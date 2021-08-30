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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Event;
use Larva\Transaction\Casts\Failure;
use Larva\Transaction\Events\ChargeClosed;
use Larva\Transaction\Events\ChargeFailed;
use Larva\Transaction\Events\ChargeShipped;
use Larva\Transaction\Traits\UsingTimestampAsPrimaryKey;

/**
 * 支付模型
 * @property string $id 付款流水号
 * @property string $trade_channel 支付渠道
 * @property string $trade_type  支付类型
 * @property string $transaction_no 支付网关交易号
 * @property string $order_id 订单ID
 * @property string $order_type 订单类型
 * @property string $subject 支付标题
 * @property string $description 描述
 * @property int $total_amount 支付金额，单位分
 * @property string $currency 支付币种
 * @property string $state 交易状态
 * @property string $client_ip 客户端IP
 * @property array $payer 支付者信息
 * @property array $credential 客户端支付凭证
 * @property Failure $failure 错误信息
 * @property CarbonInterface|null $succeed_at 支付完成时间
 * @property CarbonInterface|null $expired_at 过期时间
 * @property CarbonInterface $created_at 创建时间
 * @property CarbonInterface|null $updated_at 更新时间
 * @property CarbonInterface|null $deleted_at 软删除时间
 *
 * @property-read bool $refunded 是否有退款
 * @property-read bool $reversed 是否撤销
 * @property-read string $stateDesc
 * @property Model $order 触发该收款的订单模型
 * @property Refund $refunds 退款实例
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Charge extends Model
{
    use SoftDeletes, UsingTimestampAsPrimaryKey;

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
     * @var string 主键名称
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
        'id', 'trade_channel', 'trade_type', 'transaction_no', 'subject', 'description', 'total_amount', 'currency',
        'state', 'client_ip', 'payer', 'credential', 'failure', 'expired_at'
    ];

    /**
     * 这个属性应该被转换为原生类型.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'trade_channel' => 'string',
        'trade_type' => 'string',
        'transaction_no' => 'string',
        'subject' => 'string',
        'description' => 'string',
        'total_amount' => 'int',
        'currency' => 'string',
        'state' => 'string',
        'client_ip' => 'string',
        'payer' => 'array',
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
        self::STATE_REVOKED => '已撤销',
        self::STATE_USERPAYING => '用户支付中',
        self::STATE_PAYERROR => '支付失败',
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
            /** @var Charge $model */
            $model->currency = $model->currency ?: 'CNY';
            $model->expired_at = $model->expired_at ?? $model->freshTimestamp()->addHours(24);//过期时间24小时
            $model->state = static::STATE_NOTPAY;
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
     * 是否有退款
     * @return bool
     */
    public function getRefundedAttribute(): bool
    {
        return $this->state == static::STATE_REFUND;
    }

    /**
     * 已退款钱数
     * @return int|mixed
     */
    public function getRefundedAmountAttribute()
    {
        if ($this->refunded) {
            return $this->refunds()->sum('');
        }
        return 0;
    }

    /**
     * 是否已经撤销
     * @return bool
     */
    public function getReversedAttribute(): bool
    {
        return $this->state == static::STATE_REVOKED;
    }

    /**
     * 状态描述
     * @return mixed|string
     */
    public function getStateDescAttribute()
    {
        return static::$stateMaps[$this->state] ?? '未知状态';
    }

    /**
     * 发起退款
     * @param string $description 退款描述
     * @return Refund
     */
    public function refund(string $description): Refund
    {
        return $this->refunds()->create([
            'description' => $description
        ]);
    }

    /**
     * 撤销交易
     */
    public function revoke()
    {

    }

    /**
     * 关闭交易
     */
    public function close()
    {

    }

    /**
     * 设置支付错误
     * @param array $failure
     * @return bool
     */
    public function markFailed(array $failure): bool
    {
        $status = $this->saveQuietly(['state' => static::STATE_PAYERROR, 'failure' => $failure]);
        Event::dispatch(new ChargeFailed($this));
        return $status;
    }

    /**
     * 设置已付款状态
     * @param string $transactionNo 支付渠道返回的交易流水号。
     * @return bool
     */
    public function markSucceed(string $transactionNo): bool
    {
        $state = $this->saveQuietly([
            'transaction_no' => $transactionNo,
            'succeed_at' => $this->freshTimestamp(),
            'state' => static::STATE_SUCCESS,
        ]);
        Event::dispatch(new ChargeShipped($this));
        return $state;
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
}