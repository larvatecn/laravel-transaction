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
namespace Larva\Transaction;

use Exception;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Yansongda\Pay\Gateways\Alipay;
use Yansongda\Pay\Gateways\Wechat;

/**
 * Class Transaction
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Transaction extends Facade
{
    //支持的交易通道
    public const CHANNEL_WECHAT = 'wechat';
    public const CHANNEL_ALIPAY = 'alipay';

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
     * Binds the Transaction routes into the controller.
     *
     * @param callable|null $callback
     * @param array $options
     * @return void
     */
    public static function routes($callback = null, array $options = [])
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

    /**
     * 获取交易网关
     * @param string $channel
     * @return Alipay|Wechat
     * @throws Exception
     */
    public static function getChannel(string $channel)
    {
        if ($channel == static::CHANNEL_WECHAT) {
            return static::wechat();
        } elseif ($channel == static::CHANNEL_ALIPAY) {
            return static::alipay();
        } else {
            throw new Exception('The channel does not exist.');
        }
    }
}
