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
use Psr\Http\Message\ResponseInterface;

/**
 * 通知回调
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class NotifyController
{
    /**
     * 微信通知
     * @param Request $request
     * @return ResponseInterface
     */
    public function wechat(Request $request): ResponseInterface
    {
        $pay = Transaction::wechat();
        $result = $pay->callback();
        if ($result->event_type == 'TRANSACTION.SUCCESS' && $result->resource_type == 'encrypt-resource') {//付款成功
            $charge = Transaction::getCharge($result->resource['ciphertext']['out_trade_no']);
            if ($charge) {
                $charge->markSucceeded($result->resource['ciphertext']['transaction_id'], $result->toArray());
            }
        } elseif ($result->event_type == 'REFUND.SUCCESS' && $result->resource_type == 'encrypt-resource') {//退款成功
            $refund = Transaction::getRefund($result->resource['ciphertext']['out_refund_no']);
            if ($refund) {
                $refund->markSucceeded($result->resource['ciphertext']['success_time'], $result->toArray());
            }
        } elseif ($result->event_type == 'REFUND.ABNORMAL' && $result->resource_type == 'encrypt-resource') {//退款异常通知
            $refund = Transaction::getRefund($result->resource['ciphertext']['out_refund_no']);
            if ($refund) {
                $refund->markFailed($result->resource['ciphertext']['refund_status'], $result->summary);
            }
        } elseif ($result->event_type == 'REFUND.CLOSED' && $result->resource_type == 'encrypt-resource') {//退款关闭通知
        }
        Log::debug('Wechat notify', $result->toArray());
        return $pay->success();
    }

    /**
     * 支付宝通知
     * @param Request $request
     * @return ResponseInterface
     */
    public function alipay(Request $request): ResponseInterface
    {
        $pay = Transaction::alipay();
        $result = $pay->callback();
//        if ($result['trade_status'] == 'TRADE_SUCCESS' || $result['trade_status'] == 'TRADE_FINISHED') {
//            $charge = Transaction::getCharge($result['out_trade_no']);
//            $charge->markSucceeded($result['trade_no']);
//        }
        Log::debug('alipay notify', $result->toArray());
        return $pay->success();
    }
}
