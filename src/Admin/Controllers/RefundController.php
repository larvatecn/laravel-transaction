<?php

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */
namespace Larva\Transaction\Admin\Controllers;

use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Grid;

/**
 * 退款单
 * @author Tongle Xu <xutongle@gmail.com>
 */
class RefundController extends AdminController
{
    /**
     * Get content title.
     *
     * @return string
     */
    protected function title(): string
    {
        return '退款单';
    }
}
