<?php

use App\Models\DbCopy;
use App\Models\DbCopyRun;

test('db copy belongs to run and run has many copies', function (): void {
    $run = DbCopyRun::query()->create([
        'status' => 'queued',
        'source_system_db_connection' => 'mysql-source-system',
        'source_system_db_name' => 'source_system',
        'source_admin_app_connection' => 'mysql-admin-app',
        'source_admin_app_name' => 'admin_app',
        'source_db_connection' => 'mysql-source',
        'dest_db_connections' => ['mysql-dest-a', 'mysql-dest-b'],
    ]);

    $dbCopy = DbCopy::factory()->create([
        'db_copy_run_id' => $run->id,
    ]);

    expect($dbCopy->run)->toBeInstanceOf(DbCopyRun::class);
    expect($run->copies->pluck('id')->all())->toContain($dbCopy->id);
    expect($run->status)->toBe('queued');
    expect($run->dest_db_connections)->toBe(['mysql-dest-a', 'mysql-dest-b']);
    expect($run->durationSeconds())->toBeNull();
    expect($run->durationForDisplay())->toBeNull();
});
