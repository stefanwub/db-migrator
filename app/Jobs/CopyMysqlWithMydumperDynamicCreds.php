<?php

namespace App\Jobs;

use App\Models\DbCopy;
use App\Models\DbCopyRow;
use App\Models\DbCopyRun;
use App\Services\DbCopyWebhookNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class CopyMysqlWithMydumperDynamicCreds implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $dbCopyId,
        public string $sourceConnection,
        public string $sourceDatabase,
        public string|array $destinationConnection,
        public string $destinationDatabase,
        public int $threads = 8,
        public bool $recreateDestination = true,
        public bool $createDestDbOnLaravelCloud = false,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DbCopyWebhookNotifier $notifier): void
    {
        $dbCopy = DbCopy::query()->find($this->dbCopyId);

        if (! $dbCopy) {
            return;
        }

        try {
            $selectedDestinationConnection = $this->selectDestinationConnection();

            $this->destinationConnection = $selectedDestinationConnection;

            $dbCopy->update([
                'dest_connection' => $selectedDestinationConnection,
            ]);

            $this->markRunning($dbCopy);
            $notifier->notify($dbCopy);

            $this->prepareDestinationDatabase();
            $this->runMydumperAndMyloader($dbCopy);
            $this->verifyCopiedRows($dbCopy);

            $this->markSucceeded($dbCopy);
            $notifier->notify($dbCopy);
            $this->syncRunStatus($dbCopy);
        } catch (Throwable $e) {
            $this->markFailed($dbCopy, $e);
            $this->syncRunStatus($dbCopy);

            try {
                $notifier->notify($dbCopy);
            } catch (Throwable) {
                //
            }
        }
    }

    /**
     * Handle a job failure that was not caught inside handle().
     */
    public function failed(Throwable $e): void
    {
        $dbCopy = DbCopy::query()->find($this->dbCopyId);

        if (! $dbCopy) {
            return;
        }

        $this->markFailed($dbCopy, $e);

        DbCopyRow::query()
            ->where('db_copy_id', $dbCopy->id)
            ->whereNotIn('status', ['imported', 'verified'])
            ->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);

        try {
            app(DbCopyWebhookNotifier::class)->notify($dbCopy);
        } catch (Throwable) {
            //
        }

        $this->syncRunStatus($dbCopy);
    }

    /**
     * Mark the DB copy as running.
     */
    protected function markRunning(DbCopy $dbCopy): void
    {
        $dbCopy->update([
            'status' => 'running',
            'started_at' => $dbCopy->started_at ?? now(),
            'last_error' => null,
        ]);
    }

    /**
     * Mark the DB copy as succeeded.
     */
    protected function markSucceeded(DbCopy $dbCopy): void
    {
        $dbCopy->update([
            'status' => 'succeeded',
            'progress' => 100,
            'finished_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Mark the DB copy as failed.
     */
    protected function markFailed(DbCopy $dbCopy, Throwable $e): void
    {
        $dbCopy->update([
            'status' => 'failed',
            'finished_at' => now(),
            'last_error' => mb_substr($e->getMessage(), 0, 1000),
        ]);
    }

    /**
     * Sync associated run status from all related copies.
     */
    protected function syncRunStatus(DbCopy $dbCopy): void
    {
        if ($dbCopy->db_copy_run_id === null) {
            return;
        }

        $dbCopyRun = DbCopyRun::query()->find($dbCopy->db_copy_run_id);

        if ($dbCopyRun === null) {
            return;
        }

        $dbCopyRun->syncStatusFromCopies();
    }

    /**
     * Prepare the destination database using dynamic connection configuration.
     */
    protected function prepareDestinationDatabase(): void
    {
        $destinationConfig = config("database.connections.{$this->destinationConnection}");

        if (! is_array($destinationConfig)) {
            throw new RuntimeException("Destination connection [{$this->destinationConnection}] is not configured.");
        }

        if (($destinationConfig['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('DB Copy Runner currently supports only MySQL destinations.');
        }

        $serverConnectionName = "{$this->destinationConnection}_server_{$this->dbCopyId}";

        $serverConfig = $destinationConfig;

        // Use a connection without selecting a specific database so we can
        // safely drop and create the destination database. Laravel's MySQL
        // connector still expects the key to exist, so we set it to null
        // instead of unsetting it.
        $serverConfig['database'] = null;

        config([
            "database.connections.{$serverConnectionName}" => $serverConfig,
        ]);

        DB::purge($serverConnectionName);

        $escapedDatabase = str_replace('`', '``', $this->destinationDatabase);

        // if ($this->createDestDbOnLaravelCloud) {
            $this->createDatabaseOnLaravelCloud((string) $this->destinationConnection, $this->destinationDatabase);
        // } else {
        //     if ($this->recreateDestination) {
        //         DB::connection($serverConnectionName)
        //             ->statement("DROP DATABASE IF EXISTS `{$escapedDatabase}`");
        //     }

        //     DB::connection($serverConnectionName)
        //         ->statement("CREATE DATABASE IF NOT EXISTS `{$escapedDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        // }

        $destinationConfig['database'] = $this->destinationDatabase;

        config([
            "database.connections.{$this->destinationConnection}" => $destinationConfig,
        ]);

        DB::purge($this->destinationConnection);
    }

    /**
     * Create destination database through Laravel Cloud API for the target connection.
     */
    protected function createDatabaseOnLaravelCloud(string $connectionName, string $databaseName): void
    {
        $apiToken = (string) config('services.laravel_cloud.api_token', '');
        $baseUrl = 'https://cloud.laravel.com/api/';
        $endpointTemplate = (string) 'databases/clusters/{cluster_id}/databases';
        $clusterIdsByConnection = config('services.laravel_cloud.database_cluster_ids', []);

        if (! is_array($clusterIdsByConnection)) {
            throw new RuntimeException('Laravel Cloud cluster mapping configuration is invalid.');
        }

        $clusterId = $clusterIdsByConnection[$connectionName] ?? null;

        if (! is_string($clusterId) || $clusterId === '') {
            throw new RuntimeException("Missing Laravel Cloud cluster id for destination connection [{$connectionName}].");
        }

        if ($apiToken === '' || $baseUrl === '' || $endpointTemplate === '') {
            throw new RuntimeException('Laravel Cloud API configuration is incomplete.');
        }

        $endpoint = str_replace('{cluster_id}', $clusterId, $endpointTemplate);
        $url = $baseUrl.'/'.ltrim($endpoint, '/');

        $response = Http::withToken($apiToken)
            ->acceptJson()
            ->asJson()
            ->post($url, [
                'name' => $databaseName,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Laravel Cloud database creation failed: HTTP '.$response->status().' '.$response->body()
            );
        }

        sleep(2);
    }

    /**
     * Run mysqldump/mydumper to export the source database, then import into destination using mysql client.
     */
    protected function runMydumperAndMyloader(DbCopy $dbCopy): void
    {
        $sourceConfig = config("database.connections.{$this->sourceConnection}");
        $destinationConfig = config("database.connections.{$this->destinationConnection}");

        if (! is_array($sourceConfig) || ! is_array($destinationConfig)) {
            throw new RuntimeException('Source or destination connection is not configured.');
        }

        if (($sourceConfig['driver'] ?? null) !== 'mysql' || ($destinationConfig['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('DB Copy Runner currently supports only MySQL connections.');
        }

        $dumpDirectory = storage_path("app/db-copies/{$this->dbCopyId}");

        File::ensureDirectoryExists($dumpDirectory);

        $this->recordDatabaseTotalSourceSize($dbCopy);
        $this->dumpSchemaWithMysqldump($sourceConfig, $dumpDirectory);
        $this->runMydumper($sourceConfig, $dumpDirectory, $dbCopy);
        $this->recordSourceStats($dbCopy);
        $this->restoreSchemaWithMysql($destinationConfig, $dumpDirectory);
        $this->importDumpDataWithMysql($destinationConfig, $dumpDirectory, $dbCopy);

        File::deleteDirectory($dumpDirectory);
    }

    /**
     * Dump only the schema of the source database using mysqldump.
     *
     * @param  array<string, mixed>  $sourceConfig
     */
    protected function dumpSchemaWithMysqldump(array $sourceConfig, string $dumpDirectory): void
    {
        $sourceArgs = $this->buildMysqlCliArgs($sourceConfig, $this->sourceDatabase);

        $mysqldumpCommand = array_merge(
            ['mysqldump'],
            [
                '-h'.$sourceConfig['host'],
                '-u'.$sourceConfig['username'],
                '-p'.$sourceConfig['password'],
            ],
            [
                '--no-data',
                '--routines',
                '--triggers',
                '--events',
                '--single-transaction',
                '--set-gtid-purged=OFF',
            ],
            [
                $this->sourceDatabase,
            ]
        );

        $mysqldumpResult = Process::timeout(0)->run($mysqldumpCommand);

        if ($mysqldumpResult->failed()) {
            throw new RuntimeException('mysqldump (schema) failed with error: '.$mysqldumpResult->errorOutput());
        }

        $schemaDumpPath = $dumpDirectory.'/schema.sql';

        File::put($schemaDumpPath, $mysqldumpResult->output());
    }

    /**
     * Run mydumper to export the data of the source database (without schema).
     *
     * @param  array<string, mixed>  $sourceConfig
     */
    protected function runMydumper(array $sourceConfig, string $dumpDirectory, DbCopy $dbCopy): void
    {
        $mydumperCommand = $this->buildMydumperCommand($sourceConfig, $dumpDirectory);

        $mydumperResult = Process::timeout(0)->run($mydumperCommand);

        if ($mydumperResult->failed()) {
            throw new RuntimeException('mydumper failed with error: '.$mydumperResult->errorOutput());
        }

        $this->createRowsFromDumpFiles($dbCopy, $dumpDirectory);
    }

    /**
     * Build the mydumper command to export data from the source database.
     *
     * @param  array<string, mixed>  $sourceConfig
     * @return array<int, string>
     */
    protected function buildMydumperCommand(array $sourceConfig, string $dumpDirectory): array
    {
        $sourceArgs = $this->buildMysqlCliArgs($sourceConfig, $this->sourceDatabase);

        $command = array_merge(
            ['mydumper'],
            $sourceArgs,
            [
                '--threads='.$this->threads,
                '--outputdir='.$dumpDirectory,
                '--trx-consistency-only',
                '--less-locking',
                '--no-locks',
                '--no-schemas',
                '--skip-tz-utc',
            ],
        );

        $command[] = '--regex=^(?!('.$this->sourceDatabase.'.sent_mail_bodies$))';

        return $command;
    }

    /**
     * Restore the schema into the destination database using the mysql client.
     */
    protected function restoreSchemaWithMysql(array $destinationConfig, string $dumpDirectory): void
    {
        $schemaPath = $dumpDirectory.'/schema.sql';

        if (! File::exists($schemaPath)) {
            throw new RuntimeException('Schema dump file [schema.sql] is missing.');
        }

        $destinationArgs = $this->buildMysqlCliArgs($destinationConfig, $this->destinationDatabase);
        $mysqlCommand = array_merge(['mysql'], $destinationArgs);

        $sql = File::get($schemaPath);

        $result = Process::timeout(0)->input($sql)->run($mysqlCommand);

        if ($result->failed()) {
            throw new RuntimeException(
                'mysql schema import failed for schema.sql: '.$result->errorOutput()
            );
        }
    }

    /**
     * Import each data dump file into the destination database using the mysql client.
     */
    protected function importDumpDataWithMysql(array $destinationConfig, string $dumpDirectory, DbCopy $dbCopy): void
    {
        $destinationArgs = $this->buildMysqlCliArgs($destinationConfig, $this->destinationDatabase);
        $mysqlArgs = array_merge(['mysql'], $destinationArgs);

        $files = File::glob($dumpDirectory.'/*.sql');

        if ($files === []) {
            return;
        }

        // Disable foreign key checks once for the session before imports.
        $fkOffProcess = Process::timeout(0)->run(array_merge(
            $mysqlArgs,
            ['-e', 'SET FOREIGN_KEY_CHECKS=0;'],
        ));

        if ($fkOffProcess->failed()) {
            throw new RuntimeException(
                'mysql failed to disable foreign key checks: '.$fkOffProcess->errorOutput()
            );
        }

        foreach ($files as $path) {
            if (basename($path) === 'schema.sql') {
                continue;
            }

            // Use shell redirection so the SQL file is streamed directly to mysql
            // by the operating system, avoiding loading large dumps into PHP memory.
            $commandLine = implode(' ', array_map('escapeshellarg', $mysqlArgs)).' < '.escapeshellarg($path);

            $result = Process::timeout(0)->run($commandLine);

            if ($result->failed()) {
                DbCopyRow::query()
                    ->where('db_copy_id', $dbCopy->id)
                    ->where('dump_file_path', $path)
                    ->update([
                        'status' => 'failed',
                        'error_message' => mb_substr($result->errorOutput(), 0, 1000),
                    ]);

                throw new RuntimeException(
                    'mysql import failed for '.basename($path).': '.$result->errorOutput()
                );
            }

            DbCopyRow::query()
                ->where('db_copy_id', $dbCopy->id)
                ->where('dump_file_path', $path)
                ->update([
                    'status' => 'imported',
                    'error_message' => null,
                ]);
        }
    }

    /**
     * Create DbCopyRow records for each data dump file produced by mydumper.
     */
    protected function createRowsFromDumpFiles(DbCopy $dbCopy, string $dumpDirectory): void
    {
        $files = File::glob($dumpDirectory.'/*.sql');

        foreach ($files as $path) {
            if (basename($path) === 'schema.sql') {
                continue;
            }

            $filename = pathinfo($path, PATHINFO_FILENAME);
            $name = str_contains($filename, '.') ? substr($filename, strpos($filename, '.') + 1) : $filename;

            DbCopyRow::query()->create([
                'db_copy_id' => $dbCopy->id,
                'name' => $name,
                'dump_file_path' => $path,
                'status' => 'dumped',
            ]);
        }
    }

    /**
     * Populate source row count and size for each table immediately after dumping.
     */
    protected function recordSourceStats(DbCopy $dbCopy): void
    {
        $rows = $dbCopy->rows()->get();

        if ($rows->isEmpty()) {
            return;
        }

        $tableNames = $rows->pluck('name')->all();

        $sourceStats = $this->getTableStats(
            $this->sourceConnection,
            $this->sourceDatabase,
            $tableNames
        );

        foreach ($rows as $row) {
            $source = $sourceStats[$row->name] ?? null;

            if ($source === null) {
                continue;
            }

            $row->update([
                'source_row_count' => $source['row_count'],
                'source_size' => $source['size'],
            ]);
        }
    }

    /**
     * Calculate and store the total source database size in bytes.
     */
    protected function recordDatabaseTotalSourceSize(DbCopy $dbCopy): void
    {
        $connection = DB::connection($this->sourceConnection);

        $result = $connection->selectOne(
            'SELECT SUM(DATA_LENGTH + INDEX_LENGTH) as size_bytes
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?',
            [$this->sourceDatabase],
        );

        $totalSize = (int) ($result->size_bytes ?? 0);

        $dbCopy->update([
            'total_source_size' => $totalSize,
        ]);
    }

    /**
     * Verify that all DbCopyRow records have matching counts and sizes after import.
     */
    protected function verifyCopiedRows(DbCopy $dbCopy): void
    {
        $rows = $dbCopy->rows;

        if ($rows->isEmpty()) {
            return;
        }

        $tableNames = $rows->pluck('name')->all();

        $sourceStats = $this->getTableStats(
            $this->sourceConnection,
            $this->sourceDatabase,
            $tableNames
        );

        $destinationStats = $this->getTableStats(
            $this->destinationConnection,
            $this->destinationDatabase,
            $tableNames
        );

        foreach ($rows as $row) {
            $source = $sourceStats[$row->name] ?? null;
            $destination = $destinationStats[$row->name] ?? null;

            if ($source === null || $destination === null) {
                throw new RuntimeException('Missing statistics for table '.$row->name);
            }

            $row->update([
                'source_row_count' => $source['row_count'],
                'dest_row_count' => $destination['row_count'],
                'source_size' => $source['size'],
                'dest_size' => $destination['size'],
                'status' => 'verified',
            ]);

            if ($source['row_count'] !== $destination['row_count'] && $source['size'] !== $destination['size']) {
                $row->update([
                    'status' => 'failed',
                    'error_message' => 'Row count and size both mismatch for '.$row->name,
                ]);
                throw new RuntimeException('Row count and size both mismatch for '.$row->name);
            }

            if ($source['row_count'] !== $destination['row_count']) {
                $row->update([
                    'status' => 'failed',
                    'error_message' => 'Row count mismatch for '.$row->name,
                ]);
                throw new RuntimeException('Row count mismatch for '.$row->name);
            }

            // if ($source['size'] !== $destination['size']) {
            //     $row->update([
            //         'status' => 'failed',
            //         'error_message' => 'Row size mismatch for '.$row->name,
            //     ]);
            //     throw new RuntimeException('Row size mismatch for '.$row->name);
            // }
        }
    }

    /**
     * Get row count and size statistics for the given tables in a database.
     *
     * Uses COUNT(*) for exact row counts and information_schema for size.
     *
     * @param  array<int, string>  $tableNames
     * @return array<string, array{row_count: int, size: int}>
     */
    protected function getTableStats(string $connectionName, string $database, array $tableNames): array
    {
        if ($tableNames === []) {
            return [];
        }

        $connection = DB::connection($connectionName);

        $stats = [];

        foreach ($tableNames as $tableName) {
            $escapedDatabase = str_replace('`', '``', $database);
            $escapedTable = str_replace('`', '``', $tableName);

            $rowCountResult = $connection->selectOne(
                "SELECT COUNT(*) as aggregate FROM `{$escapedDatabase}`.`{$escapedTable}`"
            );

            $rowCount = (int) ($rowCountResult->aggregate ?? 0);

            $sizeResult = $connection->selectOne(
                'SELECT DATA_LENGTH + INDEX_LENGTH as size_bytes
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                [$database, $tableName],
            );

            $size = (int) ($sizeResult->size_bytes ?? 0);

            $stats[$tableName] = [
                'row_count' => $rowCount,
                'size' => $size,
            ];
        }

        return $stats;
    }

    /**
     * Select the destination connection.
     *
     * If an array of connections is provided, the connection with the least
     * currently used data size is selected.
     */
    protected function selectDestinationConnection(): string
    {
        if (is_string($this->destinationConnection)) {
            return $this->destinationConnection;
        }

        if (! is_array($this->destinationConnection) || $this->destinationConnection === []) {
            throw new RuntimeException('Destination connection list is empty.');
        }

        $candidates = array_values(array_unique(array_filter(
            $this->destinationConnection,
            static fn (mixed $connection): bool => is_string($connection) && $connection !== ''
        )));

        if ($candidates === []) {
            throw new RuntimeException('Destination connection list is invalid.');
        }

        $selectedConnection = null;
        $smallestUsedSize = null;

        foreach ($candidates as $connection) {
            $connectionConfig = config("database.connections.{$connection}");

            if (! is_array($connectionConfig)) {
                throw new RuntimeException("Destination connection [{$connection}] is not configured.");
            }

            if (($connectionConfig['driver'] ?? null) !== 'mysql') {
                throw new RuntimeException("Destination connection [{$connection}] must use mysql.");
            }

            $usedSize = (int) DbCopyRow::query()
                ->join('db_copies', 'db_copies.id', '=', 'db_copy_rows.db_copy_id')
                ->where('db_copies.dest_connection', $connection)
                ->sum(DB::raw('COALESCE(db_copy_rows.dest_size, db_copy_rows.source_size, 0)'));

            if ($smallestUsedSize === null || $usedSize < $smallestUsedSize) {
                $smallestUsedSize = $usedSize;
                $selectedConnection = $connection;
            }
        }

        if ($selectedConnection === null) {
            throw new RuntimeException('Could not resolve destination connection.');
        }

        return $selectedConnection;
    }

    /**
     * Build command line arguments for MySQL CLI tools without logging credentials.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    protected function buildMysqlCliArgs(array $config, string $database): array
    {
        $args = [];

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $username = $config['username'] ?? $config['user'] ?? null;
        $password = $config['password'] ?? null;

        $args[] = '--host='.$host;
        $args[] = '--port='.$port;

        if ($username !== null) {
            $args[] = '--user='.$username;
        }

        if ($password !== null) {
            $args[] = '--password='.$password;
        }

        $args[] = '--database='.$database;

        return $args;
    }
}
