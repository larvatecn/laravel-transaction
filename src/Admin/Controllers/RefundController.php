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
use Illuminate\Support\Carbon;
use Larva\Transaction\Models\Refund;

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

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid(): Grid
    {
        return Grid::make(new Refund(), function (Grid $grid) {
            $grid->filter(function (Grid\Filter $filter) {
                //右侧搜索
                $filter->equal('id', '流水号');
                $filter->equal('client_ip', '交易IP');
                $filter->between('created_at', '交易时间')->date();

                //顶部筛选
                $filter->scope('today', '今天数据')->whereDay('created_at', Carbon::today());
                $filter->scope('yesterday', '昨天数据')->whereDay('created_at', Carbon::yesterday());
                $thisWeek = [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()];
                $filter->scope('this_week', '本周数据')
                    ->whereBetween('created_at', $thisWeek);
                $lastWeek = [Carbon::now()->startOfWeek()->subWeek(), Carbon::now()->endOfWeek()->subWeek()];
                $filter->scope('last_week', '上周数据')->whereBetween('created_at', $lastWeek);
                $filter->scope('this_month', '本月数据')->whereMonth('created_at', Carbon::now()->month);
                $filter->scope('last_month', '上月数据')->whereBetween('created_at', [Carbon::now()->subMonth()->startOfDay(), Carbon::now()->subMonth()->endOfDay()]);
                $filter->scope('year', '本年数据')->whereYear('created_at', Carbon::now()->year);
            });
            $grid->quickSearch(['id', 'transaction_no']);
            $grid->model()->orderBy('id', 'desc');

            $grid->column('id', '流水号')->sortable();
            $grid->column('charge_id', '付款单号');
            $grid->column('transaction_no', '网关流水号');
            $grid->column('amount', '金额')->display(function ($amount) {
                return (string)($amount / 100) . '元';
            });
            $grid->column('status', '状态')->using(Refund::getStatusMaps())->dot(Refund::getStateDots());
            $grid->column('reason', '描述');
            $grid->column('succeed_at', '成功时间')->sortable();
            $grid->column('created_at', '创建时间')->sortable();
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->paginate(10);
        });
    }
}
