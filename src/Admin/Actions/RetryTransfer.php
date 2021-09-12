<?php

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */

namespace Larva\Transaction\Admin\Actions;

use Dcat\Admin\Actions\Response;
use Dcat\Admin\Grid\RowAction;
use Dcat\Admin\Traits\HasPermissions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Larva\Transaction\Jobs\HandleTransferJob;
use Larva\Transaction\Models\Transfer;

/**
 * 付款重试
 * @author Tongle Xu <xutongle@gmail.com>
 */
class RetryTransfer extends RowAction
{
    /**
     * @return string
     */
    protected $title = '重试';

    /**
     * Handle the action request.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function handle(Request $request)
    {
        $key = $this->getKey();
        $transfer = Transfer::findOrFail($key);
        HandleTransferJob::dispatch($transfer)->delay(1);
        return $this->response()->success('已重试！')->refresh();
    }

    /**
     * @return string
     */
    public function render(): string
    {
        if ($this->row->status != Transfer::STATUS_PENDING || $this->row->status != Transfer::STATUS_ABNORMAL) {
            $this->disable();
        }
        return parent::render();
    }

    /**
     * 弹窗询问
     * @return string[]
     */
    public function confirm(): array
    {
        return ['确定重试吗？'];
    }

    /**
     * @param Model|Authenticatable|HasPermissions|null $user
     *
     * @return bool
     */
    protected function authorize($user): bool
    {
        return true;
    }

    /**
     * @return array
     */
    protected function parameters(): array
    {
        return [

        ];
    }
}