<?php

use App\Models\User;

test('command creates api key for existing user', function (): void {
    $user = User::factory()->create();

    expect($user->tokens()->count())->toBe(0);

    $this->artisan('user:create-api-key', [
        'email' => $user->email,
        '--token' => 'cli-token',
    ])
        ->assertSuccessful();

    $user->refresh();

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->name)->toBe('cli-token');
});

test('command fails when user does not exist', function (): void {
    $this->artisan('user:create-api-key', [
        'email' => 'missing@example.com',
    ])
        ->assertExitCode(1);
});
