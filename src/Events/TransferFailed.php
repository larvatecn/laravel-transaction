<?php

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 */
namespace Larva\Transaction\Events;

use Illuminate\Queue\SerializesModels;
use Larva\Transaction\Models\Transfer;

/**
 * 企业付款失败事件
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class TransferFailed
{
    use SerializesModels;

    /**
     * @var Transfer
     */
    public Transfer $transfer;

    /**
     * TransferShipped constructor.
     * @param Transfer $transfer
     */
    public function __construct(Transfer $transfer)
    {
        $this->transfer = $transfer;
    }
}
