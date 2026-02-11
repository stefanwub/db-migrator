<?php

namespace App\Jobs;

use App\Models\DbCopy;
use App\Models\DbCopyRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DispatchDbCopyRun implements ShouldQueue
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

        $dbCopyRun->update([
            'status' => 'running',
            'started_at' => $dbCopyRun->started_at ?? now(),
            'finished_at' => null,
        ]);

        try {
            $destinationConnections = $dbCopyRun->dest_db_connections;

            if (! is_array($destinationConnections) || $destinationConnections === []) {
                throw new RuntimeException('Run destination connections are missing.');
            }

            $systemCopy = $this->createDbCopy(
                dbCopyRun: $dbCopyRun,
                sourceConnection: $dbCopyRun->source_system_db_connection,
                sourceDatabase: $dbCopyRun->source_system_db_name,
                destinationDatabase: $dbCopyRun->source_system_db_name,
                destinationConnection: (string) $destinationConnections[0],
            );

            $adminAppCopy = $this->createDbCopy(
                dbCopyRun: $dbCopyRun,
                sourceConnection: $dbCopyRun->source_admin_app_connection,
                sourceDatabase: $dbCopyRun->source_admin_app_name,
                destinationDatabase: $dbCopyRun->source_admin_app_name,
                destinationConnection: (string) $destinationConnections[0],
            );

            Bus::chain([
                new CopyMysqlWithMydumperDynamicCreds(
                    dbCopyId: $systemCopy->id,
                    sourceConnection: $systemCopy->source_connection,
                    sourceDatabase: $systemCopy->source_db,
                    destinationConnection: $destinationConnections,
                    destinationDatabase: $systemCopy->dest_db,
                    threads: $this->threads,
                    recreateDestination: $this->recreateDestination,
                ),
                new CopyMysqlWithMydumperDynamicCreds(
                    dbCopyId: $adminAppCopy->id,
                    sourceConnection: $adminAppCopy->source_connection,
                    sourceDatabase: $adminAppCopy->source_db,
                    destinationConnection: $destinationConnections,
                    destinationDatabase: $adminAppCopy->dest_db,
                    threads: $this->threads,
                    recreateDestination: $this->recreateDestination,
                ),
                new DispatchDbCopyRunSourceDatabaseCopies(
                    dbCopyRunId: $dbCopyRun->id,
                    createdByUserId: $this->createdByUserId,
                    threads: $this->threads,
                    recreateDestination: $this->recreateDestination,
                ),
            ])->dispatch();
        } catch (Throwable $e) {
            $dbCopyRun->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a queued DB copy for the run.
     */
    protected function createDbCopy(
        DbCopyRun $dbCopyRun,
        string $sourceConnection,
        string $sourceDatabase,
        string $destinationDatabase,
        string $destinationConnection,
    ): DbCopy {
        return DbCopy::query()->create([
            'id' => (string) Str::uuid(),
            'status' => 'queued',
            'progress' => null,
            'source_connection' => $sourceConnection,
            'source_db' => $sourceDatabase,
            'dest_connection' => $destinationConnection,
            'dest_db' => $destinationDatabase,
            'callback_url' => '',
            'created_by_user_id' => $this->createdByUserId,
            'db_copy_run_id' => $dbCopyRun->id,
        ]);
    }
}
