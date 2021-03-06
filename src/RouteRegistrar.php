<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 * @license http://www.larva.com.cn/license/
 */

namespace Larva\Transaction;

use Illuminate\Contracts\Routing\Registrar as Router;

/**
 * Class RouteRegistrar
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class RouteRegistrar
{
    /**
     * The router implementation.
     *
     * @var \Illuminate\Contracts\Routing\Registrar
     */
    protected $router;

    /**
     * Create a new route registrar instance.
     *
     * @param \Illuminate\Contracts\Routing\Registrar $router
     * @return void
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Register routes for transient tokens, clients, and personal access tokens.
     *
     * @return void
     */
    public function all()
    {
        $this->forNotify();
    }

    /**
     * Register the routes needed for notify.
     *
     * @return void
     */
    public function forNotify()
    {
        $this->router->match(['get', 'post'], 'callback/charge/{channel}', [//支付回调
            'uses' => 'PaymentController@paymentCallback',
            'as' => 'transaction.callback.charge',
        ]);

        $this->router->match(['get'], 'callback/charge/{id}', [//支付回调(一般用于扫码付)
            'uses' => 'PaymentController@paymentSuccess',
            'as' => 'transaction.success.charge',
        ]);

        $this->router->match(['get'], 'charge/{id}', [//支付状态查询
            'uses' => 'PaymentController@query',
            'as' => 'transaction.query.charge',
        ]);

        $this->router->match(['get', 'post'], 'notify/charge/{channel}', [//支付通知
            'uses' => 'NotifyController@charge',
            'as' => 'transaction.notify.charge',
        ]);

        $this->router->match(['get', 'post'], 'notify/refund/{channel}', [//退款通知
            'uses' => 'NotifyController@refund',
            'as' => 'transaction.notify.refund',
        ]);
    }

}