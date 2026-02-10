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
