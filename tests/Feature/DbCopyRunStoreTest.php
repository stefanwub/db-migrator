<?php

use App\Jobs\DispatchDbCopyRun;
use App\Models\DbCopyRun;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('authenticated users can create a db copy run', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    $payload = [
        'source_system_db_connection' => 'sqlite',
        'source_system_db_name' => 'system_db',
        'source_admin_app_connection' => 'sqlite',
        'source_admin_app_name' => 'admin_app',
        'source_db_connection' => 'sqlite',
        'dest_db_connections' => ['sqlite'],
        'threads' => 6,
        'recreateDestination' => true,
        'createDestDbOnLaravelCloud' => true,
    ];

    $response = $this->actingAs($user)->post(route('db-copy-runs.store'), $payload);

    $response->assertRedirect(route('db-copy-runs.index'));

    $run = DbCopyRun::query()->first();

    expect($run)->not->toBeNull();
    expect($run?->status)->toBe('queued');
    expect($run?->created_by_user_id)->toBe($user->id);
    expect($run?->create_dest_db_on_laravel_cloud)->toBeTrue();
    expect($run?->source_system_db_name)->toBe('system_db');
    expect($run?->source_admin_app_name)->toBe('admin_app');
    expect($run?->dest_db_connections)->toBe(['sqlite']);
    expect($run?->started_at)->toBeNull();
    expect($run?->finished_at)->toBeNull();

    Queue::assertPushed(DispatchDbCopyRun::class, function (DispatchDbCopyRun $job) use ($run, $user): bool {
        return $job->dbCopyRunId === $run?->id
            && $job->createdByUserId === $user->id
            && $job->threads === 6
            && $job->recreateDestination
            && $job->createDestDbOnLaravelCloud;
    });
});

test('run creation validates configured connections', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    $payload = [
        'source_system_db_connection' => 'invalid_connection',
        'source_system_db_name' => 'system_db',
        'source_admin_app_connection' => 'sqlite',
        'source_admin_app_name' => 'admin_app',
        'source_db_connection' => 'sqlite',
        'dest_db_connections' => ['sqlite'],
    ];

    $response = $this->actingAs($user)->post(route('db-copy-runs.store'), $payload);

    $response->assertSessionHasErrors([
        'source_system_db_connection',
    ]);

    Queue::assertNothingPushed();
});
