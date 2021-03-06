<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 * @license http://www.larva.com.cn/license/
 */

namespace Larva\Transaction\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Larva\Transaction\Transaction;

/**
 * 企业付款模型，处理提现
 *
 * @property string $id 付款单ID
 * @property string $channel 付款渠道
 * @property-read boolean $paid 是否已经转账
 * @property string $status 状态
 * @property mixed $source 触发源对象
 * @property int $amount 金额
 * @property string $currency 币种
 * @property string $recipient_id 接收者ID
 * @property string $description 描述
 * @property string $transaction_no 网关交易号
 * @property string $failure_msg 失败详情
 * @property array $metadata 元数据
 * @property array $extra 扩展数据
 * @property CarbonInterface $transferred_at 交易成功时间
 * @property CarbonInterface $deleted_at 软删除时间
 * @property CarbonInterface $created_at 创建时间
 * @property CarbonInterface $updated_at 更新时间
 * @property-read boolean $scheduled
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Transfer extends Model
{
    use SoftDeletes;

    //付款状态
    const STATUS_SCHEDULED = 'scheduled';//scheduled: 待发送
    const STATUS_PENDING = 'pending';//pending: 处理中
    const STATUS_PAID = 'paid';//paid: 付款成功
    const STATUS_FAILED = 'failed';//failed: 付款失败

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction_transfer';

    /**
     * @var string 定义主键
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
        'id', 'user_id', 'channel', 'status', 'amount', 'currency', 'recipient_id', 'description', 'transaction_no', 'failure_msg', 'metadata', 'extra', 'transferred_at'
    ];

    /**
     * 这个属性应该被转换为原生类型.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'int',
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
        'transferred_at',
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
            /** @var Transfer $model */
            $model->id = $model->generateId();
            $model->currency = $model->currency ? $model->currency : 'CNY';
            $model->status = static::STATUS_SCHEDULED;
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
     * 多态关联
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function order()
    {
        return $this->morphTo();
    }

    /**
     * Get the user that the charge belongs to.
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
     * 查询已付款的
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * 生成流水号
     * @return int
     */
    protected function generateId()
    {
        $i = rand(0, 9999);
        do {
            if (9999 == $i) {
                $i = 0;
            }
            $i++;
            $id = time() . str_pad($i, 4, '0', STR_PAD_LEFT);
            $row = static::query()->where($this->primaryKey, $id)->exists();
        } while ($row);
        return $id;
    }

    /**
     * 是否已付款
     * @return boolean
     */
    public function getPaidAttribute()
    {
        return $this->status == static::STATUS_PAID;
    }

    /**
     * 是否待发送
     * @return boolean
     */
    public function getScheduledAttribute()
    {
        return $this->status == static::STATUS_SCHEDULED;
    }

    /**
     * 设置已付款
     * @param string $transactionNo
     * @param array $params
     * @return bool
     */
    public function setPaid($transactionNo, $params = [])
    {
        if ($this->paid) {
            return true;
        }
        $paid = (bool)$this->update(['transaction_no' => $transactionNo, 'transferred_at' => $this->freshTimestamp(), 'status' => static::STATUS_PAID, 'extra' => $params]);
        event(new \Larva\Transaction\Events\TransferShipped($this));
        return $paid;
    }

    /**
     * 设置提现错误
     * @param string $code
     * @param string $msg
     * @return bool
     */
    public function setFailure($code, $msg)
    {
        $res = (bool)$this->update(['status' => self::STATUS_FAILED, 'failure_code' => $code, 'failure_msg' => $msg]);
        event(new \Larva\Transaction\Events\TransferFailure($this));
        return $res;
    }

    /**
     * 主动发送付款请求到网关
     * @return Transfer
     * @throws \Exception
     */
    public function send()
    {
        if ($this->status == static::STATUS_SCHEDULED) {
            $channel = Transaction::getChannel($this->channel);
            if ($this->channel == Transaction::CHANNEL_WECHAT) {
                $config = [
                    'partner_trade_no' => $this->id,
                    'openid' => $this->recipient_id,
                    'check_name' => 'NO_CHECK',
                    'amount' => $this->amount,
                    'desc' => $this->description,
                    'type' => $this->extra['type'],
                ];
                if (isset($this->extra['user_name'])) {
                    $config['check_name'] = 'FORCE_CHECK';
                    $config['re_user_name'] = $this->extra['user_name'];
                }
                try {
                    $response = $channel->transfer($config);
                    $this->setPaid($response->payment_no, $response);
                } catch (\Exception $exception) {//设置付款失败
                    $this->setFailure('FAIL', $exception->getMessage());
                }
            } else if ($this->channel == Transaction::CHANNEL_ALIPAY) {
                $config = [
                    'out_biz_no' => $this->id,
                    'payee_type' => $this->extra['recipient_account_type'],
                    'payee_account' => $this->recipient_id,
                    'amount' => bcdiv($this->amount, 100, 2),
                    'remark' => $this->description,
                ];
                if (isset($this->extra['recipient_name'])) {
                    $config['payee_real_name'] = $this->extra['recipient_name'];
                }
                try {
                    $response = $channel->transfer($config);
                    $this->setPaid($response->payment_no, $response);
                } catch (\Exception $exception) {//设置提现失败
                    $this->setFailure('FAIL', $exception->getMessage());
                }
            }
        }
        return $this;
    }
}
