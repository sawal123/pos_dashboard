<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Sync\SyncSequence;
use Illuminate\Console\Command;

class SyncResequenceCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:resequence-catalog {--business= : Specific business ID to resequence}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resequence catalog and master data with zero or missing sync sequences';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $businessId = $this->option('business');

        if ($businessId) {
            $business = Business::find((int) $businessId);
            if (! $business) {
                $this->error("Business with ID {$businessId} not found.");
                return self::FAILURE;
            }
            $businesses = collect([$business]);
        } else {
            $businesses = Business::all();
        }

        $this->info("Resequencing catalog for {$businesses->count()} business(es)...");

        foreach ($businesses as $biz) {
            $seq = SyncSequence::resequenceBusiness($biz->id);
            $this->line("Business [{$biz->id}] {$biz->name}: new sync sequence is {$seq}");
        }

        $this->info('Done resequencing catalog data.');

        return self::SUCCESS;
    }
}

