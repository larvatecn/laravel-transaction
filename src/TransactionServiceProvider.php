<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 */

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 */

namespace Larva\Transaction;

use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use Yansongda\Pay\Pay;

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
            ], 'laravel-assets');
            $this->publishes(
                [
                __DIR__ . '/../config/transaction.php' => config_path('transaction.php'),],
                'transaction-config'
            );
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Yansongda\Pay\Exception\ContainerDependencyException
     * @throws \Yansongda\Pay\Exception\ContainerException
     * @throws \Yansongda\Pay\Exception\ServiceNotFoundException
     */
    public function register()
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/transaction.php', 'transaction');

        Pay::config(Container::getInstance()->make('config')->get('transaction'));

        $this->app->singleton('transaction.alipay', function () {
            return Pay::alipay();
        });

        $this->app->singleton('transaction.wechat', function () {
            return Pay::wechat();
        });
    }

    /**
     * Get services.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['transaction.alipay', 'transaction.wechat'];
    }
}
