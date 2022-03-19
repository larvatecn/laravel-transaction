# laravel-transaction

<p align="center">
    <a href="https://packagist.org/packages/larva/laravel-transaction"><img src="https://poser.pugx.org/larva/laravel-transaction/v/stable" alt="Stable Version"></a>
    <a href="https://packagist.org/packages/larva/laravel-transaction"><img src="https://poser.pugx.org/larva/laravel-transaction/downloads" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/larva/laravel-transaction"><img src="https://poser.pugx.org/larva/laravel-transaction/license" alt="License"></a>
</p>

这是一个内部收单系统，依赖 yansongda/pay 这个组件，本收单系统，统一了调用。
备注，交易单位是分；2.x 和 3.x 版本对外接口一致，只是内部调用的第三方接口版本不同，本扩展拉齐了开发体验；如果你使用了 微信的 企业付款到钱包接口，
请使用 2.x,但是2.x对应的支付宝不支持最新版支付宝的公钥证书，只支持公钥；如果没有用到微信的 企业付款到钱包 等V3不支持的接口建议使用3.x;在
3.x 如果你调用了企业付款同时指定了微信，会直接回调你模型返回失败消息；
 
## 环境需求

- PHP ^7.4|^8.0

## 安装

```bash
# yansongda/pay 2.x
composer require "larva/laravel-transaction:^2.0"

# yansongda/pay 3.x
composer require "larva/laravel-transaction:^3.0"
```

事件
```php
\Larva\Transaction\Events\ChargeClosed 交易已关闭
\Larva\Transaction\Events\ChargeFailed 交易失败
\Larva\Transaction\Events\ChargeSucceeded 交易已支付
\Larva\Transaction\Events\RefundFailed 退款失败事件
\Larva\Transaction\Events\RefundSucceeded 退款成功事件
\Larva\Transaction\Events\TransferFailed 付款失败事件
\Larva\Transaction\Events\TransferSucceeded 付款成功事件
```

AppServiceProvider 的 boot 中注册 路由

```php
\Larva\Transaction\Transaction::routes();
```

在中间件 `App\Http\Middleware\VerifyCsrfToken` 排除支付回调相关的路由，如：

```php
protected $except = [
    // ...
    'transaction',
];
```

你自己的订单关联

```php
/**
 * @property Charge $change
 */
class Order extends Model {

    /**
     * Get the entity's charge.
     * 这里关联付款模型
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function charge()
    {
        return $this->morphOne(Charge::class, 'order');
    }

    /**
     * 设置交易成功
     */
    public function markSucceeded()
    {
        $this->update(['channel' => $this->charge->trade_channel, 'status' => static::STATUS_PAY_SUCCEEDED, 'succeeded_at' => $this->freshTimestamp()]);
    }

    /**
     * 设置交易失败
     */
    public function markFailed()
    {
        $this->update(['status' => static::STATUS_FAILED]);
    }

    /**
     * 发起退款
     * @param string $reason 退款描述
     * @return Model|Refund
     * @throws Exception
     */
    public function refund(string $reason)
    {
        if ($this->paid && $this->charge->allowRefund) {
            $refund = $this->charge->refund($reason);
            $this->update(['refunded' => true]);
            return $refund;
        }
        throw new Exception ('Not paid, no refund.');
    }
}
```
