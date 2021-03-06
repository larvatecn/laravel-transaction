<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 * @license http://www.larva.com.cn/license/
 */

namespace Larva\Transaction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Larva\Transaction\Transaction;

/**
 * 通知回调
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class NotifyController
{
    /**
     * 付款通知回调
     * @param \Illuminate\Http\Request $request
     * @param string $channel 回调的渠道
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Yansongda\Pay\Exceptions\InvalidArgumentException
     */
    public function charge(Request $request, $channel)
    {
        try {
            $pay = Transaction::getChannel($channel);
            if ($channel == Transaction::CHANNEL_WECHAT) {
                $params = $pay->verify($request->getContent()); // 验签
                if ($params['return_code'] == 'SUCCESS') {//入账
                    $charge = Transaction::getCharge($params['out_trade_no']);
                    $charge->setPaid($params['transaction_id']);
                }
                Log::debug('Wechat notify', $params->all());
            } else if ($channel == Transaction::CHANNEL_ALIPAY) {
                $params = $pay->verify(); // 验签
                if ($params['trade_status'] == 'TRADE_SUCCESS' || $params['trade_status'] == 'TRADE_FINISHED') {
                    $charge = Transaction::getCharge($params['out_trade_no']);
                    $charge->setPaid($params['trade_no']);
                }
                Log::debug('Alipay notify', $params->all());
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        return $pay->success();
    }

    /**
     * 退款通知回调
     * @param \Illuminate\Http\Request $request
     * @param string $channel 回调的渠道
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Yansongda\Pay\Exceptions\InvalidArgumentException
     */
    public function refund(Request $request, $channel)
    {
        try {
            $pay = Transaction::getChannel($channel);
            $params = $pay->verify($request->getContent(), true); // 验签
            if ($channel == Transaction::CHANNEL_WECHAT) {
                if ($params['refund_status'] == 'SUCCESS') {//入账
                    $refund = Transaction::getRefund($params['out_refund_no']);
                    $refund->setRefunded($params['success_time'], $params);
                }
                Log::debug('Wechat refund notify', $params->all());
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        return $pay->success();
    }
}