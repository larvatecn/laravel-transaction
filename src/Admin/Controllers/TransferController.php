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
use Larva\Transaction\Models\Transfer;

/**
 * 提现单
 * @author Tongle Xu <xutongle@gmail.com>
 */
class TransferController extends AdminController
{
    /**
     * Get content title.
     *
     * @return string
     */
    protected function title(): string
    {
        return '付款单';
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid(): Grid
    {
        return Grid::make(new Transfer(), function (Grid $grid) {
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id', '流水号');
                $filter->equal('order_id', '申请流水号');
                $filter->equal('transaction_no', '网关流水号');
            });

            $grid->quickSearch(['id', 'transaction_no', 'order_id']);
            $grid->model()->orderBy('id', 'desc');
            $grid->column('id', '流水号')->sortable();
            $grid->column('transaction_no', '网关流水号');
        });
    }
}
