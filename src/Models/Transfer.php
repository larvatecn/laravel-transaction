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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Event;
use Larva\Transaction\Casts\Failure;
use Larva\Transaction\Events\TransferFailed;
use Larva\Transaction\Events\TransferSucceeded;
use Larva\Transaction\Jobs\HandleTransferJob;
use Larva\Transaction\Transaction;

/**
 * 企业付款模型，处理提现
 *
 * @property string $id 付款单ID
 * @property string $channel 付款渠道
 * @property string $status 状态
 * @property int $amount 金额
 * @property string $currency 币种
 * @property string $description 描述
 * @property string $transaction_no 网关交易号
 * @property array $failure 失败信息
 * @property array $recipient 接收者
 * @property array $extra 扩展数据
 * @property CarbonInterface|null $succeed_at 交易成功时间
 * @property CarbonInterface|null $deleted_at 软删除时间
 * @property CarbonInterface $created_at 创建时间
 * @property CarbonInterface $updated_at 更新时间
 *
 * @property-read boolean $succeed
 *
 * @property Model $order
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Transfer extends Model
{
    use SoftDeletes, Traits\DateTimeFormatter, Traits\UsingTimestampAsPrimaryKey;

    //付款状态
    public const STATUS_PENDING = 'PENDING';//待处理
    public const STATUS_SUCCESS = 'SUCCESS';//成功
    public const STATUS_ABNORMAL = 'ABNORMAL';//异常

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction_transfer';

    /**
     * @var bool 关闭主键自增
     */
    public $incrementing = false;

    /**
     * @var array 批量赋值属性
     */
    public $fillable = [
        'id', 'channel', 'status', 'amount', 'currency', 'description', 'transaction_no',
        'failure', 'recipient', 'extra', 'succeed_at'
    ];

    /**
     * 这个属性应该被转换为原生类型.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'int',
        'channel' => 'string',
        'status' => 'string',
        'amount' => 'int',
        'currency' => 'string',
        'description' => 'string',
        'transaction_no' => 'string',
        'failure' => Failure::class,
        'recipient' => 'array',
        'extra' => 'array'
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
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function booted()
    {
        static::creating(function (Transfer $model) {
            $model->id = $model->generateKey();
            $model->currency = $model->currency ?: 'CNY';
            $model->status = static::STATUS_PENDING;
        });
        static::created(function (Transfer $model) {
            HandleTransferJob::dispatch($model)->delay(1);
        });
    }

    /**
     * 多态关联
     * @return MorphTo
     */
    public function order(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 是否已付款
     * @return bool
     */
    public function getSucceedAttribute(): bool
    {
        return $this->status == self::STATUS_SUCCESS;
    }

    /**
     * 设置已付款
     * @param string $transactionNo
     * @param array $params
     * @return bool
     */
    public function markSucceeded(string $transactionNo, array $params = []): bool
    {
        if ($this->succeed) {
            return true;
        }
        $state = $this->update([
            'transaction_no' => $transactionNo,
            'transferred_at' => $this->freshTimestamp(),
            'status' => static::STATUS_SUCCESS,
            'extra' => $params
        ]);
        Event::dispatch(new TransferSucceeded($this));
        return $state;
    }

    /**
     * 设置提现错误
     * @param string $code
     * @param string $desc
     * @return bool
     */
    public function markFailed(string $code, string $desc): bool
    {
        $res = $this->update(['status' => self::STATUS_ABNORMAL, 'failure' => ['code' => $code, 'desc' => $desc]]);
        Event::dispatch(new TransferFailed($this));
        return $res;
    }

    /**
     * 主动发送付款请求到网关
     * @return Transfer
     * @throws Exception
     */
    public function gatewayHandle(): Transfer
    {
        $channel = Transaction::getGateway($this->channel);
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
                $this->markSucceeded($response->payment_no, $response->toArray());
            } catch (Exception $exception) {//设置付款失败
                $this->markFailed('FAIL', $exception->getMessage());
            }
        } elseif ($this->channel == Transaction::CHANNEL_ALIPAY) {
            $config = [
                'out_biz_no' => $this->id,
                'payee_type' => $this->extra['recipient_account_type'],
                'payee_account' => $this->recipient_id,
                'amount' => $this->amount / 100,
                'remark' => $this->description,
            ];
            if (isset($this->extra['recipient_name'])) {
                $config['payee_real_name'] = $this->extra['recipient_name'];
            }
            try {
                $response = $channel->transfer($config);
                $this->markSucceeded($response->payment_no, $response->toArray());
            } catch (Exception $exception) {//设置提现失败
                $this->markFailed('FAIL', $exception->getMessage());
            }
        }
        return $this;
    }
}
