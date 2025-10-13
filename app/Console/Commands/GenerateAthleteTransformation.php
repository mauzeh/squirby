<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TransformationConfig;
use Carbon\Carbon;
use Database\Seeders\AthleteTransformationSeeder;
use Illuminate\Console\Command;

class GenerateAthleteTransformation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transformation:generate 
                            {--user= : Email of existing user to use for transformation}
                            {--weeks=12 : Duration of transformation in weeks}
                            {--start-weight=180 : Starting weight in pounds}
                            {--target-weight=165 : Target weight in pounds}
                            {--start-waist=36 : Starting waist measurement in inches}
                            {--program=strength : Program type (strength, powerlifting, bodybuilding)}
                            {--start-date= : Start date (YYYY-MM-DD format, defaults to today)}
                            {--no-variations : Disable realistic variations in data}
                            {--miss-rate=0.05 : Rate of missed workouts (0.0 to 1.0)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a comprehensive athlete transformation dataset with configurable parameters';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏋️ Athlete Transformation Generator');
        $this->info('==================================');

        try {
            // Validate and build configuration
            $config = $this->buildConfiguration();
            
            // Display configuration summary
            $this->displayConfigurationSummary($config);
            
            // Confirm execution
            if (!$this->confirm('Generate transformation data with these settings?')) {
                $this->info('Operation cancelled.');
                return 0;
            }

            // Execute seeder with progress tracking
            $this->info('Starting transformation data generation...');
            
            $seeder = new AthleteTransformationSeeder();
            $seeder->runWithConfig($config);
            
            $this->info('✅ Transformation data generation completed successfully!');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Error generating transformation data: ' . $e->getMessage());
            
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    /**
     * Build transformation configuration from command options.
     */
    private function buildConfiguration(): TransformationConfig
    {
        $config = new TransformationConfig();

        // User selection
        if ($userEmail = $this->option('user')) {
            $user = User::where('email', $userEmail)->first();
            if (!$user) {
                throw new \InvalidArgumentException("User with email '{$userEmail}' not found.");
            }
            $config->user = $user;
            $this->info("Using existing user: {$user->name} ({$user->email})");
        }

        // Duration validation
        $weeks = (int) $this->option('weeks');
        if ($weeks < 1 || $weeks > 52) {
            throw new \InvalidArgumentException('Duration must be between 1 and 52 weeks.');
        }
        $config->durationWeeks = $weeks;

        // Weight validation
        $startWeight = (float) $this->option('start-weight');
        $targetWeight = (float) $this->option('target-weight');
        
        if ($startWeight <= 0 || $targetWeight <= 0) {
            throw new \InvalidArgumentException('Weights must be positive numbers.');
        }
        
        if ($startWeight <= $targetWeight) {
            throw new \InvalidArgumentException('Starting weight must be greater than target weight for weight loss transformation.');
        }
        
        $config->startingWeight = $startWeight;
        $config->targetWeight = $targetWeight;

        // Waist measurement validation
        $startWaist = (float) $this->option('start-waist');
        if ($startWaist <= 0) {
            throw new \InvalidArgumentException('Starting waist measurement must be a positive number.');
        }
        $config->startingWaist = $startWaist;

        // Program type validation
        $programType = $this->option('program');
        $validPrograms = ['strength', 'powerlifting', 'bodybuilding'];
        if (!in_array($programType, $validPrograms)) {
            throw new \InvalidArgumentException('Program type must be one of: ' . implode(', ', $validPrograms));
        }
        $config->programType = $programType;

        // Start date validation
        if ($startDateStr = $this->option('start-date')) {
            try {
                $startDate = Carbon::createFromFormat('Y-m-d', $startDateStr);
                $config->startDate = $startDate;
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Start date must be in YYYY-MM-DD format.');
            }
        } else {
            $config->startDate = Carbon::today();
        }

        // Variations setting
        $config->includeVariations = !$this->option('no-variations');

        // Miss rate validation
        $missRate = (float) $this->option('miss-rate');
        if ($missRate < 0 || $missRate > 1) {
            throw new \InvalidArgumentException('Miss rate must be between 0.0 and 1.0.');
        }
        $config->missedWorkoutRate = $missRate;

        return $config;
    }

    /**
     * Display configuration summary to user.
     */
    private function displayConfigurationSummary(TransformationConfig $config): void
    {
        $this->info('Configuration Summary:');
        $this->line('---------------------');
        
        if ($config->user) {
            $this->line("User: {$config->user->name} ({$config->user->email})");
        } else {
            $this->line('User: New demo athlete will be created');
        }
        
        $this->line("Duration: {$config->durationWeeks} weeks");
        $this->line("Start Date: {$config->startDate->format('Y-m-d')}");
        $this->line("End Date: {$config->startDate->copy()->addWeeks($config->durationWeeks)->format('Y-m-d')}");
        $this->line("Starting Weight: {$config->startingWeight} lbs");
        $this->line("Target Weight: {$config->targetWeight} lbs");
        $this->line("Weight Loss Goal: " . ($config->startingWeight - $config->targetWeight) . " lbs");
        $this->line("Starting Waist: {$config->startingWaist} inches");
        $this->line("Program Type: {$config->programType}");
        $this->line("Include Variations: " . ($config->includeVariations ? 'Yes' : 'No'));
        $this->line("Missed Workout Rate: " . ($config->missedWorkoutRate * 100) . "%");
        $this->line('');
    }
}