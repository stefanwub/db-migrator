<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateApiKeyForUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-api-key
                            {email : The user email address}
                            {--token=api-key : The name for the API token}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a Sanctum API key for an existing user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("User not found for email [{$email}].");

            return self::FAILURE;
        }

        $tokenName = $this->option('token');
        $accessToken = $user->createToken($tokenName);

        $this->newLine();
        $this->info('API key created. Use it as a Bearer token:');
        $this->line($accessToken->plainTextToken);
        $this->newLine();
        $this->warn('Store this token securely; it will not be shown again.');

        return self::SUCCESS;
    }
}
