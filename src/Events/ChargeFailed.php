<?php

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 */
namespace Larva\Transaction\Events;

use Illuminate\Queue\SerializesModels;
use Larva\Transaction\Models\Charge;

/**
 * 交易失败
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class ChargeFailed
{
    use SerializesModels;

    /**
     * @var Charge
     */
    public Charge $charge;

    /**
     * ChargeFailure constructor.
     * @param Charge $charge
     */
    public function __construct(Charge $charge)
    {
        $this->charge = $charge;
    }
}
