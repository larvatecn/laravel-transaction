<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */

declare (strict_types = 1);

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
    public $charge;

    /**
     * ChargeFailure constructor.
     * @param Charge $charge
     */
    public function __construct(Charge $charge)
    {
        $this->charge = $charge;
    }
}