<?php

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 */
namespace Larva\Transaction;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Larva\Transaction\Models\Charge;
use Larva\Transaction\Models\Refund;
use Larva\Transaction\Models\Transfer;
use Yansongda\Pay\Provider\Alipay;
use Yansongda\Pay\Provider\Wechat;

/**
 * 交易助手
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Transaction extends Facade
{
    //支持的交易通道
    public const CHANNEL_WECHAT = 'wechat';
    public const CHANNEL_ALIPAY = 'alipay';

    //交易类型
    public const TRADE_TYPE_WEB = 'web';
    public const TRADE_TYPE_WAP = 'wap';
    public const TRADE_TYPE_APP = 'app';
    public const TRADE_TYPE_POS = 'pos';
    public const TRADE_TYPE_SCAN = 'scan';
    public const TRADE_TYPE_MINI = 'mini';

    /**
     * Return the facade accessor.
     *
     * @return string
     */
    public static function getFacadeAccessor(): string
    {
        return 'transaction.alipay';
    }

    /**
     * Return the facade accessor.
     *
     * @return Alipay
     */
    public static function alipay(): Alipay
    {
        return app('transaction.alipay');
    }

    /**
     * Return the facade accessor.
     *
     * @return Wechat
     */
    public static function wechat(): Wechat
    {
        return app('transaction.wechat');
    }

    /**
     * 支持的网关
     *
     * @return string[]
     */
    public static function getChannelMaps(): array
    {
        return [
            static::CHANNEL_WECHAT => '微信',
            static::CHANNEL_ALIPAY => '支付宝',
        ];
    }

    /**
     * 支付类型
     *
     * @return string[]
     */
    public static function getTradeTypeMaps(): array
    {
        return [
            static::TRADE_TYPE_WEB => '电脑支付',
            static::TRADE_TYPE_WAP => '手机网站支付',
            static::TRADE_TYPE_APP => 'APP 支付',
            static::TRADE_TYPE_POS => '刷卡支付',
            static::TRADE_TYPE_SCAN => '扫码支付',
            static::TRADE_TYPE_MINI => '小程序支付',
        ];
    }

    /**
     * 获取交易网关
     * @param string $channel
     * @return Alipay|Wechat
     * @throws TransactionException
     */
    public static function getChannel(string $channel)
    {
        if ($channel == static::CHANNEL_WECHAT) {
            return static::wechat();
        } elseif ($channel == static::CHANNEL_ALIPAY) {
            return static::alipay();
        } else {
            throw new TransactionException('The channel does not exist.');
        }
    }

    /**
     * 获取收款单
     * @param string $id
     * @return Charge|null
     */
    public static function getCharge(string $id): ?Charge
    {
        return Charge::findOrFail($id);
    }

    /**
     * 获取退款单
     * @param string $id
     * @return Refund|null
     */
    public static function getRefund(string $id): ?Refund
    {
        return Refund::findOrFail($id);
    }

    /**
     * 获取付款单
     * @param string $id
     * @return Transfer|null
     */
    public static function getTransfer(string $id): ?Transfer
    {
        return Transfer::findOrFail($id);
    }

    /**
     * Binds the Transaction routes into the controller.
     *
     * @param callable|null $callback
     * @param array $options
     * @return void
     */
    public static function routes(callable $callback = null, array $options = [])
    {
        $callback = $callback ?: function ($router) {
            $router->all();
        };

        $defaultOptions = [
            'prefix' => 'transaction',
            'namespace' => '\Larva\Transaction\Http\Controllers',
        ];

        $options = array_merge($defaultOptions, $options);

        Route::group($options, function ($router) use ($callback) {
            $callback(new RouteRegistrar($router));
        });
    }
}
