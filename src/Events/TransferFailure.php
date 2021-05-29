<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 * @license http://www.larva.com.cn/license/
 */

declare (strict_types = 1);

namespace Larva\Transaction\Events;

use Illuminate\Queue\SerializesModels;
use Larva\Transaction\Models\Transfer;

/**
 * 企业付款失败事件
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class TransferFailure
{
    use SerializesModels;

    /**
     * @var Transfer
     */
    public $transfer;

    /**
     * TransferShipped constructor.
     * @param Transfer $transfer
     */
    public function __construct(Transfer $transfer)
    {
        $this->transfer = $transfer;
    }
}