<?php

namespace App\Console\Commands;

use App\Models\MagicLoginToken;
use Illuminate\Console\Command;

class CleanExpiredMagicLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-expired-magic-links';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired magic login tokens';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning up expired magic login tokens...');

        $deletedCount = MagicLoginToken::where('expires_at', '<', now())
            ->orWhere('uses_remaining', '<=', 0)
            ->delete();

        $this->info($deletedCount . ' expired magic login tokens have been deleted.');
    }
}