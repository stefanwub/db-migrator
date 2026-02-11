<?php

use App\Jobs\CopyMysqlWithMydumperDynamicCreds;
use App\Jobs\DispatchDbCopyRun;
use App\Jobs\DispatchDbCopyRunSourceDatabaseCopies;
use App\Models\DbCopy;
use App\Models\DbCopyRun;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

test('dispatch db copy run creates system and admin copies and chains required jobs', function (): void {
    Bus::fake();

    $user = User::factory()->create();

    $run = DbCopyRun::factory()->create([
        'source_system_db_connection' => 'sqlite',
        'source_system_db_name' => 'system_db',
        'source_admin_app_connection' => 'sqlite',
        'source_admin_app_name' => 'admin_app',
        'source_db_connection' => 'sqlite',
        'dest_db_connections' => ['sqlite'],
    ]);

    $job = new DispatchDbCopyRun(
        dbCopyRunId: $run->id,
        createdByUserId: $user->id,
        threads: 8,
        recreateDestination: true,
    );

    $job->handle();

    $run->refresh();

    $copies = DbCopy::query()
        ->where('db_copy_run_id', $run->id)
        ->orderBy('source_db')
        ->get();

    expect($copies)->toHaveCount(2);
    expect($copies->pluck('source_db')->all())->toBe(['admin_app', 'system_db']);
    expect($copies->pluck('created_by_user_id')->unique()->all())->toBe([$user->id]);
    expect($run->status)->toBe('running');
    expect($run->started_at)->not->toBeNull();
    expect($run->finished_at)->toBeNull();

    Bus::assertChained([
        CopyMysqlWithMydumperDynamicCreds::class,
        CopyMysqlWithMydumperDynamicCreds::class,
        DispatchDbCopyRunSourceDatabaseCopies::class,
    ]);
});

test('source database fan-out dispatches one copy job per discovered database', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    $run = DbCopyRun::factory()->create([
        'source_system_db_connection' => 'sqlite',
        'source_system_db_name' => 'system_db',
        'source_admin_app_connection' => 'sqlite',
        'source_admin_app_name' => 'admin_app',
        'source_db_connection' => 'sqlite',
        'dest_db_connections' => ['sqlite'],
    ]);

    $job = \Mockery::mock(DispatchDbCopyRunSourceDatabaseCopies::class, [
        $run->id,
        $user->id,
        8,
        true,
    ])->makePartial();

    $job->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('loadSourceClusterDatabaseNames')
        ->once()
        ->andReturn(['tenant_alpha', 'tenant_beta']);

    $job->handle();

    $copies = DbCopy::query()
        ->where('db_copy_run_id', $run->id)
        ->orderBy('source_db')
        ->get();

    expect($copies)->toHaveCount(2);
    expect($copies->pluck('source_db')->all())->toBe(['tenant_alpha', 'tenant_beta']);

    Queue::assertPushed(CopyMysqlWithMydumperDynamicCreds::class, 2);
});
