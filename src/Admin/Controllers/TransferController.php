<?php

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */
namespace App\Admin\Controllers\Transaction;

use Dcat\Admin\Grid;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Show;
use Illuminate\Support\Carbon;
use Larva\Transaction\Admin\Actions\RetryTransfer;
use Larva\Transaction\Models\Transfer;
use Larva\Transaction\Transaction;

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
            $grid->quickSearch(['id', 'transaction_no']);
            $grid->model()->orderBy('id', 'desc');
            $grid->column('id', '流水号')->sortable();
            $grid->column('succeed', '已支付')->bool();
            $grid->column('amount', '付款金额')->display(function ($amount) {
                return bcdiv($amount, 100, 2) . '元';
            });
            $grid->column('currency', '币种');
            $grid->column('description', '描述');
            $grid->column('trade_channel', '付款渠道');
            $grid->column('created_at', '创建时间')->sortable();
            $grid->column('succeed_at', '付款时间')->sortable();
            $grid->disableCreateButton();
            $grid->disableEditButton();
            $grid->disableDeleteButton();

            $grid->actions(function (Grid\Displayers\Actions $actions) use ($grid) {
                $actions->append(RetryTransfer::make());
            });
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
        return Show::make($id, Transfer::query(), function (Show $show) {
            $show->row(function (Show\Row $show) {
                $show->width(4)->field('id', '付款单号');
                $show->width(4)->field('charge_id', '付款单号');
                $show->width(4)->field('transaction_no', '网关流水号');
            });
            $show->row(function (Show\Row $show) {
                $show->width(4)->field('currency', '币种');
                $show->width(4)->field('amount', '付款金额')->as(function ($total_amount) {
                    return (string)($total_amount / 100) . '元';
                });
                $show->width(4)->field('description', '描述');
            });
            $show->row(function (Show\Row $show) {
                $show->width(4)->field('trade_channel', '付款渠道')->using(Transaction::getChannelMaps());
                $show->width(4)->field('status', '付款状态')->using(Transfer::getStatusMaps())->dot(Transfer::getStateDots());
                $show->width(4)->field('recipient', '收款人')->json();
            });
            $show->row(function (Show\Row $show) {
                $show->width(4)->field('succeed_at', '成功时间');
                $show->width(4)->field('created_at', '创建时间');
                $show->width(4)->field('failure', '失败信息')->json();
            });
            $show->row(function (Show\Row $show) {
                $show->width(8)->field('extra', '网关信息')->json();
            });
            $show->disableEditButton();
            $show->disableDeleteButton();
        });
    }
}
