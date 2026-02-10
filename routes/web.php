<?php

use App\Models\DbCopy;
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
        ->paginate(20);

    return Inertia::render('DbCopies/Index', [
        'dbCopies' => $dbCopies,
    ]);
})->middleware(['auth', 'verified'])->name('db-copies.index');

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
