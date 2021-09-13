<?php

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */
namespace Larva\Transaction\Admin\Controllers;

use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Dcat\Admin\Http\Controllers\AdminController;
use Illuminate\Support\Carbon;
use Larva\Transaction\Admin\Actions\ChargeRefund;
use Larva\Transaction\Models\Charge;
use Larva\Transaction\Transaction;

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
                //右侧搜索
                $filter->equal('id', '流水号');
                $filter->equal('user_id', '用户ID');
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
            $grid->quickSearch(['id', 'transaction_no', 'order_id']);
            $grid->model()->orderBy('id', 'desc');
            $grid->column('id', '流水号')->sortable();
            $grid->column('order_id', '订单号');
            $grid->column('currency', '币种');
            $grid->column('total_amount', '收款金额')->display(function ($total_amount) {
                return (string)($total_amount / 100) . '元';
            });
            $grid->column('refunded_amount', '已退金额')->display(function ($refunded_amount) {
                return (string)($refunded_amount / 100) . '元';
            });
            $grid->column('state', '状态')
                ->using(Charge::getStateMaps())
                ->dot(Charge::getStateDots())
                ->filter(Grid\Column\Filter\In::make(Charge::getStateMaps()));
            $grid->column('trade_channel', '收款渠道')
                ->using(Transaction::getChannelMaps())
                ->filter(Grid\Column\Filter\In::make(Transaction::getChannelMaps()));
            $grid->column('trade_type', '收款类型')
                ->using(Transaction::getTradeTypeMaps())
                ->filter(Grid\Column\Filter\In::make(Transaction::getTradeTypeMaps()));
            $grid->column('subject', '收款主题');
            $grid->column('created_at', '创建时间')->sortable();
            $grid->column('succeed_at', '付款时间')->sortable();
            $grid->disableCreateButton();
            $grid->disableEditButton();
            $grid->disableDeleteButton();
            $grid->paginate(10);
        });
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected function detail($id): Show
    {
        return Show::make($id, Charge::query(), function (Show $show) {
            $show->row(function (Show\Row $show) {
                $show->width(4)->field('id', '收款单号');
                $show->width(4)->field('order_id', '订单号');
                $show->width(4)->field('transaction_no', '网关流水号');
            });
            $show->row(function (Show\Row $show) {
                $show->width(4)->field('trade_channel', '收款渠道')->using(Transaction::getChannelMaps());
                $show->width(4)->field('trade_type', '收款类型')->using(Transaction::getTradeTypeMaps());
                $show->width(4)->field('state', '付款状态')->using(Charge::getStateMaps())->dot(Charge::getStateDots());
            });
            $show->row(function (Show\Row $show) {
                $show->width(4)->field('currency', '币种');
                $show->width(4)->field('total_amount', '收款金额')->as(function ($total_amount) {
                    return (string)($total_amount / 100) . '元';
                });
                $show->width(4)->field('refunded_amount', '已退金额')->as(function ($refunded_amount) {
                    return (string)($refunded_amount / 100) . '元';
                });
            });
            $show->row(function (Show\Row $show) {
                $show->width(4)->field('expired_at', '过期时间');
                $show->width(4)->field('succeed_at', '付款时间');
                $show->width(4)->field('created_at', '创建时间');
            });

            $show->disableEditButton();
            $show->disableDeleteButton();

            $show->tools(function (Show\Tools $tools) use ($show) {
                if ($show->model()->state == Charge::STATE_SUCCESS) {
                    $tools->prepend(new ChargeRefund());
                }
            });
        });
    }
}
