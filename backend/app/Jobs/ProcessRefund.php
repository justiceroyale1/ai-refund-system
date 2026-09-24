<?php

namespace App\Jobs;

use App\Services\Refunds\RefundProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessRefund implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const string QUEUE = 'refunds';

    public const int TIMEOUT_SECONDS = 30;

    public int $tries = 1;

    public int $timeout = self::TIMEOUT_SECONDS;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $refundId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return (string) $this->refundId;
    }

    /**
     * Execute the job.
     */
    public function handle(RefundProcessor $processor): void
    {
        $processor->process($this->refundId);
    }
}
