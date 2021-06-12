<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */

declare (strict_types=1);

namespace Larva\Transaction\Models;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Event;
use Larva\Transaction\Events\RefundFailure;
use Larva\Transaction\Events\RefundSuccess;
use Larva\Transaction\Transaction;

/**
 * 退款处理模型
 * @property string $id
 * @property int $user_id
 * @property int $charge_id
 * @property int $amount
 * @property string $status
 * @property string $description 退款描述
 * @property string $failure_code
 * @property string $failure_msg
 * @property string $charge_order_id
 * @property string $transaction_no
 * @property string $funding_source 退款资金来源
 * @property array $metadata 元数据
 * @property array $extra 渠道数据
 * @property CarbonInterface $deleted_at 软删除时间
 * @property CarbonInterface $created_at 创建时间
 * @property CarbonInterface $updated_at 更新时间
 * @property CarbonInterface $time_succeed 成功时间
 *
 * @property Charge $charge
 * @property \App\Models\User $user
 * @property-read boolean $succeed
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Refund extends Model
{
    use SoftDeletes;

    //退款状态
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCEEDED = 'succeeded';
    const STATUS_FAILED = 'failed';

    //退款资金来源
    const FUNDING_SOURCE_UNSETTLED = 'unsettled_funds';//使用未结算资金退款
    const FUNDING_SOURCE_RECHARGE = 'recharge_funds';//使用可用余额退款

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction_refunds';

    /**
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var bool 关闭主键自增
     */
    public $incrementing = false;

    /**
     * @var array 批量赋值属性
     */
    public $fillable = [
        'id', 'user_id', 'charge_id', 'amount', 'status', 'description', 'failure_code', 'failure_msg', 'charge_order_id',
        'transaction_no', 'funding_source', 'metadata', 'extra', 'time_succeed'
    ];

    /**
     * 这个属性应该被转换为原生类型.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'int',
        'succeed' => 'boolean',
        'metadata' => 'array',
        'extra' => 'array'
    ];

    /**
     * 应该被调整为日期的属性
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'time_succeed',
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            /** @var Refund $model */
            $model->id = $model->generateId();
            $model->status = static::STATUS_PENDING;
            $model->funding_source = $model->getFundingSource();
        });
    }

    /**
     * 为数组 / JSON 序列化准备日期。
     *
     * @param DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format($this->dateFormat ?: 'Y-m-d H:i:s');
    }

    /**
     * Get the user that the charge belongs to.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.' . config('auth.guards.web.provider') . '.model'));
    }

    /**
     * 关联收单
     *
     * @return BelongsTo
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    /**
     * 生成流水号
     * @return string
     */
    protected function generateId(): string
    {
        $i = rand(0, 9999);
        do {
            if (9999 == $i) {
                $i = 0;
            }
            $i++;
            $id = time() . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
            $row = static::query()->where($this->primaryKey, $id)->exists();
        } while ($row);
        return $id;
    }

    /**
     * 获取微信退款资金来源
     * @return string
     */
    public function getFundingSource(): string
    {
        return config('transaction.wechat.unsettled_funds', 'REFUND_SOURCE_RECHARGE_FUNDS');
    }

    /**
     * 退款是否成功
     * @return bool
     */
    public function getSucceedAttribute(): bool
    {
        return $this->status == self::STATUS_SUCCEEDED;
    }

    /**
     * 设置退款错误
     * @param string $code
     * @param string $msg
     * @return bool
     */
    public function setFailure(string $code, string $msg): bool
    {
        $succeed = $this->update(['status' => self::STATUS_FAILED, 'failure_code' => $code, 'failure_msg' => $msg]);
        $this->charge->update(['amount_refunded' => $this->charge->amount_refunded - $this->amount]);//可退款金额，减回去
        Event::dispatch(new RefundFailure($this));
        return $succeed;
    }

    /**
     * 设置退款成功
     * @param string $transactionNo
     * @param array $params
     * @return bool
     */
    public function setRefunded(string $transactionNo, array $params = []): bool
    {
        if ($this->succeed) {
            return true;
        }
        $this->update(['status' => self::STATUS_SUCCEEDED, 'transaction_no' => $transactionNo, 'time_succeed' => $this->freshTimestamp(), 'extra' => $params]);
        Event::dispatch(new RefundSuccess($this));
        return $this->succeed;
    }

    /**
     * 网关退款
     * @return Refund
     * @throws Exception
     */
    public function send(): Refund
    {
        $this->charge->update(['refunded' => true, 'amount_refunded' => $this->charge->amount_refunded + $this->amount]);
        $channel = Transaction::getChannel($this->charge->channel);
        if ($this->charge->channel == Transaction::CHANNEL_WECHAT) {
            $refundAccount = 'REFUND_SOURCE_RECHARGE_FUNDS';
            if ($this->funding_source == Refund::FUNDING_SOURCE_UNSETTLED) {
                $refundAccount = 'REFUND_SOURCE_UNSETTLED_FUNDS';
            }
            $order = [
                'out_refund_no' => $this->id,
                'out_trade_no' => $this->charge->id,
                'total_fee' => $this->charge->amount,
                'refund_fee' => $this->amount,
                'refund_fee_type' => $this->charge->currency,
                'refund_desc' => $this->description,
                'refund_account' => $refundAccount,
                'notify_url' => route('transaction.notify.refund', ['channel' => Transaction::CHANNEL_WECHAT]),
            ];
            try {
                $response = $channel->refund($order);
                $this->setRefunded($response->transaction_id, $response->toArray());
            } catch (Exception $exception) {//设置失败
                $this->setFailure('FAIL', $exception->getMessage());
            }
        } else if ($this->charge->channel == Transaction::CHANNEL_ALIPAY) {
            $order = [
                'out_trade_no' => $this->charge->id,
                'trade_no' => $this->charge->transaction_no,
                'refund_currency' => $this->charge->currency,
                'refund_amount' => $this->amount / 100,
                'refund_reason' => '退款',
                'out_request_no' => $this->id
            ];
            try {
                $response = $channel->refund($order);
                $this->setRefunded($response->trade_no, $response->toArray());
            } catch (Exception $exception) {//设置失败
                $this->setFailure('FAIL', $exception->getMessage());
            }
        }
        return $this;
    }
}