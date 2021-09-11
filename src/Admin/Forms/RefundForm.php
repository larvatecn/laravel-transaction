<?php

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */

namespace Larva\Transaction\Admin\Forms;

use Dcat\Admin\Contracts\LazyRenderable;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Widgets\Form;
use Larva\Transaction\Models\Charge;

/**
 * 退款表单
 * @author Tongle Xu <xutongle@gmail.com>
 */
class RefundForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function form()
    {
        $this->textarea('reason', '理由')->rows(3)->required()->rules('string')->placeholder('请输入理由！');
    }

    /**
     * Handle the form request.
     *
     * @param array $input
     *
     * @return \Dcat\Admin\Http\JsonResponse
     */
    public function handle(array $input)
    {
        // 获取外部传递参数
        $id = $this->payload['id'] ?? null;
        Charge::findOrFail($id)->refund($input['reason']);
        return $this->response()->success('已受理！')->refresh();
    }
}
