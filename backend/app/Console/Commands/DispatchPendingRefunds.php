<?php

namespace App\Console\Commands;

use App\Jobs\ProcessRefund;
use App\Models\Refund;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('refunds:dispatch {--limit=100 : Maximum number of eligible refunds to inspect}')]
#[Description('Dispatch eligible pending refunds to the dedicated refund queue')]
class DispatchPendingRefunds extends Command
{
    private const int MAXIMUM_LIMIT = 1000;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => self::MAXIMUM_LIMIT,
            ],
        ]);

        if ($limit === false) {
            $this->components->error(sprintf(
                'The --limit option must be an integer between 1 and %d.',
                self::MAXIMUM_LIMIT,
            ));

            return self::INVALID;
        }

        $refunds = Refund::query()
            ->eligibleForDispatch()
            ->orderBy('id')
            ->limit($limit)
            ->get(['id']);

        foreach ($refunds as $refund) {
            ProcessRefund::dispatch($refund->id);
        }

        $this->components->info(sprintf(
            'Considered %d eligible refund(s) for dispatch.',
            $refunds->count(),
        ));

        return self::SUCCESS;
    }
}
