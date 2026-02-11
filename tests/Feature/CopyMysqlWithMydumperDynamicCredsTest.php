<?php

use App\Jobs\CopyMysqlWithMydumperDynamicCreds;
use App\Models\DbCopy;
use App\Models\DbCopyRow;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

it('creates db copy rows for dump files', function () {
    Queue::fake();

    $user = User::factory()->create();

    $dbCopy = DbCopy::query()->create([
        'status' => 'pending',
        'progress' => null,
        'source_connection' => 'mysql',
        'source_db' => 'source_db',
        'dest_connection' => 'mysql',
        'dest_db' => 'dest_db',
        'callback_url' => 'https://example.test/callback',
        'created_by_user_id' => $user->id,
    ]);

    $job = new CopyMysqlWithMydumperDynamicCreds(
        dbCopyId: $dbCopy->id,
        sourceConnection: 'mysql',
        sourceDatabase: 'source_db',
        destinationConnection: 'mysql',
        destinationDatabase: 'dest_db',
        threads: 1,
        recreateDestination: false,
    );

    $dumpDirectory = storage_path("app/db-copies/{$dbCopy->id}");

    File::ensureDirectoryExists($dumpDirectory);

    $dataFile = $dumpDirectory.'/table.sql';
    File::put($dataFile, 'INSERT INTO `table` VALUES (1);');

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('createRowsFromDumpFiles');
    $method->setAccessible(true);
    $method->invoke($job, $dbCopy, $dumpDirectory);

    $rows = DbCopyRow::query()->where('db_copy_id', $dbCopy->id)->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->name)->toBe('table');
    expect($rows->first()->dump_file_path)->toBe($dataFile);
    expect($rows->first()->status)->toBe('dumped');
    expect($rows->first()->source_size)->toBe(File::size($dataFile));
});

it('excludes send_mail_bodies from mydumper data dump', function () {
    Queue::fake();

    $user = User::factory()->create();

    $dbCopy = DbCopy::query()->create([
        'status' => 'pending',
        'progress' => null,
        'source_connection' => 'mysql',
        'source_db' => 'source_db',
        'dest_connection' => 'mysql',
        'dest_db' => 'dest_db',
        'callback_url' => 'https://example.test/callback',
        'created_by_user_id' => $user->id,
    ]);

    $job = new CopyMysqlWithMydumperDynamicCreds(
        dbCopyId: $dbCopy->id,
        sourceConnection: 'mysql',
        sourceDatabase: 'source_db',
        destinationConnection: 'mysql',
        destinationDatabase: 'dest_db',
        threads: 1,
        recreateDestination: false,
    );

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('buildMydumperCommand');
    $method->setAccessible(true);

    $sourceConfig = config('database.connections.mysql');
    $dumpDirectory = storage_path("app/db-copies/{$dbCopy->id}");

    $command = $method->invoke($job, $sourceConfig, $dumpDirectory);

    expect($command)->toContain('--regex=^(?!(source_db.send_mail_bodies$))');
});

it('selects destination connection with the smallest persisted copy size', function () {
    Queue::fake();

    $user = User::factory()->create();

    $copyOnMysql = DbCopy::query()->create([
        'status' => 'succeeded',
        'progress' => 100,
        'source_connection' => 'mysql',
        'source_db' => 'source_mysql',
        'dest_connection' => 'mysql',
        'dest_db' => 'dest_mysql',
        'callback_url' => '',
        'created_by_user_id' => $user->id,
    ]);

    DbCopyRow::query()->create([
        'db_copy_id' => $copyOnMysql->id,
        'name' => 'table_a',
        'dump_file_path' => '/tmp/table_a.sql',
        'status' => 'verified',
        'source_size' => 8000,
        'dest_size' => 8000,
    ]);

    $copyOnCloudCluster = DbCopy::query()->create([
        'status' => 'succeeded',
        'progress' => 100,
        'source_connection' => 'mysql',
        'source_db' => 'source_cloud_cluster',
        'dest_connection' => 'cloud-cluster-1',
        'dest_db' => 'dest_cloud_cluster',
        'callback_url' => '',
        'created_by_user_id' => $user->id,
    ]);

    DbCopyRow::query()->create([
        'db_copy_id' => $copyOnCloudCluster->id,
        'name' => 'table_b',
        'dump_file_path' => '/tmp/table_b.sql',
        'status' => 'verified',
        'source_size' => 2000,
        'dest_size' => 2000,
    ]);

    $job = new CopyMysqlWithMydumperDynamicCreds(
        dbCopyId: $copyOnMysql->id,
        sourceConnection: 'mysql',
        sourceDatabase: 'source_db',
        destinationConnection: ['mysql', 'cloud-cluster-1'],
        destinationDatabase: 'dest_db',
        threads: 1,
        recreateDestination: false,
    );

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('selectDestinationConnection');
    $method->setAccessible(true);

    $selected = $method->invoke($job);

    expect($selected)->toBe('cloud-cluster-1');
});
