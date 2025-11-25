<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\MagicLoginToken;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateMagicLink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-magic-link {userId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a magic login link for a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('userId');
        $user = User::find($userId);

        if (!$user) {
            $this->error('User not found.');
            return;
        }

        $token = Str::random(60);

        MagicLoginToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addHours(72),
            'uses_remaining' => 5,
        ]);

        $magicLink = url('/magic-login/' . $token);

        $this->info('Magic link for ' . $user->name . ':');
        $this->info($magicLink);
    }
}