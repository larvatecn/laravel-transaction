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
        return '收款单';
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
                $filter->equal('id');
            });
            $grid->quickSearch(['id']);
            $grid->model()->orderBy('id', 'desc');

            $grid->column('id', 'ID')->sortable();
            $grid->column('user_id', '用户ID');

            $grid->column('channel', '付款渠道');
            $grid->column('trade_type', '付款类型');

            $grid->column('amount', '付款金额')->display(function ($amount) {
                return ($amount / 100) . '元';
            });
            $grid->column('score', '积分数量');
            $grid->column('status', '状态')->using(Charge::getStatusLabels())->dot(Charge::getStatusDots(), 'info');
            $grid->column('client_ip', '客户端IP');
            $grid->column('succeeded_at', '成功时间');

            $grid->column('created_at', '创建时间')->sortable();

            $grid->disableCreateButton();
            $grid->disableViewButton();
            $grid->disableEditButton();
            $grid->disableDeleteButton();
        });
    }
}
