<?php

namespace App\Console\Commands;

use App\Models\TransferConfirmation;
use Illuminate\Console\Command;

class CleanupExpiredTransferConfirmations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transfers:cleanup-expired
                            {--days=7 : Delete confirmations older than X days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired and old transfer confirmations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');

        $deleted = TransferConfirmation::where('expires_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Deleted {$deleted} expired transfer confirmation(s) older than {$days} days.");

        return Command::SUCCESS;
    }
}
