<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 */

namespace Larva\Transaction\Http\Controllers;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Larva\Transaction\Transaction;

/**
 * 付款页面
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class ChargeController
{
    /**
     * The response factory implementation.
     *
     * @var ResponseFactory
     */
    protected ResponseFactory $response;

    /**
     * ChargeController constructor.
     * @param ResponseFactory $response
     */
    public function __construct(ResponseFactory $response)
    {
        $this->response = $response;
    }

    /**
     * 查询交易状态
     * @param string $id
     * @return JsonResponse
     */
    public function query(string $id): JsonResponse
    {
        $charge = Transaction::getCharge($id);
        return $this->response->json($charge->toArray());
    }
}
