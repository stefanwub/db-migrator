<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateUserWithApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-with-api-key
                            {email : The user email address}
                            {--name= : The user name (defaults to email prefix)}
                            {--password= : The user password (generated if omitted)}
                            {--token=api-key : The name for the API token}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a user and output a Sanctum API key for the API';

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

        if ($user) {
            $this->info("User already exists: {$email}");
        } else {
            $name = $this->option('name') ?? Str::before($email, '@');
            $password = $this->option('password') ?? Str::random(32);

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $this->info("User created: {$email}");

            if (! $this->option('password')) {
                $this->newLine();
                $this->warn('Generated password (store it securely; it will not be shown again):');
                $this->line($password);
                $this->newLine();
            }
        }

        $tokenName = $this->option('token');
        $accessToken = $user->createToken($tokenName);
        $plainTextToken = $accessToken->plainTextToken;

        $this->newLine();
        $this->info('API key created. Use it as a Bearer token:');
        $this->line($plainTextToken);
        $this->newLine();
        $this->warn('Store this token securely; it will not be shown again.');

        return self::SUCCESS;
    }
}
