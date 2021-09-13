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
use Larva\Transaction\Models\Charge;

class ChargeClose extends RowAction
{
    /**
     * @return string
     */
    protected $title = '关闭';

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
        Charge::findOrFail($key)->close();
        return $this->response()->success('已关闭！')->refresh();
    }

    /**
     * @return string
     */
    public function render()
    {
        if ($this->row->state != Charge::STATE_NOTPAY) {
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
        return ['确定关闭吗？'];
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
