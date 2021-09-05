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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Larva\Transaction\Events\ChargeClosed;
use Larva\Transaction\Events\ChargeFailed;
use Larva\Transaction\Events\ChargeShipped;
use Larva\Transaction\Transaction;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Yansongda\Pay\Exceptions\GatewayException;
use Yansongda\Pay\Exceptions\InvalidArgumentException;
use Yansongda\Pay\Exceptions\InvalidConfigException;
use Yansongda\Pay\Exceptions\InvalidGatewayException;
use Yansongda\Pay\Exceptions\InvalidSignException;
use Yansongda\Supports\Collection;

/**
 * 支付模型
 * @property string $id
 * @property int $user_id 用户ID
 * @property boolean $reversed 已撤销
 * @property boolean $refunded 已退款
 * @property string $trade_channel 支付渠道
 * @property string $trade_type 支付类型
 * @property string $subject 支付标题
 * @property string $order_id 订单ID
 * @property float $amount 支付金额，单位分
 * @property string $currency 支付币种
 * @property boolean $paid 是否支付成功
 * @property string $transaction_no 支付网关交易号
 * @property int $amount_refunded 已经退款的金额
 * @property CarbonInterface|null $time_expire 时间
 * @property string $client_ip 客户端IP
 * @property string $failure_code 失败代码
 * @property string $failure_msg 失败消息
 * @property string $description 描述
 * @property array $credential 客户端支付凭证
 * @property array $metadata 元数据
 * @property array $extra 渠道数据
 *
 * @property \App\Models\User $user
 * @property Model $order 触发该收款的订单模型
 * @property Refund $refunds
 *
 * @property CarbonInterface $time_paid 付款时间
 * @property CarbonInterface $deleted_at 软删除时间
 * @property CarbonInterface $created_at 创建时间
 * @property CarbonInterface $updated_at 更新时间
 *
 * @property-read int $refundable 可退款金额
 * @property-read boolean $allowRefund 是否可以退款
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Charge extends Model
{
    use SoftDeletes;

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
        'id', 'user_id', 'paid', 'refunded', 'reversed', 'trade_channel', 'trade_type', 'amount', 'currency', 'subject', 'body',
        'client_ip', 'extra', 'time_paid', 'time_expire', 'transaction_no', 'amount_refunded', 'failure_code',
        'failure_msg', 'metadata', 'credential', 'description'
    ];

    /**
     * 这个属性应该被转换为原生类型.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'int',
        'paid' => 'boolean',
        'refunded' => 'boolean',
        'reversed' => 'boolean',
        'extra' => 'array',
        'credential' => 'array',
        'metadata' => 'array',
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
        'time_paid',
        'time_expire',
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function booted()
    {
        static::creating(function ($model) {
            /** @var Charge $model */
            $model->id = $model->generateId();
            $model->currency = $model->currency ?: 'CNY';
            $model->time_expire = $model->freshTimestamp()->addHours(24);//过期时间24小时
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
     * 获取指定渠道的支付凭证
     * @param string $channel
     * @param string $type
     * @return array
     * @throws InvalidGatewayException
     */
    public function getCredential(string $channel, string $type): array
    {
        $this->update(['trade_channel' => $channel, 'trade_type' => $type]);
        $this->unify();
        $this->refresh();
        return $this->credential;
    }

    /**
     * 查询已付款的
     * @param Builder $query
     * @return Builder
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('paid', true);
    }

    /**
     * 多态关联
     * @return MorphTo
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 获取用户关联
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.' . config('auth.guards.web.provider') . '.model'));
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
     * 是否还可以继续退款
     * @return boolean
     */
    public function getAllowRefundAttribute(): bool
    {
        if ($this->paid && $this->refundable > 0) {
            return true;
        }
        return false;
    }

    /**
     * 获取可退款钱数
     * @return float|int
     */
    public function getRefundableAttribute()
    {
        return $this->amount - $this->amount_refunded;
    }

    /**
     * 生成流水号
     * @return string
     */
    public function generateId(): string
    {
        $i = rand(0, 9999);
        do {
            if (9999 == $i) {
                $i = 0;
            }
            $i++;
            $id = time() . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
            $row = static::query()->where($this->primaryKey, '=', $id)->exists();
        } while ($row);
        return $id;
    }

    /**
     * 设置支付错误
     * @param string $code
     * @param string $msg
     * @return bool
     */
    public function markFailed(string $code, string $msg): bool
    {
        $status = $this->update(['failure_code' => $code, 'failure_msg' => $msg]);
        Event::dispatch(new ChargeFailed($this));
        return $status;
    }

    /**
     * 设置已付款状态
     * @param string $transactionNo 支付渠道返回的交易流水号。
     * @return bool
     */
    public function markPaid(string $transactionNo): bool
    {
        if ($this->paid) {
            return true;
        }
        $paid = $this->update(['transaction_no' => $transactionNo, 'time_paid' => $this->freshTimestamp(), 'paid' => true]);
        Event::dispatch(new ChargeShipped($this));
        return $paid;
    }

    /**
     * 关闭关闭该笔收单
     * @return bool
     * @throws Exception
     */
    public function markClosed(): bool
    {
        if ($this->paid) {
            $this->update(['failure_code' => 'FAIL', 'failure_msg' => '已支付，无法撤销']);
            return false;
        } elseif ($this->reversed) {//已经撤销
            return true;
        } else {
            $channel = Transaction::getChannel($this->trade_channel);
            try {
                if ($channel->close($this->id)) {
                    Event::dispatch(new ChargeClosed($this));
                    $this->update(['reversed' => true, 'credential' => []]);
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
     * 发起退款
     * @param string $description 退款描述
     * @return Refund
     * @throws Exception
     */
    public function markRefund(string $description): Refund
    {
        if ($this->paid) {
            /** @var Refund $refund */
            $refund = $this->refunds()->create([
                'user_id' => $this->user_id,
                'amount' => $this->amount,
                'description' => $description,
                'charge_id' => $this->id,
                'charge_order_id' => $this->order,
            ]);
            $this->update(['refunded' => true]);
            return $refund;
        }
        throw new Exception('Not paid, no refund.');
    }

    /**
     * 订单付款预下单
     * @throws InvalidGatewayException
     */
    public function unify()
    {
        $channel = Transaction::getChannel($this->trade_channel);
        $order = [
            'out_trade_no' => $this->id,
        ];

        if ($this->trade_channel == Transaction::CHANNEL_WECHAT) {
            $order['spbill_create_ip'] = $this->client_ip;
            $order['total_fee'] = $this->amount;//总金额，单位分
            $order['body'] = $this->description;
            if ($this->time_expire) {
                $order['time_expire'] = $this->time_expire->format('YmdHis');
            }
            $order['notify_url'] = route('transaction.notify.charge', ['channel' => Transaction::CHANNEL_WECHAT]);
        } elseif ($this->trade_channel == Transaction::CHANNEL_ALIPAY) {
            $order['total_amount'] = $this->amount / 100;//总钱数，单位元
            $order['subject'] = $this->subject;
            if ($this->description) {
                $order['body'] = $this->description;
            }
            if ($this->time_expire) {
                $order['time_expire'] = $this->time_expire;
            }
            $order['notify_url'] = route('transaction.notify.charge', ['channel' => Transaction::CHANNEL_ALIPAY]);
            if ($this->trade_type == 'wap') {
                $order['return_url'] = route('transaction.callback.charge', ['channel' => Transaction::CHANNEL_ALIPAY]);
            }
        }
        // 获取支付凭证
        $credential = $channel->pay($this->trade_type, $order);
        if ($credential instanceof Collection) {
            $credential = $credential->toArray();
        } elseif ($credential instanceof RedirectResponse) {
            $credential = ['url' => $credential->getTargetUrl()];
        } elseif ($credential instanceof JsonResponse) {
            $credential = json_decode($credential->getContent(), true);
        } elseif ($credential instanceof Response) {//此处判断一定要在最后
            if ($this->trade_channel == Transaction::CHANNEL_ALIPAY && $this->trade_type == 'app') {
                $params = [];
                parse_str($credential->getContent(), $params);
                $credential = $params;
            } else {//WEB H5
                $credential = ['html' => $credential->getContent()];
            }
        }
        $this->update(['credential' => $credential]);
    }
}
