<?php
/**
 * @copyright Copyright (c) 2018 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larvacent.com/
 * @license http://www.larvacent.com/license/
 */

declare (strict_types = 1);

namespace Larva\Transaction\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Larva\Transaction\Models\Charge;

/**
 * 检查付款单是否过期
 *
 * @author Tongle Xu <xutongle@gmail.com>
 */
class CheckChargeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 任务可以尝试的最大次数。
     *
     * @var int
     */
    public $tries = 3;

    /**
     * @var Charge
     */
    protected $charge;

    /**
     * Create a new job instance.
     *
     * @param Charge $charge
     */
    public function __construct(Charge $charge)
    {
        $this->charge = $charge;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        if (!$this->charge->paid) {
            if (Carbon::parse($this->charge->time_expire)->diffInSeconds(Carbon::now()) < 0) {//过期订单直接关闭
                $this->charge->setClose();
            } else {//过一会再次查询
                $this->release(2);
            }
        }
    }
}