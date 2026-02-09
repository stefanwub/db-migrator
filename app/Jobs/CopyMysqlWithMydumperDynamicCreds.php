<?php

namespace App\Jobs;

use App\Models\DbCopy;
use App\Services\DbCopyWebhookNotifier;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
        public string $destinationConnection,
        public string $destinationDatabase,
        public int $threads = 8,
        public bool $recreateDestination = true,
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
            $this->markRunning($dbCopy);
            $notifier->notify($dbCopy);

            $this->prepareDestinationDatabase();
            $this->runMydumperAndMyloader();

            $this->markSucceeded($dbCopy);
            $notifier->notify($dbCopy);
        } catch (Throwable $e) {
            $this->markFailed($dbCopy, $e);

            try {
                $notifier->notify($dbCopy);
            } catch (Throwable) {
                //
            }
        }
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

        if ($this->recreateDestination) {
            DB::connection($serverConnectionName)
                ->statement("DROP DATABASE IF EXISTS `{$escapedDatabase}`");
        }

        DB::connection($serverConnectionName)
            ->statement("CREATE DATABASE IF NOT EXISTS `{$escapedDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $destinationConfig['database'] = $this->destinationDatabase;

        config([
            "database.connections.{$this->destinationConnection}" => $destinationConfig,
        ]);

        DB::purge($this->destinationConnection);
    }

    /**
     * Run the mydumper and myloader commands to copy the database.
     */
    protected function runMydumperAndMyloader(): void
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

        $sourceArgs = $this->buildMysqlCliArgs($sourceConfig, $this->sourceDatabase);

        $mydumperCommand = array_merge(
            ['mydumper'],
            $sourceArgs,
            [
                '--threads='.$this->threads,
                '--outputdir='.$dumpDirectory,
                '--trx-consistency-only',
                '--less-locking',
                '--no-locks',
                '--no-schemas',
                '--skip-tz-utc'
            ],
        );

        $mydumperResult = Process::timeout(0)->run($mydumperCommand);

        if ($mydumperResult->failed()) {
            throw new RuntimeException('mydumper failed with error: '.$mydumperResult->errorOutput());
        }

        $destinationArgs = $this->buildMysqlCliArgs($destinationConfig, $this->destinationDatabase);

        $myloaderCommand = array_merge(
            ['myloader'],
            $destinationArgs,
            [
                '--threads='.$this->threads,
                '--directory='.$dumpDirectory,
            ],
        );

        $myloaderResult = Process::timeout(0)->run($myloaderCommand);

        if ($myloaderResult->failed()) {
            throw new RuntimeException('myloader failed with error: '.$myloaderResult->errorOutput());
        }
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
