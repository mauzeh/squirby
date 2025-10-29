<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MobileFoodForm;

class CleanupMobileFoodForms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mobile-food-forms:cleanup {--days=7 : Number of days to keep forms}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old mobile food forms to prevent database bloat';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        
        $deletedCount = MobileFoodForm::where('date', '<', now()->subDays($days))->count();
        
        MobileFoodForm::where('date', '<', now()->subDays($days))->delete();
        
        $this->info("Cleaned up {$deletedCount} mobile food forms older than {$days} days.");
        
        return 0;
    }
}
