<?php

namespace App\Sync\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PurgeSyncLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:purge-logs {--days=30 : Delete logs older than this number of days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete sync log files older than a configured number of days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $this->info("Purging sync logs older than {$days} days...");

        $dir = storage_path('logs/sync');
        if (!File::isDirectory($dir)) {
            $this->warn("Sync logs directory does not exist: {$dir}");
            return Command::SUCCESS;
        }

        $cutoff = now()->subDays($days)->getTimestamp();
        $files = File::files($dir);
        $deletedCount = 0;

        foreach ($files as $file) {
            if ($file->getExtension() === 'log' && $file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
                $this->info("Deleted log file: " . $file->getFilename());
                $deletedCount++;
            }
        }

        $this->info("Successfully deleted {$deletedCount} log files.");

        return Command::SUCCESS;
    }
}
