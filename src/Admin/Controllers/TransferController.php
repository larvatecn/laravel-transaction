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
                $filter->equal('transaction_no', '网关流水号');
            });
            $grid->quickSearch(['id', 'transaction_no']);
            $grid->model()->orderBy('id', 'desc');
            $grid->column('id', '流水号')->sortable();
            $grid->column('transaction_no', '网关流水号');
            $grid->column('trade_channel', '付款渠道');
            $grid->column('amount', '付款金额')->display(function ($amount) {
                return ($amount / 100) . '元';
            });
            $grid->column('description', '备注');
            $grid->column('status', '状态')->using(Transfer::getStatusMaps());
            $grid->column('succeed_at', '成功时间');
            $grid->column('created_at', '创建时间')->sortable();
            $grid->disableCreateButton();
            $grid->disableViewButton();
            $grid->disableEditButton();
            $grid->disableDeleteButton();
        });
    }
}
