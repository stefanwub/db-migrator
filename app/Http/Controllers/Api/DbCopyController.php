<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DbCopyStoreRequest;
use App\Jobs\CopyMysqlWithMydumperDynamicCreds;
use App\Models\DbCopy;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class DbCopyController extends Controller
{
    /**
     * Store a newly created DB copy request.
     */
    public function store(DbCopyStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        $dbCopy = DbCopy::create([
            'id' => (string) Str::uuid(),
            'status' => 'queued',
            'progress' => null,
            'source_connection' => $validated['source']['connection'],
            'source_db' => $validated['source']['database'],
            'dest_connection' => $validated['destination']['connection'],
            'dest_db' => $validated['destination']['database'],
            'callback_url' => $validated['callback_url'],
            'created_by_user_id' => $user?->id,
        ]);

        $threads = $validated['threads'] ?? 8;
        $recreateDestination = (bool) ($validated['recreateDestination'] ?? true);

        CopyMysqlWithMydumperDynamicCreds::dispatch(
            dbCopyId: $dbCopy->id,
            sourceConnection: $validated['source']['connection'],
            sourceDatabase: $validated['source']['database'],
            destinationConnection: $validated['destination']['connection'],
            destinationDatabase: $validated['destination']['database'],
            threads: (int) $threads,
            recreateDestination: $recreateDestination,
        );

        return response()->json([
            'id' => $dbCopy->id,
            'status' => $dbCopy->status,
        ], 201);
    }

    /**
     * Display the specified DB copy status.
     */
    public function show(Request $request, DbCopy $dbCopy): JsonResponse
    {
        $user = $request->user();

        if ($user === null || $user->id !== $dbCopy->created_by_user_id) {
            abort(403);
        }

        return response()->json([
            'id' => $dbCopy->id,
            'status' => $dbCopy->status,
            'progress' => $dbCopy->progress,
            'source_connection' => $dbCopy->source_connection,
            'source_db' => $dbCopy->source_db,
            'dest_connection' => $dbCopy->dest_connection,
            'dest_db' => $dbCopy->dest_db,
            'callback_url' => $dbCopy->callback_url,
            'started_at' => $dbCopy->started_at?->toIso8601String(),
            'finished_at' => $dbCopy->finished_at?->toIso8601String(),
            'last_error' => $dbCopy->last_error,
            'created_by_user_id' => $dbCopy->created_by_user_id,
            'created_at' => $dbCopy->created_at?->toIso8601String(),
            'updated_at' => $dbCopy->updated_at?->toIso8601String(),
        ]);
    }
}
