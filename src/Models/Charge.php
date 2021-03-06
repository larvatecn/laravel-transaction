<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 * @license http://www.larva.com.cn/license/
 */

namespace Larva\Transaction\Models;

use Carbon\CarbonInterface;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Larva\Transaction\Transaction;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Yansongda\Pay\Exceptions\GatewayException;
use Yansongda\Pay\Exceptions\InvalidArgumentException;
use Yansongda\Pay\Exceptions\InvalidConfigException;
use Yansongda\Pay\Exceptions\InvalidSignException;
use Yansongda\Supports\Collection;

/**
 * 支付模型
 * @property int $id
 * @property int $user_id 用户ID
 * @property boolean $reversed 已撤销
 * @property boolean $refunded 已退款
 * @property string $channel 支付渠道
 * @property string $type  支付类型
 * @property string $subject 支付标题
 * @property string $body 支付内容
 * @property string $order_id 订单ID
 * @property float $amount 支付金额，单位分
 * @property string $currency 支付币种
 * @property boolean $paid 是否支付成功
 * @property string $transaction_no 支付网关交易号
 * @property int $amount_refunded 已经退款的金额
 * @property CarbonInterface $time_expire 时间
 * @property string $client_ip 客户端IP
 * @property string $failure_code 失败代码
 * @property string $failure_msg 失败消息
 * @property string $description 描述
 * @property array $credential 客户端支付凭证
 * @property array $metadata 元数据
 * @property array $extra 渠道数据
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
     * @var bool 关闭主键自增
     */
    public $incrementing = false;

    /**
     * @var array 批量赋值属性
     */
    public $fillable = [
        'id', 'user_id', 'paid', 'refunded', 'reversed', 'type', 'channel', 'amount', 'currency', 'subject', 'body', 'client_ip', 'extra', 'time_paid',
        'time_expire', 'transaction_no', 'amount_refunded', 'failure_code', 'failure_msg', 'metadata', 'credential', 'description'
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
    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            /** @var Charge $model */
            $model->id = $model->generateId();
            $model->currency = $model->currency ? $model->currency : 'CNY';
        });
    }

    /**
     * 为数组 / JSON 序列化准备日期。
     *
     * @param \DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format($this->dateFormat ?: 'Y-m-d H:i:s');
    }

    /**
     * 获取指定渠道的支付凭证
     * @param string $channel
     * @param string $type
     * @return array
     * @throws \Yansongda\Pay\Exceptions\InvalidGatewayException
     */
    public function getCredential($channel, $type)
    {
        $this->update(['channel' => $channel, 'type' => $type]);
        $this->send();
        $this->refresh();
        return $this->credential;
    }

    /**
     * 查询已付款的
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePaid($query)
    {
        return $query->where('paid', true);
    }

    /**
     * 多态关联
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function order()
    {
        return $this->morphTo();
    }

    /**
     * 获取用户关联
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(
            config('auth.providers.' . config('auth.guards.web.provider') . '.model')
        );
    }

    /**
     * 关联退款
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * 是否还可以继续退款
     * @return boolean
     */
    public function getAllowRefundAttribute()
    {
        if ($this->paid && $this->refundable > 0) {
            return true;
        }
        return false;
    }

    /**
     * 获取可退款钱数
     * @return string
     */
    public function getRefundableAttribute()
    {
        return bcsub($this->amount, $this->amount_refunded);
    }

    /**
     * 获取Body
     * @return string
     */
    public function getBodyAttribute()
    {
        return $this->attributes['body'] ? $this->attributes['body'] : $this->subject;
    }

    /**
     * 生成流水号
     * @return int
     */
    public function generateId()
    {
        $i = rand(0, 9999);
        do {
            if (9999 == $i) {
                $i = 0;
            }
            $i++;
            $id = time() . str_pad($i, 4, '0', STR_PAD_LEFT);
            $row = static::query()->where('id', '=', $id)->exists();
        } while ($row);
        return $id;
    }

    /**
     * 设置支付错误
     * @param string $code
     * @param string $msg
     * @return bool
     */
    public function setFailure($code, $msg)
    {
        $status = (bool)$this->update(['failure_code' => $code, 'failure_msg' => $msg]);
        event(new \Larva\Transaction\Events\ChargeFailure($this));
        return $status;
    }

    /**
     * 设置已付款状态
     * @param string $transactionNo 支付渠道返回的交易流水号。
     * @return bool
     */
    public function setPaid($transactionNo)
    {
        if ($this->paid) {
            return true;
        }
        $paid = (bool)$this->update(['transaction_no' => $transactionNo, 'time_paid' => $this->freshTimestamp(), 'paid' => true]);
        event(new \Larva\Transaction\Events\ChargeShipped($this));
        return $paid;
    }

    /**
     * 关闭关闭该笔收单
     * @return bool
     * @throws Exception
     */
    public function setClose()
    {
        if ($this->paid) {
            $this->update(['failure_code' => 'FAIL', 'failure_msg' => '已支付，无法撤销']);
            return false;
        } else if ($this->reversed) {//已经撤销
            return true;
        } else {
            $channel = Transaction::getChannel($this->channel);
            try {
                if ($channel->close($this->id)) {
                    event(new \Larva\Transaction\Events\ChargeClosed($this));
                    $this->update(['reversed' => true, 'credential' => []]);
                    return true;
                }
                return false;
            } catch (GatewayException $e) {
                Log::error($e->getMessage());
            } catch (InvalidArgumentException $e) {
                Log::error($e->getMessage());
            } catch (InvalidConfigException $e) {
                Log::error($e->getMessage());
            } catch (InvalidSignException $e) {
                Log::error($e->getMessage());
            }
        }
        return false;
    }

    /**
     * 发起退款
     * @param string $description 退款描述
     * @return Model|Refund
     * @throws Exception
     */
    public function setRefund($description)
    {
        if ($this->paid) {
            $refund = $this->refunds()->create([
                'user_id' => $this->user_id,
                'amount' => $this->amount,
                'description' => $description,
                'charge_id' => $this->id,
                'charge_order_id' => $this->order_id,
            ]);
            $this->update(['refunded' => true]);
            return $refund;
        }
        throw new Exception ('Not paid, no refund.');
    }

    /**
     * 订单付款预下单
     * @param Charge $charge
     * @throws \Yansongda\Pay\Exceptions\InvalidGatewayException
     * @throws Exception
     */
    public function send()
    {
        $channel = Transaction::getChannel($this->channel);
        $order = [
            'out_trade_no' => $this->id,
        ];

        if ($this->channel == Transaction::CHANNEL_WECHAT) {
            $order['spbill_create_ip'] = $this->client_ip;
            $order['total_fee'] = $this->amount;//总金额，单位分
            $order['body'] = $this->body;
            if ($this->time_expire) {
                $order['time_expire'] = $this->time_expire->format('YmdHis');
            }
            $order['notify_url'] = route('transaction.notify.charge', ['channel' => Transaction::CHANNEL_WECHAT]);
        } else if ($this->channel == Transaction::CHANNEL_ALIPAY) {
            $order['total_amount'] = bcdiv($this->amount, 100, 2);//总钱数，单位元
            $order['subject'] = $this->subject;
            if ($this->body) {
                $order['body'] = $this->body;
            }
            if ($this->time_expire) {
                $order['time_expire'] = $this->time_expire;
            }
            $order['notify_url'] = route('transaction.notify.charge', ['channel' => Transaction::CHANNEL_ALIPAY]);
            if ($this->type == 'wap') {
                $order['return_url'] = route('transaction.callback.charge', ['channel' => Transaction::CHANNEL_ALIPAY]);
            }
        }
        // 获取支付凭证
        $credential = $channel->pay($this->type, $order);
        if ($credential instanceof Collection) {
            $credential = $credential->toArray();
        } else if ($credential instanceof RedirectResponse) {
            $credential = ['url' => $credential->getTargetUrl()];
        } else if ($credential instanceof JsonResponse) {
            $credential = json_decode($credential->getContent(), true);
        } else if ($credential instanceof Response) {//此处判断一定要在最后
            if ($this->channel == Transaction::CHANNEL_ALIPAY && $this->type == 'app') {
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