<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 * @license http://www.larva.com.cn/license/
 */

declare (strict_types = 1);

namespace Larva\Transaction;

use Exception;
use Illuminate\Support\Facades\Route;
use Larva\Transaction\Models\Charge;
use Larva\Transaction\Models\Refund;
use Larva\Transaction\Models\Transfer;
use Yansongda\Pay\Pay;

/**
 * Class Transaction
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class Transaction
{
    //支持的交易通道
    const CHANNEL_WECHAT = 'wechat';
    const CHANNEL_ALIPAY = 'alipay';

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
     * @return \Yansongda\Pay\Gateways\Alipay|\Yansongda\Pay\Gateways\Wechat
     * @throws Exception
     */
    public static function getChannel(string $channel)
    {
        if ($channel == static::CHANNEL_WECHAT) {
            return Pay::wechat(config('transaction.wechat'));
        } else if ($channel == static::CHANNEL_ALIPAY) {
            return Pay::alipay(config('transaction.alipay'));
        } else {
            throw new Exception ('The channel does not exist.');
        }
    }

    /**
     * 获取付款单
     * @param string $id
     * @return Charge|null
     */
    public static function getCharge(string $id): ?Charge
    {
        return Charge::where('id', $id)->first();
    }

    /**
     * 获取退款单
     * @param string $id
     * @return Refund|null
     */
    public static function getRefund(string $id): ?Refund
    {
        return Refund::where('id', $id)->first();
    }

    /**
     * 获取企业付款
     * @param string $id
     * @return Transfer|null
     */
    public static function getTransfer(string $id): ?Transfer
    {
        return Transfer::where('id', $id)->first();
    }

}