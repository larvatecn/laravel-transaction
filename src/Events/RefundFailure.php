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
use Larva\Transaction\Models\Refund;

/**
 * 退款失败事件
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class RefundFailure
{
    use SerializesModels;

    /**
     * @var Refund
     */
    public $refund;

    /**
     * RefundFailure constructor.
     * @param Refund $refund
     */
    public function __construct(Refund $refund)
    {
        $this->refund = $refund;
    }
}