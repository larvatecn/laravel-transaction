<?php

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 */
namespace Larva\Transaction\Events;

use Illuminate\Queue\SerializesModels;
use Larva\Transaction\Models\Refund;

/**
 * 退款失败事件
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class RefundFailed
{
    use SerializesModels;

    /**
     * @var Refund
     */
    public Refund $refund;

    /**
     * RefundFailure constructor.
     * @param Refund $refund
     */
    public function __construct(Refund $refund)
    {
        $this->refund = $refund;
    }
}
