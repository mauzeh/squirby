<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class RestoreUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:restore {user? : User ID, email, or name to restore}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore a soft-deleted user by ID, email, or name';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userInput = $this->argument('user');
        
        // If no user specified, show list of deleted users
        if (!$userInput) {
            return $this->showDeletedUsers();
        }
        
        // Find the user
        $user = $this->findDeletedUser($userInput);
        
        if (!$user) {
            $this->error("No deleted user found matching: {$userInput}");
            return 1;
        }
        
        // Show user details and confirm
        $this->info("Found deleted user:");
        $this->table(
            ['ID', 'Name', 'Email', 'Deleted At'],
            [[$user->id, $user->name, $user->email, $user->deleted_at->format('Y-m-d H:i:s')]]
        );
        
        // Show related data that will be affected
        $this->showRelatedData($user);
        
        if (!$this->confirm("Do you want to restore this user?")) {
            $this->info('Restoration cancelled.');
            return 0;
        }
        
        // Restore the user
        $user->restore();
        
        $this->info("✅ User '{$user->name}' (ID: {$user->id}) has been restored successfully!");
        
        return 0;
    }
    
    /**
     * Show list of all deleted users
     */
    private function showDeletedUsers()
    {
        $deletedUsers = User::onlyTrashed()->get();
        
        if ($deletedUsers->isEmpty()) {
            $this->info('No deleted users found.');
            return 0;
        }
        
        $this->info('Deleted users:');
        $this->table(
            ['ID', 'Name', 'Email', 'Deleted At'],
            $deletedUsers->map(function ($user) {
                return [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->deleted_at->format('Y-m-d H:i:s')
                ];
            })->toArray()
        );
        
        $this->info('');
        $this->info('To restore a user, run: php artisan user:restore <user_id|email|name>');
        
        return 0;
    }
    
    /**
     * Find a deleted user by ID, email, or name
     */
    private function findDeletedUser($input)
    {
        // Try to find by ID first (if numeric)
        if (is_numeric($input)) {
            $user = User::onlyTrashed()->find($input);
            if ($user) {
                return $user;
            }
        }
        
        // Try to find by email
        $user = User::onlyTrashed()->where('email', $input)->first();
        if ($user) {
            return $user;
        }
        
        // Try to find by name (case insensitive)
        $user = User::onlyTrashed()->whereRaw('LOWER(name) = ?', [strtolower($input)])->first();
        if ($user) {
            return $user;
        }
        
        return null;
    }
    
    /**
     * Show related data that will be affected by restoring this user
     */
    private function showRelatedData(User $user)
    {
        // Count exercises owned by this user (including soft-deleted)
        $exerciseCount = \App\Models\Exercise::withTrashed()
            ->where('user_id', $user->id)
            ->count();
            
        // Count lift logs by this user (including soft-deleted)
        $liftLogCount = \App\Models\LiftLog::withTrashed()
            ->where('user_id', $user->id)
            ->count();
        
        // Count exercise aliases by this user (including soft-deleted)
        $aliasCount = \App\Models\ExerciseAlias::withTrashed()
            ->where('user_id', $user->id)
            ->count();
        
        // Count soft-deleted data that will be restored
        $deletedExerciseCount = \App\Models\Exercise::onlyTrashed()
            ->where('user_id', $user->id)
            ->count();
            
        $deletedLiftLogCount = \App\Models\LiftLog::onlyTrashed()
            ->where('user_id', $user->id)
            ->count();
        
        $deletedAliasCount = \App\Models\ExerciseAlias::onlyTrashed()
            ->where('user_id', $user->id)
            ->count();
        
        if ($exerciseCount > 0 || $liftLogCount > 0 || $aliasCount > 0) {
            $this->info('');
            $this->info('Related data that will be reconnected:');
            
            if ($exerciseCount > 0) {
                $restoredText = $deletedExerciseCount > 0 ? " ({$deletedExerciseCount} will be restored)" : "";
                $this->line("• {$exerciseCount} exercise(s){$restoredText}");
            }
            
            if ($liftLogCount > 0) {
                $restoredText = $deletedLiftLogCount > 0 ? " ({$deletedLiftLogCount} will be restored)" : "";
                $this->line("• {$liftLogCount} lift log(s){$restoredText}");
            }
            
            if ($aliasCount > 0) {
                $restoredText = $deletedAliasCount > 0 ? " ({$deletedAliasCount} will be restored)" : "";
                $this->line("• {$aliasCount} exercise alias(es){$restoredText}");
            }
            
            if ($deletedExerciseCount > 0 || $deletedLiftLogCount > 0 || $deletedAliasCount > 0) {
                $this->comment('Note: Soft-deleted associated data will be automatically restored.');
            }
            
            $this->info('');
        }
    }
}
