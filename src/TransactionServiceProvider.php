<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 * @license http://www.larva.com.cn/license/
 */

declare (strict_types = 1);

namespace Larva\Transaction;

use Illuminate\Support\ServiceProvider;

/**
 * Class TransactionServiceProvider
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class TransactionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => base_path('resources/views/vendor/transaction'),
            ], 'transaction-views');
            $this->publishes([
                __DIR__ . '/../config/transaction.php' => config_path('transaction.php'),],
                'transaction-config'
            );
        }

        \Larva\Transaction\Models\Charge::observe(\Larva\Transaction\Observers\ChargeObserver::class);
        \Larva\Transaction\Models\Refund::observe(\Larva\Transaction\Observers\RefundObserver::class);
        \Larva\Transaction\Models\Transfer::observe(\Larva\Transaction\Observers\TransferObserver::class);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

    }
}