<?php

namespace App\Jobs;

use App\Models\DbCopy;
use App\Models\DbCopyRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DispatchDbCopyRunSourceDatabaseCopies implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $dbCopyRunId,
        public int $createdByUserId,
        public int $threads = 8,
        public bool $recreateDestination = true,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $dbCopyRun = DbCopyRun::query()->find($this->dbCopyRunId);

        if ($dbCopyRun === null) {
            return;
        }

        try {
            $destinationConnections = $dbCopyRun->dest_db_connections;

            if (! is_array($destinationConnections) || $destinationConnections === []) {
                throw new RuntimeException('Run destination connections are missing.');
            }

            $databaseNames = $this->loadSourceClusterDatabaseNames($dbCopyRun);

            foreach ($databaseNames as $databaseName) {
                if ($databaseName === $dbCopyRun->source_system_db_name || $databaseName === $dbCopyRun->source_admin_app_name) {
                    continue;
                }

                $dbCopy = DbCopy::query()->create([
                    'id' => (string) Str::uuid(),
                    'status' => 'queued',
                    'progress' => null,
                    'source_connection' => $dbCopyRun->source_db_connection,
                    'source_db' => $databaseName,
                    'dest_connection' => (string) $destinationConnections[0],
                    'dest_db' => $databaseName,
                    'callback_url' => '',
                    'created_by_user_id' => $this->createdByUserId,
                    'db_copy_run_id' => $dbCopyRun->id,
                ]);

                CopyMysqlWithMydumperDynamicCreds::dispatch(
                    dbCopyId: $dbCopy->id,
                    sourceConnection: $dbCopy->source_connection,
                    sourceDatabase: $dbCopy->source_db,
                    destinationConnection: $destinationConnections,
                    destinationDatabase: $dbCopy->dest_db,
                    threads: $this->threads,
                    recreateDestination: $this->recreateDestination,
                );
            }
        } catch (Throwable $e) {
            $dbCopyRun->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Load all application database names from the source cluster.
     *
     * @return array<int, string>
     */
    protected function loadSourceClusterDatabaseNames(DbCopyRun $dbCopyRun): array
    {
        $connectionConfig = config("database.connections.{$dbCopyRun->source_db_connection}");

        if (! is_array($connectionConfig)) {
            throw new RuntimeException("Source cluster connection [{$dbCopyRun->source_db_connection}] is not configured.");
        }

        $systemDatabases = [
            'information_schema',
            'mysql',
            'performance_schema',
            'sys',
            $dbCopyRun->source_system_db_name,
            $dbCopyRun->source_admin_app_name,
        ];

        $escaped = implode(
            ',',
            array_map(
                static fn (string $value): string => "'".str_replace("'", "''", $value)."'",
                $systemDatabases
            )
        );

        $rows = DB::connection($dbCopyRun->source_db_connection)
            ->select("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME NOT IN ({$escaped}) ORDER BY SCHEMA_NAME");

        $databaseNames = [];

        foreach ($rows as $row) {
            $name = $row->SCHEMA_NAME ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $databaseNames[] = $name;
        }

        return $databaseNames;
    }
}
