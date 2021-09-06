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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Event;
use Larva\Transaction\Casts\Failure;
use Larva\Transaction\Events\ChargeClosed;
use Larva\Transaction\Events\ChargeFailed;
use Larva\Transaction\Events\ChargeSucceeded;
use Larva\Transaction\Models\Traits\DateTimeFormatter;
use Larva\Transaction\Models\Traits\UsingTimestampAsPrimaryKey;
use Larva\Transaction\Transaction;
use Larva\Transaction\TransactionException;

/**
 * 支付模型
 * @property int $id 收款流水号
 * @property string $trade_channel 支付渠道
 * @property string $trade_type 支付类型
 * @property string $transaction_no 支付网关交易号
 * @property string $order_id 订单ID
 * @property string $order_type 订单类型
 * @property string $subject 支付标题
 * @property string $description 描述
 * @property int $total_amount 支付金额，单位分
 * @property string $currency 支付币种
 * @property string $state 交易状态
 * @property string $client_ip 客户端IP
 * @property array $metadata 元信息
 * @property array $credential 客户端支付凭证
 * @property Failure $failure 错误信息
 * @property array $extra 网关返回的信息
 * @property CarbonInterface|null $succeed_at 支付完成时间
 * @property CarbonInterface|null $expired_at 过期时间
 * @property CarbonInterface $created_at 创建时间
 * @property CarbonInterface|null $updated_at 更新时间
 * @property CarbonInterface|null $deleted_at 软删除时间
 *
 * @property Model $order 触发该收款的订单模型
 * @property Refund $refunds 退款列表
 *
 * @property-read bool $paid 是否已付款
 * @property-read bool $refunded 是否有退款
 * @property-read bool $reversed 是否已撤销
 * @property-read bool $closed 是否已关闭
 * @property-read string $stateDesc 状态描述
 * @property-read int $refundedAmount 已退款钱数
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Charge extends Model
{
    use SoftDeletes, UsingTimestampAsPrimaryKey, DateTimeFormatter;

    public const STATE_SUCCESS = 'SUCCESS';
    public const STATE_REFUND = 'REFUND';
    public const STATE_NOTPAY = 'NOTPAY';
    public const STATE_CLOSED = 'CLOSED';
    public const STATE_REVOKED = 'REVOKED';
    public const STATE_USERPAYING = 'USERPAYING';
    public const STATE_PAYERROR = 'PAYERROR';
    public const STATE_ACCEPT = 'ACCEPT';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction_charges';

    /**
     * @var bool 主键自增
     */
    public $incrementing = false;

    /**
     * @var array 批量赋值属性
     */
    public $fillable = [
        'id', 'trade_channel', 'trade_type', 'transaction_no', 'subject', 'description', 'total_amount', 'currency',
        'state', 'client_ip', 'metadata', 'credential', 'extra', 'failure', 'succeed_at', 'expired_at'
    ];

    /**
     * 这个属性应该被转换为原生类型.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'int',
        'trade_channel' => 'string',
        'trade_type' => 'string',
        'transaction_no' => 'string',
        'subject' => 'string',
        'description' => 'string',
        'total_amount' => 'int',
        'currency' => 'string',
        'state' => 'string',
        'client_ip' => 'string',
        'metadata' => 'array',
        'extra' => 'array',
        'credential' => 'array',
        'failure' => Failure::class
    ];

    /**
     * 应该被调整为日期的属性
     *
     * @var array
     */
    protected $dates = [
        'succeed_at', 'expired_at', 'created_at', 'updated_at', 'deleted_at'
    ];

    /**
     * 交易状态，枚举值
     * @var array|string[]
     */
    protected static array $stateMaps = [
        self::STATE_SUCCESS => '支付成功',
        self::STATE_REFUND => '转入退款',
        self::STATE_NOTPAY => '未支付',
        self::STATE_CLOSED => '已关闭',
        self::STATE_REVOKED => '已撤销',//已撤销（仅付款码支付会返回）
        self::STATE_USERPAYING => '用户支付中',//用户支付中（仅付款码支付会返回）
        self::STATE_PAYERROR => '支付失败',//支付失败（仅付款码支付会返回）
        self::STATE_ACCEPT => '已接收，等待扣款',
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function booted()
    {
        static::creating(function (Charge $model) {
            $model->id = $model->generateKey();
            $model->currency = $model->currency ?: 'CNY';
            $model->expired_at = $model->expired_at ?? $model->freshTimestamp()->addHours(24);//过期时间24小时
            $model->state = static::STATE_NOTPAY;
        });
        static::created(function (Charge $model) {
            if (!empty($model->trade_channel) && !empty($model->trade_type)) {//不为空就预下单
                $model->prePay();
            }
        });
    }

    /**
     * 获取 State Label
     * @return string[]
     */
    public static function getStateMaps(): array
    {
        return static::$stateMaps;
    }

    /**
     * 关联订单
     * @return MorphTo
     */
    public function order(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 关联退款
     * @return HasMany
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * 是否已付款
     * @return bool
     */
    public function getPaidAttribute(): bool
    {
        return $this->state == static::STATE_SUCCESS || $this->state == static::STATE_REFUND;
    }

    /**
     * 是否有退款
     * @return bool
     */
    public function getRefundedAttribute(): bool
    {
        return $this->state == static::STATE_REFUND;
    }

    /**
     * 是否已撤销
     * @return bool
     */
    public function getReversedAttribute(): bool
    {
        return $this->state == static::STATE_REVOKED;
    }

    /**
     * 是否已关闭
     * @return bool
     */
    public function getClosedAttribute(): bool
    {
        return $this->state == static::STATE_CLOSED;
    }

    /**
     * 状态描述
     * @return string
     */
    public function getStateDescAttribute(): string
    {
        return static::$stateMaps[$this->state] ?? '未知状态';
    }

    /**
     * 已退款钱数
     * @return int
     */
    public function getRefundedAmountAttribute(): int
    {
        if ($this->refunded) {
            return $this->refunds()
                ->where('status', 'in', [Refund::STATUS_PENDING, Refund::STATUS_SUCCESS, Refund::STATUS_PROCESSING])
                ->sum('amount');
        }
        return 0;
    }

    /**
     * 发起退款
     * @param string $reason 退款原因
     * @return Refund
     * @throws TransactionException
     */
    public function refund(string $reason): Refund
    {
        if ($this->paid) {
            /** @var Refund $refund */
            $refund = $this->refunds()->create([
                'charge_id' => $this->id,
                'amount' => $this->total_amount,
                'reason' => $reason,
            ]);
            $this->update(['state' => static::STATE_REFUND]);
            return $refund;
        }
        throw new TransactionException('Not paid, no refund.');
    }

    /**
     * 设置已付款状态
     * @param string $transactionNo 支付渠道返回的交易流水号。
     * @param array $extra
     * @return bool
     */
    public function markSucceeded(string $transactionNo, array $extra = []): bool
    {
        if ($this->paid) {
            return true;
        }
        $state = $this->updateQuietly([
            'transaction_no' => $transactionNo,
            'expired_at' => null,
            'succeed_at' => $this->freshTimestamp(),
            'state' => static::STATE_SUCCESS,
            'credential' => null,
            'extra' => $extra
        ]);
        Event::dispatch(new ChargeSucceeded($this));
        return $state;
    }

    /**
     * 设置支付错误
     * @param string $code
     * @param string $desc
     * @return bool
     */
    public function markFailed(string $code, string $desc): bool
    {
        $state = $this->updateQuietly([
            'state' => static::STATE_PAYERROR,
            'credential' => null,
            'failure' => ['code' => $code, 'desc' => $desc]
        ]);
        Event::dispatch(new ChargeFailed($this));
        return $state;
    }

    /**
     * 关闭该笔收单
     * @return bool
     * @throws Exception
     */
    public function markClosed(): bool
    {
        if ($this->state == static::STATE_NOTPAY) {
            $channel = Transaction::getGateway($this->trade_channel);
            try {
                $result = $channel->close($this->id);
                if ($result) {
                    $this->update(['state' => static::STATE_CLOSED, 'credential' => null]);
                    Event::dispatch(new ChargeClosed($this));
                    return true;
                }
                return false;
            } catch (GatewayException | InvalidArgumentException | InvalidConfigException | InvalidSignException $e) {
                Log::error($e->getMessage());
            }
        }
        return false;
    }

    /**
     * 获取指定渠道的支付凭证
     * @param string $channel
     * @param string $type
     * @return array
     * @throws InvalidGatewayException
     */
    public function getCredential(string $channel, string $type): array
    {
        $this->update(['trade_channel' => $channel, 'trade_type' => $type]);
        $this->prePay();
        $this->refresh();
        return $this->credential;
    }

    /**
     * 订单付款预下单
     * @throws InvalidGatewayException
     */
    public function prePay()
    {
        if ($this->trade_channel == Transaction::CHANNEL_WECHAT) {
            $order['notify_url'] = route('transaction.notify.charge', ['channel' => Transaction::CHANNEL_WECHAT]);
        } elseif ($this->trade_channel == Transaction::CHANNEL_ALIPAY) {
            $order['notify_url'] = route('transaction.notify.charge', ['channel' => Transaction::CHANNEL_ALIPAY]);
            if ($this->trade_type == 'wap') {
                $order['return_url'] = route('transaction.callback.charge', ['channel' => Transaction::CHANNEL_ALIPAY]);
            }
        }
    }
}
