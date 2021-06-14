<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */

declare (strict_types = 1);

namespace Larva\Transaction\Observers;

use Larva\Transaction\Jobs\CheckChargeJob;
use Larva\Transaction\Models\Charge;
use Yansongda\Pay\Exceptions\InvalidGatewayException;

/**
 * 支付模型观察者
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class ChargeObserver
{
    /**
     * Handle the user "created" event.
     *
     * @param Charge $charge
     * @return void
     * @throws InvalidGatewayException
     */
    public function created(Charge $charge)
    {
        if (!empty($charge->channel) && !empty($charge->type)) {//不为空就预下单
            $charge->prePay();
        }
        if (!empty($charge->time_expire)) {//订单失效时间不为空
            CheckChargeJob::dispatch($charge)->delay(2);
        }
    }
}
