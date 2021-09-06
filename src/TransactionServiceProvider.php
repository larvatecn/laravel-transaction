<?php
/**
 * This is NOT a freeware, use is subject to license terms
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
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
     */
    public function register()
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/transaction.php', 'transaction');

        $this->app->singleton('transaction.alipay', function () {
            return Pay::alipay(Container::getInstance()->make('config')->get('transaction.alipay'));
        });

        $this->app->singleton('transaction.wechat', function () {
            return Pay::wechat(Container::getInstance()->make('config')->get('transaction.wechat'));
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
