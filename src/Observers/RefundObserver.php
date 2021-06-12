<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */

declare (strict_types = 1);

namespace Larva\Transaction\Observers;

use Larva\Transaction\Models\Refund;

/**
 * 退款模型观察者
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class RefundObserver
{
    /**
     * Handle the refund "created" event.
     *
     * @param Refund $refund
     * @return void
     * @throws \Exception
     */
    public function created(Refund $refund)
    {
        $refund->send();
    }
}