<?php

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */

namespace Larva\Transaction\Http\Controllers;

use Illuminate\Contracts\Routing\ResponseFactory;
use Larva\Transaction\Transaction;

/**
 * 回调页面
 * @author Tongle Xu <xutongle@msn.com>
 */
class CallbackController
{
    /**
     * The response factory implementation.
     *
     * @var ResponseFactory
     */
    protected ResponseFactory $response;

    /**
     * CodeController constructor.
     * @param ResponseFactory $response
     */
    public function __construct(ResponseFactory $response)
    {
        $this->response = $response;
    }

    /**
     * 支付宝PC和手机付款的回调页面
     * @throws \Yansongda\Pay\Exception\ContainerDependencyException
     * @throws \Yansongda\Pay\Exception\ContainerException
     * @throws \Yansongda\Pay\Exception\InvalidParamsException
     * @throws \Yansongda\Pay\Exception\ServiceNotFoundException
     */
    public function alipay()
    {
        $pay = Transaction::alipay();
        $params = $pay->callback();
        $result = Transaction::alipay()->find(['out_trade_no' => $params['out_trade_no']]);
        if (isset($params['trade_status']) && ($params['trade_status'] == 'TRADE_SUCCESS' || $params['trade_status'] == 'TRADE_FINISHED')) {
            $charge = Transaction::getCharge($params['out_trade_no']);
            $charge->markSucceeded($params['trade_no'], $result->toArray());
            if ($charge->metadata['return_url']) {
                $this->response->redirectTo($charge->metadata['return_url']);
            }
        }
        $this->response->view('transaction:return', ['charge' => $charge ?? null]);
    }

    /**
     * 扫码付成功回调
     * @param string $id
     */
    public function scan(string $id)
    {
        $charge = Transaction::getCharge($id);
        if ($charge && $charge->paid) {
            if ($charge->metadata['return_url']) {
                $this->response->redirectTo($charge->metadata['return_url']);
            }
            $this->response->view('transaction:return', ['charge' => $charge]);
        }
    }
}
