<?php

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 */
namespace Larva\Transaction\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Larva\Transaction\Models\Refund;

class HandleRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Refund
     */
    protected Refund $refund;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Refund $refund)
    {
        $this->refund = $refund->withoutRelations();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->refund->status == Refund::STATUS_PENDING) {
            $this->refund->gatewayHandle();
        }
    }
}
