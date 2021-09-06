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
namespace Larva\Transaction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Larva\Transaction\Transaction;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Yansongda\Pay\Exceptions\InvalidArgumentException;

/**
 * 通知回调
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class NotifyController
{
    /**
     * 付款通知回调
     * @param Request $request
     * @param string $channel 回调的渠道
     * @return Response
     */
    public function charge(Request $request, string $channel): Response
    {
        try {
            $pay = Transaction::getGateway($channel);
            if ($channel == Transaction::CHANNEL_WECHAT) {
                $params = $pay->verify($request->getContent());
                if ($params['return_code'] == 'SUCCESS' && $params['result_code'] == 'SUCCESS') {
                    $charge = Transaction::getCharge($params['out_trade_no']);
                    $charge->markSucceeded($params['transaction_id'], $params->toArray());//入账
                    return $pay->success();
                }
                Log::debug('Wechat notify', $params->all());
            } elseif ($channel == Transaction::CHANNEL_ALIPAY) {
                $params = $pay->verify(); // 验签
                if ($params['trade_status'] == 'TRADE_SUCCESS' || $params['trade_status'] == 'TRADE_FINISHED') {
                    $charge = Transaction::getCharge($params['out_trade_no']);
                    $charge->markSucceeded($params['trade_no'], $params->toArray());
                    return $pay->success();
                }
                Log::debug('Alipay notify', $params->all());
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage(), $e->getTrace());
        }
        throw new NotFoundHttpException('Resource does not exist.');
    }

    /**
     * 退款通知回调
     * @param Request $request
     * @param string $channel 回调的渠道
     * @return Response
     */
    public function refund(Request $request, string $channel): Response
    {
        try {
            $pay = Transaction::getGateway($channel);
            $params = $pay->verify($request->getContent(), true); // 验签
            if ($channel == Transaction::CHANNEL_WECHAT) {
                if ($params['refund_status'] == 'SUCCESS') {//入账
                    $refund = Transaction::getRefund($params['out_refund_no']);
                    if ($refund) {
                        $refund->markSucceeded($params['success_time'], $params->toArray());
                        return $pay->success();
                    }
                }
                Log::debug('Wechat refund notify', $params->all());
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        throw new NotFoundHttpException('Resource does not exist.');
    }
}
