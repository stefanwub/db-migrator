<?php

namespace App\Http\Controllers;

use App\Http\Requests\DbCopyRunStoreRequest;
use App\Jobs\DispatchDbCopyRun;
use App\Models\DbCopyRun;
use Illuminate\Http\RedirectResponse;

class DbCopyRunController extends Controller
{
    /**
     * Store a newly created DB copy run.
     */
    public function store(DbCopyRunStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $run = DbCopyRun::query()->create([
            'status' => 'queued',
            'source_system_db_connection' => $validated['source_system_db_connection'],
            'source_system_db_name' => $validated['source_system_db_name'],
            'source_admin_app_connection' => $validated['source_admin_app_connection'],
            'source_admin_app_name' => $validated['source_admin_app_name'],
            'source_db_connection' => $validated['source_db_connection'],
            'dest_db_connections' => $validated['dest_db_connections'],
            'create_dest_db_on_laravel_cloud' => (bool) ($validated['createDestDbOnLaravelCloud'] ?? false),
            'started_at' => null,
            'finished_at' => null,
            'created_by_user_id' => (int) $request->user()->id,
        ]);

        DispatchDbCopyRun::dispatch(
            dbCopyRunId: $run->id,
            createdByUserId: (int) $request->user()->id,
            threads: (int) ($validated['threads'] ?? 8),
            recreateDestination: (bool) ($validated['recreateDestination'] ?? true),
            createDestDbOnLaravelCloud: (bool) ($validated['createDestDbOnLaravelCloud'] ?? false),
        );

        return redirect()
            ->route('db-copy-runs.index')
            ->with('success', 'Run created and queued.');
    }
}
