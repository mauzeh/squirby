<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LiftLog;
use App\Models\LiftSet;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AnalyzeLiftLogs extends Command
{
    protected $signature = 'lift-logs:analyze
                            {--user-id= : Analyze specific user\'s lift logs}
                            {--days= : Analyze logs from last N days}
                            {--export= : Export results to file (json or md)}';

    protected $description = 'Analyze lift logs data across users and provide insights';

    public function handle()
    {
        $this->info('=== LIFT LOGS DATA ANALYSIS ===');
        $this->newLine();

        // Apply filters
        $userId = $this->option('user-id');
        $days = $this->option('days');

        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return Command::FAILURE;
            }
            $this->comment("Filtering by user: {$user->name}");
            $this->newLine();
        }

        if ($days) {
            $this->comment("Filtering by last {$days} days");
            $this->newLine();
        }

        // 1. Overall Statistics
        $this->displayOverallStats($userId, $days);

        // 2. User Activity Analysis
        $this->displayUserActivity($userId, $days);

        // 3. Most Popular Exercises
        $this->displayPopularExercises($userId, $days);

        // 4. Exercise Volume Analysis
        $this->displayVolumeAnalysis($userId, $days);

        // 5. Workout Frequency
        $this->displayWorkoutFrequency($userId);

        // 6. Weight Progression
        $this->displayWeightProgression($userId, $days);

        // 7. Recent Activity
        $this->displayRecentActivity($userId);

        $this->newLine();
        $this->info('=== ANALYSIS COMPLETE ===');

        // Export if requested
        if ($export = $this->option('export')) {
            $this->exportResults($export);
        }

        return Command::SUCCESS;
    }

    private function displayOverallStats($userId, $days)
    {
        $this->info('1. OVERALL STATISTICS');
        $this->line(str_repeat('-', 50));

        $query = LiftLog::query();
        if ($userId) {
            $query->where('user_id', $userId);
        }
        if ($days) {
            $query->where('logged_at', '>=', now()->subDays($days));
        }

        $totalLiftLogs = $query->count();
        $totalLiftSets = LiftSet::whereIn('lift_log_id', $query->pluck('id'))->count();
        $totalUsers = User::whereHas('liftLogs', function ($q) use ($userId, $days) {
            if ($userId) {
                $q->where('user_id', $userId);
            }
            if ($days) {
                $q->where('logged_at', '>=', now()->subDays($days));
            }
        })->count();
        $totalExercises = Exercise::whereHas('liftLogs', function ($q) use ($userId, $days) {
            if ($userId) {
                $q->where('user_id', $userId);
            }
            if ($days) {
                $q->where('logged_at', '>=', now()->subDays($days));
            }
        })->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Lift Logs', number_format($totalLiftLogs)],
                ['Total Lift Sets', number_format($totalLiftSets)],
                ['Active Users', number_format($totalUsers)],
                ['Exercises Logged', number_format($totalExercises)],
            ]
        );
        $this->newLine();
    }

    private function displayUserActivity($userId, $days)
    {
        $this->info('2. USER ACTIVITY ANALYSIS');
        $this->line(str_repeat('-', 50));

        $query = DB::table('lift_logs')
            ->join('users', 'lift_logs.user_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                DB::raw('COUNT(DISTINCT lift_logs.id) as total_workouts'),
                DB::raw('COUNT(DISTINCT DATE(lift_logs.logged_at)) as workout_days'),
                DB::raw('MIN(lift_logs.logged_at) as first_workout'),
                DB::raw('MAX(lift_logs.logged_at) as last_workout')
            )
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_workouts');

        if ($userId) {
            $query->where('users.id', $userId);
        }
        if ($days) {
            $query->where('lift_logs.logged_at', '>=', now()->subDays($days));
        }

        $userStats = $query->get();

        $tableData = $userStats->map(function ($user) {
            return [
                $user->name,
                number_format($user->total_workouts),
                number_format($user->workout_days),
                \Carbon\Carbon::parse($user->first_workout)->format('M d, Y'),
                \Carbon\Carbon::parse($user->last_workout)->format('M d, Y'),
            ];
        })->toArray();

        $this->table(
            ['User', 'Total Workouts', 'Workout Days', 'First Workout', 'Last Workout'],
            $tableData
        );
        $this->newLine();
    }

    private function displayPopularExercises($userId, $days)
    {
        $this->info('3. MOST POPULAR EXERCISES');
        $this->line(str_repeat('-', 50));

        $query = DB::table('lift_logs')
            ->join('exercises', 'lift_logs.exercise_id', '=', 'exercises.id')
            ->select(
                'exercises.title',
                DB::raw('COUNT(lift_logs.id) as log_count'),
                DB::raw('COUNT(DISTINCT lift_logs.user_id) as user_count')
            )
            ->groupBy('exercises.id', 'exercises.title')
            ->orderByDesc('log_count')
            ->limit(10);

        if ($userId) {
            $query->where('lift_logs.user_id', $userId);
        }
        if ($days) {
            $query->where('lift_logs.logged_at', '>=', now()->subDays($days));
        }

        $popularExercises = $query->get();

        $tableData = $popularExercises->map(function ($exercise, $idx) {
            return [
                $idx + 1,
                $exercise->title,
                number_format($exercise->log_count),
                number_format($exercise->user_count),
            ];
        })->toArray();

        $this->table(
            ['Rank', 'Exercise', 'Times Logged', 'Users'],
            $tableData
        );
        $this->newLine();
    }

    private function displayVolumeAnalysis($userId, $days)
    {
        $this->info('4. EXERCISE VOLUME ANALYSIS (Top 10)');
        $this->line(str_repeat('-', 50));

        $query = LiftLog::with(['liftSets', 'exercise', 'user']);
        if ($userId) {
            $query->where('user_id', $userId);
        }
        if ($days) {
            $query->where('logged_at', '>=', now()->subDays($days));
        }

        $liftLogs = $query->get();
        $unitResolver = app(\App\Services\UnitResolver::class);

        // Group by user and exercise
        $volumeData = [];
        foreach ($liftLogs as $log) {
            $user = $log->user;
            if (!$user) {
                continue;
            }
            
            $exercise = $log->exercise;
            if (!$exercise) {
                continue;
            }

            $key = $user->id . '_' . $exercise->id;
            
            if (!isset($volumeData[$key])) {
                $volumeData[$key] = [
                    'user_name' => $user->name,
                    'exercise_title' => $exercise->title,
                    'user' => $user,
                    'total_sets' => 0,
                    'total_reps' => 0,
                    'weights' => [],
                    'total_volume' => 0.0,
                ];
            }

            $preferredUnit = $unitResolver->getPreferredWeightUnit($user);

            foreach ($log->liftSets as $set) {
                if ($set->weight > 0 && $set->reps > 0) {
                    $loggedUnit = $set->unit ?? 'lbs';
                    $weightInPreferredUnit = $unitResolver->convert($set->weight, $loggedUnit, $preferredUnit);
                    
                    $volumeData[$key]['total_sets']++;
                    $volumeData[$key]['total_reps'] += $set->reps;
                    $volumeData[$key]['weights'][] = $weightInPreferredUnit;
                    $volumeData[$key]['total_volume'] += ($weightInPreferredUnit * $set->reps);
                }
            }
        }

        // Sort by total volume descending and take 10
        $topVolume = collect($volumeData)
            ->filter(fn($data) => !empty($data['weights']))
            ->sortByDesc('total_volume')
            ->take(10);

        $tableData = [];
        $idx = 1;
        foreach ($topVolume as $record) {
            $preferredUnit = $unitResolver->getPreferredWeightUnit($record['user']);
            $avgWeight = count($record['weights']) > 0 ? array_sum($record['weights']) / count($record['weights']) : 0;
            $maxWeight = count($record['weights']) > 0 ? max($record['weights']) : 0;

            $tableData[] = [
                $idx++,
                $record['user_name'],
                $record['exercise_title'],
                number_format($record['total_sets']),
                number_format($record['total_reps']),
                $unitResolver->format($avgWeight, $preferredUnit),
                $unitResolver->format($maxWeight, $preferredUnit),
                $unitResolver->format($record['total_volume'], $preferredUnit),
            ];
        }

        $this->table(
            ['#', 'User', 'Exercise', 'Sets', 'Reps', 'Avg Weight', 'Max Weight', 'Total Volume'],
            $tableData
        );
        $this->newLine();
    }

    private function displayWorkoutFrequency($userId)
    {
        $this->info('5. WORKOUT FREQUENCY (Last 30 Days)');
        $this->line(str_repeat('-', 50));

        $query = DB::table('lift_logs')
            ->join('users', 'lift_logs.user_id', '=', 'users.id')
            ->where('lift_logs.logged_at', '>=', now()->subDays(30))
            ->select(
                'users.name',
                DB::raw('COUNT(DISTINCT DATE(lift_logs.logged_at)) as workout_days'),
                DB::raw('COUNT(lift_logs.id) as total_exercises')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('workout_days');

        if ($userId) {
            $query->where('users.id', $userId);
        }

        $recentActivity = $query->get();

        if ($recentActivity->count() > 0) {
            $tableData = $recentActivity->map(function ($user) {
                return [
                    $user->name,
                    number_format($user->workout_days),
                    number_format($user->total_exercises),
                    number_format($user->total_exercises / max($user->workout_days, 1), 1),
                ];
            })->toArray();

            $this->table(
                ['User', 'Workout Days', 'Total Exercises', 'Avg Exercises/Day'],
                $tableData
            );
        } else {
            $this->comment('No workout activity in the last 30 days.');
        }
        $this->newLine();
    }

    private function displayWeightProgression($userId, $days)
    {
        $this->info('6. WEIGHT PROGRESSION (Top 5 Exercises)');
        $this->line(str_repeat('-', 50));

        $query = DB::table('lift_logs')
            ->join('exercises', 'lift_logs.exercise_id', '=', 'exercises.id')
            ->select('exercises.id', 'exercises.title', DB::raw('COUNT(*) as count'))
            ->groupBy('exercises.id', 'exercises.title')
            ->orderByDesc('count')
            ->limit(5);

        if ($userId) {
            $query->where('lift_logs.user_id', $userId);
        }
        if ($days) {
            $query->where('lift_logs.logged_at', '>=', now()->subDays($days));
        }

        $topExercises = $query->get();
        $unitResolver = app(\App\Services\UnitResolver::class);

        foreach ($topExercises as $exercise) {
            $this->comment("Exercise: {$exercise->title}");

            // Fetch all logs for this exercise with sets and user
            $logsQuery = LiftLog::where('exercise_id', $exercise->id)
                ->with(['liftSets', 'user']);
            
            if ($userId) {
                $logsQuery->where('user_id', $userId);
            }
            if ($days) {
                $logsQuery->where('logged_at', '>=', now()->subDays($days));
            }

            // Group by user to calculate progression per user
            $userLogs = $logsQuery->get()->groupBy('user_id');

            $progression = [];

            foreach ($userLogs as $uId => $logs) {
                $user = $logs->first()->user;
                if (!$user) {
                    continue;
                }

                $preferredUnit = $unitResolver->getPreferredWeightUnit($user);

                // Collect all weights in chronological order
                $chronologicalLogs = $logs->sortBy('logged_at');
                
                $weights = [];
                foreach ($chronologicalLogs as $log) {
                    foreach ($log->liftSets as $set) {
                        if ($set->weight > 0) {
                            $loggedUnit = $set->unit ?? 'lbs';
                            $weights[] = $unitResolver->convert($set->weight, $loggedUnit, $preferredUnit);
                        }
                    }
                }

                if (count($weights) >= 2) {
                    $startingWeight = $weights[0];
                    $currentWeight = end($weights);
                    $weightGain = $currentWeight - $startingWeight;

                    if ($weightGain > 0) {
                        $progression[] = [
                            'name' => $user->name,
                            'starting_weight' => $startingWeight,
                            'current_weight' => $currentWeight,
                            'weight_gain' => $weightGain,
                            'unit' => $preferredUnit,
                        ];
                    }
                }
            }

            // Sort progression by weight gain descending
            $progression = collect($progression)->sortByDesc('weight_gain');

            if ($progression->count() > 0) {
                foreach ($progression as $userProgress) {
                    $unit = $userProgress['unit'];
                    $this->line("  {$userProgress['name']}: " .
                        $unitResolver->format($userProgress['starting_weight'], $unit) . " → " .
                        $unitResolver->format($userProgress['current_weight'], $unit) . " " .
                        "(+" . $unitResolver->format($userProgress['weight_gain'], $unit) . ")");
                }
            } else {
                $this->line("  No progression data available");
            }
            $this->newLine();
        }
    }

    private function displayRecentActivity($userId)
    {
        $this->info('7. RECENT ACTIVITY (Last 10 Workouts)');
        $this->line(str_repeat('-', 50));

        $query = LiftLog::with(['exercise', 'user', 'liftSets'])
            ->orderByDesc('logged_at')
            ->limit(10);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $recentWorkouts = $query->get();
        $unitResolver = app(\App\Services\UnitResolver::class);

        $tableData = $recentWorkouts->map(function ($log, $idx) use ($unitResolver) {
            $firstSet = $log->liftSets->first();
            $user = $log->user;
            
            if ($firstSet && $user) {
                $preferredUnit = $unitResolver->getPreferredWeightUnit($user);
                $loggedUnit = $firstSet->unit ?? 'lbs';
                $weightFormatted = $unitResolver->formatForUser($firstSet->weight, $loggedUnit, $user);
                $reps = $firstSet->reps;
                $weightRepsDisplay = "{$weightFormatted} x {$reps}";
            } else {
                $weightRepsDisplay = 'N/A';
            }

            return [
                $idx + 1,
                $log->user->name ?? 'N/A',
                $log->exercise->title ?? 'N/A',
                $log->logged_at->format('M d, Y H:i'),
                $log->liftSets->count(),
                $weightRepsDisplay,
            ];
        })->toArray();

        $this->table(
            ['#', 'User', 'Exercise', 'Date', 'Sets', 'Weight x Reps'],
            $tableData
        );
        $this->newLine();
    }

    private function exportResults($format)
    {
        $this->info("Exporting results to {$format} format...");
        // TODO: Implement export functionality
        $this->comment('Export feature coming soon!');
    }
}
