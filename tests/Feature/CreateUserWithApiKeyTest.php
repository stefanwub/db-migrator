<?php

use App\Models\User;
use Illuminate\Support\Str;

test('command creates user and outputs API key', function (): void {
    $email = 'cli-'.Str::random(8).'@example.com';

    $this->artisan('user:create-with-api-key', [
        'email' => $email,
        '--password' => 'secret123',
        '--token' => 'test-token',
    ])
        ->assertSuccessful();

    $user = User::query()->where('email', $email)->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe(Str::before($email, '@'));
    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->name)->toBe('test-token');
});

test('command uses existing user and creates new token', function (): void {
    $user = User::factory()->create();

    $this->artisan('user:create-with-api-key', [
        'email' => $user->email,
        '--token' => 'another-token',
    ])
        ->assertSuccessful();

    $user->refresh();

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->name)->toBe('another-token');
});
