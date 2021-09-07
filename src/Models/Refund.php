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
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Event;
use Larva\Transaction\Casts\Failure;
use Larva\Transaction\Events\RefundFailed;
use Larva\Transaction\Events\RefundSucceeded;
use Larva\Transaction\Jobs\HandleRefundJob;
use Larva\Transaction\Models\Traits\DateTimeFormatter;
use Larva\Transaction\Models\Traits\UsingTimestampAsPrimaryKey;
use Larva\Transaction\Transaction;

/**
 * 退款处理模型
 * @property string $id 退款流水号
 * @property int $charge_id 付款ID
 * @property string $transaction_no 网关流水号
 * @property int $amount 退款金额/单位分
 * @property string $reason 退款描述
 * @property string $status 退款状态
 * @property Failure $failure 退款失败对象
 * @property array $extra 渠道返回的额外信息
 * @property CarbonInterface|null $succeed_at 成功时间
 * @property CarbonInterface $created_at 创建时间
 * @property CarbonInterface $updated_at 更新时间
 * @property CarbonInterface|null $deleted_at 软删除时间
 *
 * @property-read boolean $succeed
 * @property Charge $charge
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Refund extends Model
{
    use SoftDeletes, UsingTimestampAsPrimaryKey, DateTimeFormatter;

    // 退款状态机
    public const STATUS_PENDING = 'PENDING';//待处理
    public const STATUS_SUCCESS = 'SUCCESS';//退款成功
    public const STATUS_CLOSED = 'CLOSED';//退款关闭
    public const STATUS_PROCESSING = 'PROCESSING';//退款处理中
    public const STATUS_ABNORMAL = 'ABNORMAL';//退款异常

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction_refunds';

    /**
     * @var bool 关闭主键自增
     */
    public $incrementing = false;

    /**
     * @var array 批量赋值属性
     */
    public $fillable = [
        'id', 'charge_id', 'transaction_no', 'amount', 'reason', 'status', 'extra', 'failure', 'succeed_at',
    ];

    /**
     * 这个属性应该被转换为原生类型.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'int',
        'charge_id' => 'int',
        'transaction_no' => 'string',
        'amount' => 'int',
        'reason' => 'string',
        'status' => 'string',
        'extra' => 'array',
        'failure' => Failure::class
    ];

    /**
     * 应该被调整为日期的属性
     *
     * @var array
     */
    protected $dates = [
        'succeed_at', 'created_at', 'updated_at', 'deleted_at',
    ];

    /**
     * 交易状态，枚举值
     * @var array|string[]
     */
    protected static array $statusMaps = [
        self::STATUS_PENDING => '待处理',
        self::STATUS_SUCCESS => '退款成功',
        self::STATUS_CLOSED => '退款关闭',
        self::STATUS_PROCESSING => '退款处理中',
        self::STATUS_ABNORMAL => '退款异常',
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function booted()
    {
        static::creating(function (Refund $model) {
            $model->id = $model->generateKey();
            $model->status = static::STATUS_PENDING;
        });
        static::created(function (Refund $model) {
            Charge::query()->where('id', $model->charge_id)->increment('refunded_amount', $model->amount, ['state' => Charge::STATE_REFUND]);
            HandleRefundJob::dispatch($model)->delay(1);
        });
    }

    /**
     * 获取 Status Label
     * @return string[]
     */
    public static function getStatusMaps(): array
    {
        return static::$statusMaps;
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
     * 退款是否成功
     * @return bool
     */
    public function getSucceedAttribute(): bool
    {
        return $this->status == self::STATUS_SUCCESS;
    }

    /**
     * 设置退款错误
     * @param string $code
     * @param string $desc
     * @return bool
     */
    public function markFailed(string $code, string $desc): bool
    {
        $succeed = $this->updateQuietly(['status' => self::STATUS_ABNORMAL, 'failure' => ['code' => $code, 'desc' => $desc]]);
        Charge::query()->where('id', $this->charge_id)->decrement('refunded_amount', $this->amount);
        Event::dispatch(new RefundFailed($this));
        return $succeed;
    }

    /**
     * 设置退款完成
     * @param string $transactionNo
     * @param array $extra
     * @return bool
     */
    public function markSucceeded(string $transactionNo, array $extra = []): bool
    {
        if ($this->succeed) {
            return true;
        }
        $this->updateQuietly(['status' => self::STATUS_SUCCESS, 'transaction_no' => $transactionNo, 'succeed_at' => $this->freshTimestamp(), 'extra' => $extra]);
        Event::dispatch(new RefundSucceeded($this));
        return $this->succeed;
    }

    /**
     * 网关退款
     * @return Refund
     * @throws Exception
     */
    public function gatewayHandle(): Refund
    {
        $channel = Transaction::getGateway($this->charge->trade_channel);
        if ($this->charge->trade_channel == Transaction::CHANNEL_WECHAT) {
            $order = [
                'out_refund_no' => $this->id,
                'out_trade_no' => $this->charge->id,
                'total_fee' => $this->charge->total_amount,
                'refund_fee' => $this->amount,
                'refund_fee_type' => $this->charge->currency,
                'refund_desc' => $this->reason,
                'refund_account' => 'REFUND_SOURCE_UNSETTLED_FUNDS',//使用未结算资金退款
                'notify_url' => route('transaction.notify.refund', ['channel' => Transaction::CHANNEL_WECHAT]),
            ];
            try {
                $response = $channel->refund($order);
                $this->markSucceeded($response->transaction_id, $response->toArray());
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
                $this->markSucceeded($response->trade_no, $response->toArray());
            } catch (Exception $exception) {//设置失败
                $this->markFailed('FAIL', $exception->getMessage());
            }
        }
        return $this;
    }
}
