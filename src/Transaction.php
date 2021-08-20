<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */

declare (strict_types = 1);

namespace Larva\Transaction;

use Exception;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Larva\Transaction\Models\Charge;
use Larva\Transaction\Models\Refund;
use Larva\Transaction\Models\Transfer;
use Yansongda\Pay\Pay;
use Yansongda\Pay\Provider\AbstractProvider;
use Yansongda\Pay\Provider\Alipay;
use Yansongda\Pay\Provider\Wechat;

/**
 * Class Transaction
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Transaction extends Facade
{
    //支持的交易通道
    const CHANNEL_WECHAT = 'wechat';
    const CHANNEL_ALIPAY = 'alipay';

    /**
     * Return the facade accessor.
     *
     * @return string
     */
    public static function getFacadeAccessor(): string
    {
        return 'pay.alipay';
    }

    /**
     * Return the facade accessor.
     *
     * @return Alipay
     */
    public static function alipay(): Alipay
    {
        return app('pay.alipay');
    }

    /**
     * Return the facade accessor.
     *
     * @return Wechat
     */
    public static function wechat(): Wechat
    {
        return app('pay.wechat');
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