<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */

declare (strict_types = 1);

namespace Larva\Transaction\Observers;

use Larva\Transaction\Models\Transfer;

/**
 * 企业付款模型观察者
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class TransferObserver
{
    /**
     * Handle the user "created" event.
     *
     * @param Transfer $transfer
     * @return void
     * @throws \Exception
     */
    public function created(Transfer $transfer)
    {
        $transfer->send();
    }
}