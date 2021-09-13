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

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Larva\Transaction\Models\Charge;
use Larva\Transaction\Transaction;

/**
 * 付款回调页面
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class PaymentController
{
    /**
     * The response factory implementation.
     *
     * @var ResponseFactory
     */
    protected $response;

    /**
     * CodeController constructor.
     * @param ResponseFactory $response
     */
    public function __construct(ResponseFactory $response)
    {
        $this->response = $response;
    }

    /**
     * 付款回调
     * @param string $channel
     */
    public function paymentCallback(string $channel)
    {
        try {
            $pay = Transaction::getChannel($channel);
            $params = $pay->verify(); // 验签
            $charge = null;
            if ($channel == Transaction::CHANNEL_ALIPAY) {
                if (isset($params['trade_status']) && ($params['trade_status'] == 'TRADE_SUCCESS' || $params['trade_status'] == 'TRADE_FINISHED')) {
                    $charge = Transaction::getCharge($params['out_trade_no']);
                    $charge->markSucceeded($params['trade_no']);
                    if ($charge->metadata['return_url']) {
                        $this->response->redirectTo($charge->metadata['return_url']);
                    }
                }
            }
            $this->response->view('transaction:return', ['charge' => $charge]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    /**
     * 扫码付成功回调
     * @param string $id
     * @return JsonResponse
     */
    public function paymentSuccess(string $id): JsonResponse
    {
        $charge = Transaction::getCharge($id);
        if ($charge && $charge->paid) {
            if ($charge->metadata['return_url']) {
                $this->response->redirectTo($charge->metadata['return_url']);
            }
            $this->response->view('transaction:return', ['charge' => $charge]);
        }
        throw (new ModelNotFoundException())->setModel(Charge::class, $id);
    }

    /**
     * 查询交易状态
     * @param string $id
     * @return JsonResponse
     */
    public function query(string $id): JsonResponse
    {
        $charge = Transaction::getCharge($id);
        if ($charge) {
            return $this->response->json($charge->toArray());
        }
        throw (new ModelNotFoundException())->setModel(Charge::class, $id);
    }
}
