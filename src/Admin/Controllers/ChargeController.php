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
use Larva\Transaction\Models\Charge;

/**
 * 付款单
 * @author Tongle Xu <xutongle@gmail.com>
 */
class ChargeController extends AdminController
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
        return Grid::make(new Charge(), function (Grid $grid) {
            $grid->filter(function (Grid\Filter $filter) {
                $filter->equal('id', '流水号');
                $filter->equal('order_id', '订单号');
                $filter->equal('transaction_no', '网关流水号');
            });
            $grid->quickSearch(['id', 'transaction_no', 'order_id']);
            $grid->model()->orderBy('id', 'desc');
            $grid->column('id', '流水号')->sortable();
            $grid->column('trade_channel', '支付渠道');
            $grid->column('trade_type', '支付类型');
            $grid->column('transaction_no', '网关流水号');
            $grid->column('order_id', '订单号');
            $grid->column('amount', '支付金额')->display(function ($amount) {
                return ($amount / 100) . '元';
            });
            $grid->column('score', '积分数量');
            $grid->column('state', '状态')->using(Charge::getStateMaps());
            $grid->column('client_ip', '客户端IP');
            $grid->column('succeed_at', '成功时间');
            $grid->column('expired_at', '过期时间');

            $grid->column('created_at', '创建时间')->sortable();

            $grid->disableCreateButton();
            $grid->disableViewButton();
            $grid->disableEditButton();
            $grid->disableDeleteButton();
        });
    }
}
