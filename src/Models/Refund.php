<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */
namespace Larva\Transaction\Models;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Event;
use Larva\Transaction\Events\RefundFailed;
use Larva\Transaction\Events\RefundSucceed;
use Larva\Transaction\Transaction;

/**
 * 退款处理模型
 * @property string $id
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
 * @property CarbonInterface|null $deleted_at 软删除时间
 * @property CarbonInterface $created_at 创建时间
 * @property CarbonInterface $updated_at 更新时间
 * @property CarbonInterface|null $succeed_at 成功时间
 *
 * @property Charge $charge
 * @property-read boolean $succeed
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Refund extends Model
{
    use SoftDeletes;
    use Traits\DateTimeFormatter;
    use Traits\UsingTimestampAsPrimaryKey;

    //退款状态
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    //退款资金来源
    public const FUNDING_SOURCE_UNSETTLED = 'unsettled_funds';//使用未结算资金退款
    public const FUNDING_SOURCE_RECHARGE = 'recharge_funds';//使用可用余额退款

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
        'id', 'charge_id', 'amount', 'status', 'description', 'failure_code', 'failure_msg', 'charge_order_id',
        'transaction_no', 'funding_source', 'metadata', 'extra', 'succeed_at'
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
        'succeed_at',
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function booted()
    {
        static::creating(function ($model) {
            /** @var Refund $model */
            $model->id = $model->generateKey();
            $model->status = static::STATUS_PENDING;
            $model->funding_source = $model->getFundingSource();
        });
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
    public function markFailed(string $code, string $msg): bool
    {
        $succeed = $this->update(['status' => self::STATUS_FAILED, 'failure_code' => $code, 'failure_msg' => $msg]);
        $this->charge->update(['amount_refunded' => $this->charge->amount_refunded - $this->amount]);//可退款金额，减回去
        Event::dispatch(new RefundFailed($this));
        return $succeed;
    }

    /**
     * 设置退款完成
     * @param string $transactionNo
     * @param array $params
     * @return bool
     */
    public function markRefunded(string $transactionNo, array $params = []): bool
    {
        if ($this->succeed) {
            return true;
        }
        $this->update(['status' => self::STATUS_SUCCEEDED, 'transaction_no' => $transactionNo, 'succeed_at' => $this->freshTimestamp(), 'extra' => $params]);
        Event::dispatch(new RefundSucceed($this));
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
        $channel = Transaction::getGateway($this->charge->trade_channel);
        if ($this->charge->trade_channel == Transaction::CHANNEL_WECHAT) {
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
                $this->markRefunded($response->transaction_id, $response->toArray());
            } catch (Exception $exception) {//设置失败
                $this->markFailed('FAIL', $exception->getMessage());
            }
        } elseif ($this->charge->trade_channel == Transaction::CHANNEL_ALIPAY) {
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
                $this->markRefunded($response->trade_no, $response->toArray());
            } catch (Exception $exception) {//设置失败
                $this->markFailed('FAIL', $exception->getMessage());
            }
        }
        return $this;
    }
}
