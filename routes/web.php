<?php

use App\Http\Controllers\DbCopyRunController;
use App\Models\DbCopy;
use App\Models\DbCopyRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return redirect()->route('db-copies.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('db-copies', function (Request $request) {
    $dbCopies = DbCopy::query()
        ->where('created_by_user_id', $request->user()?->id)
        ->latest()
        ->paginate(20)
        ->through(function (DbCopy $dbCopy) {
            return [
                'id' => $dbCopy->id,
                'status' => $dbCopy->status,
                'progress' => $dbCopy->progress,
                'source_connection' => $dbCopy->source_connection,
                'source_db' => $dbCopy->source_db,
                'dest_connection' => $dbCopy->dest_connection,
                'dest_db' => $dbCopy->dest_db,
                'callback_url' => $dbCopy->callback_url,
                'started_at' => $dbCopy->started_at,
                'finished_at' => $dbCopy->finished_at,
                'duration_seconds' => $dbCopy->durationSeconds(),
                'duration_milliseconds' => $dbCopy->durationMilliseconds(),
                'duration_human' => $dbCopy->durationForDisplay(),
                'last_error' => $dbCopy->last_error,
                'created_at' => $dbCopy->created_at,
                'updated_at' => $dbCopy->updated_at,
            ];
        });

    return Inertia::render('DbCopies/Index', [
        'dbCopies' => $dbCopies,
    ]);
})->middleware(['auth', 'verified'])->name('db-copies.index');

Route::post('db-copy-runs', [DbCopyRunController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('db-copy-runs.store');

Route::get('db-copy-runs', function (Request $request) {
    $userId = $request->user()?->id;

    $dbCopyRuns = DbCopyRun::query()
        ->where(function ($query) use ($userId) {
            $query->where('created_by_user_id', $userId)
                ->orWhereNull('created_by_user_id')
                ->orWhereHas('copies', function ($copyQuery) use ($userId) {
                    $copyQuery->where('created_by_user_id', $userId);
                });
        })
        ->withCount([
            'copies as copies_count' => function ($query) use ($userId) {
                $query->where('created_by_user_id', $userId);
            },
        ])
        ->latest()
        ->paginate(20)
        ->through(function (DbCopyRun $dbCopyRun) {
            return [
                'id' => $dbCopyRun->id,
                'source_system_db_connection' => $dbCopyRun->source_system_db_connection,
                'source_system_db_name' => $dbCopyRun->source_system_db_name,
                'source_admin_app_connection' => $dbCopyRun->source_admin_app_connection,
                'source_admin_app_name' => $dbCopyRun->source_admin_app_name,
                'source_db_connection' => $dbCopyRun->source_db_connection,
                'dest_db_connections' => $dbCopyRun->dest_db_connections,
                'status' => $dbCopyRun->status,
                'started_at' => $dbCopyRun->started_at,
                'finished_at' => $dbCopyRun->finished_at,
                'duration_seconds' => $dbCopyRun->durationSeconds(),
                'duration_milliseconds' => $dbCopyRun->durationMilliseconds(),
                'duration_human' => $dbCopyRun->durationForDisplay(),
                'copies_count' => $dbCopyRun->copies_count,
                'created_by_user_id' => $dbCopyRun->created_by_user_id,
                'created_at' => $dbCopyRun->created_at,
                'updated_at' => $dbCopyRun->updated_at,
            ];
        });

    return Inertia::render('DbCopyRuns/Index', [
        'dbCopyRuns' => $dbCopyRuns,
        'availableConnections' => array_keys(config('database.connections', [])),
    ]);
})->middleware(['auth', 'verified'])->name('db-copy-runs.index');

Route::get('db-copy-runs/{dbCopyRun}', function (Request $request, DbCopyRun $dbCopyRun) {
    $userId = $request->user()?->id;

    if ($dbCopyRun->created_by_user_id !== null && $dbCopyRun->created_by_user_id !== $userId) {
        abort(403);
    }

    $copies = $dbCopyRun->copies()
        ->where('created_by_user_id', $userId)
        ->latest()
        ->get();

    return Inertia::render('DbCopyRuns/Show', [
        'dbCopyRun' => [
            'id' => $dbCopyRun->id,
            'source_system_db_connection' => $dbCopyRun->source_system_db_connection,
            'source_system_db_name' => $dbCopyRun->source_system_db_name,
            'source_admin_app_connection' => $dbCopyRun->source_admin_app_connection,
            'source_admin_app_name' => $dbCopyRun->source_admin_app_name,
            'source_db_connection' => $dbCopyRun->source_db_connection,
            'dest_db_connections' => $dbCopyRun->dest_db_connections,
            'status' => $dbCopyRun->status,
            'started_at' => $dbCopyRun->started_at,
            'finished_at' => $dbCopyRun->finished_at,
            'duration_seconds' => $dbCopyRun->durationSeconds(),
            'duration_milliseconds' => $dbCopyRun->durationMilliseconds(),
            'duration_human' => $dbCopyRun->durationForDisplay(),
            'created_by_user_id' => $dbCopyRun->created_by_user_id,
            'created_at' => $dbCopyRun->created_at,
            'updated_at' => $dbCopyRun->updated_at,
        ],
        'copies' => $copies->map(function (DbCopy $dbCopy) {
            return [
                'id' => $dbCopy->id,
                'status' => $dbCopy->status,
                'progress' => $dbCopy->progress,
                'source_connection' => $dbCopy->source_connection,
                'source_db' => $dbCopy->source_db,
                'dest_connection' => $dbCopy->dest_connection,
                'dest_db' => $dbCopy->dest_db,
                'started_at' => $dbCopy->started_at,
                'finished_at' => $dbCopy->finished_at,
                'duration_human' => $dbCopy->durationForDisplay(),
                'last_error' => $dbCopy->last_error,
                'created_at' => $dbCopy->created_at,
                'updated_at' => $dbCopy->updated_at,
            ];
        }),
    ]);
})->middleware(['auth', 'verified'])->name('db-copy-runs.show');

Route::get('db-copies/{dbCopy}', function (Request $request, DbCopy $dbCopy) {
    if ($request->user()?->id !== $dbCopy->created_by_user_id) {
        abort(403);
    }

    $dbCopy->load(['rows' => function ($query) {
        $query->orderBy('name');
    }]);

    return Inertia::render('DbCopies/Show', [
        'dbCopy' => [
            'id' => $dbCopy->id,
            'status' => $dbCopy->status,
            'progress' => $dbCopy->progress,
            'source_connection' => $dbCopy->source_connection,
            'source_db' => $dbCopy->source_db,
            'dest_connection' => $dbCopy->dest_connection,
            'dest_db' => $dbCopy->dest_db,
            'total_source_size' => $dbCopy->total_source_size,
            'callback_url' => $dbCopy->callback_url,
            'started_at' => $dbCopy->started_at,
            'finished_at' => $dbCopy->finished_at,
            'duration_seconds' => $dbCopy->durationSeconds(),
            'duration_milliseconds' => $dbCopy->durationMilliseconds(),
            'duration_human' => $dbCopy->durationForDisplay(),
            'last_error' => $dbCopy->last_error,
            'created_at' => $dbCopy->created_at,
            'updated_at' => $dbCopy->updated_at,
        ],
        'rows' => $dbCopy->rows->map(function ($row) {
            return [
                'id' => $row->id,
                'name' => $row->name,
                'dump_file_path' => $row->dump_file_path,
                'status' => $row->status,
                'error_message' => $row->error_message,
                'source_row_count' => $row->source_row_count,
                'dest_row_count' => $row->dest_row_count,
                'source_size' => $row->source_size,
                'dest_size' => $row->dest_size,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }),
        'source_total_size_bytes' => $dbCopy->total_source_size,
    ]);
})->middleware(['auth', 'verified'])->name('db-copies.show');

require __DIR__.'/settings.php';
