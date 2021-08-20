<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan JiYuan Information Technology Co., Ltd.
 * @link https://www.yaoqiyuan.com/
 */

namespace Larva\Transaction\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Failure implements CastsAttributes
{
    /**
     * 默认值
     * @var array
     */
    protected array $defaultValue = [
        'code' => null,
        'desc' => null,
    ];

    /**
     * Cast the given value.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return array
     */
    public function get($model, $key, $value, $attributes): array
    {
        $value = json_decode($value, true);
        return array_merge($this->defaultValue, is_array($value) ? $value : []);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return mixed
     */
    public function set($model, $key, $value, $attributes)
    {
        return json_encode(array_merge($this->defaultValue, is_array($value) ? $value : []));
    }
}