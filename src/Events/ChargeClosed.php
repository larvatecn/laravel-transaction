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

namespace Larva\Transaction\Events;

use Illuminate\Queue\SerializesModels;
use Larva\Transaction\Models\Charge;

/**
 * 交易已关闭
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class ChargeClosed
{
    use SerializesModels;

    /**
     * @var Charge
     */
    public Charge $charge;

    /**
     * ChargeClosed constructor.
     * @param Charge $charge
     */
    public function __construct(Charge $charge)
    {
        $this->charge = $charge;
    }
}
