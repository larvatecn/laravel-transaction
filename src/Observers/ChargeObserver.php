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
namespace Larva\Transaction\Observers;

use Larva\Transaction\Models\Charge;

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
     */
    public function created(Charge $charge)
    {
        if (!empty($charge->trade_channel) && !empty($charge->trade_type)) {//不为空就预下单
            $charge->prePay();
        }
    }
}
