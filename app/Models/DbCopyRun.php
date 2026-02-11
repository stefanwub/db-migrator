<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DbCopyRun extends Model
{
    /** @use HasFactory<\Database\Factories\DbCopyRunFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'status',
        'source_system_db_connection',
        'source_system_db_name',
        'source_admin_app_connection',
        'source_admin_app_name',
        'source_db_connection',
        'dest_db_connections',
        'create_dest_db_on_laravel_cloud',
        'started_at',
        'finished_at',
        'created_by_user_id',
    ];

    /**
     * Get the casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dest_db_connections' => 'array',
            'create_dest_db_on_laravel_cloud' => 'boolean',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the DB copies that belong to this run.
     */
    public function copies(): HasMany
    {
        return $this->hasMany(DbCopy::class, 'db_copy_run_id');
    }

    /**
     * Get the user that created this run.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get current run duration in seconds.
     */
    public function durationSeconds(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }

        $end = $this->finished_at ?? now()->toImmutable();

        return $this->started_at->diffInSeconds($end);
    }

    /**
     * Get current run duration in milliseconds.
     */
    public function durationMilliseconds(): ?int
    {
        if ($this->started_at === null) {
            return null;
        }

        $end = $this->finished_at ?? now()->toImmutable();

        return $this->started_at->diffInMilliseconds($end);
    }

    /**
     * Get a human-readable run duration.
     */
    public function durationForDisplay(): ?string
    {
        $seconds = $this->durationSeconds();

        if ($seconds === null) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.'h';
        }

        if ($minutes > 0 || $hours > 0) {
            $parts[] = $minutes.'m';
        }

        $parts[] = $remainingSeconds.'s';

        return implode(' ', $parts);
    }

    /**
     * Sync run status based on the status of related copies.
     */
    public function syncStatusFromCopies(): void
    {
        $statuses = $this->copies()
            ->pluck('status')
            ->unique()
            ->values()
            ->all();

        if ($statuses === []) {
            return;
        }

        if (in_array('failed', $statuses, true)) {
            $this->update([
                'status' => 'failed',
                'started_at' => $this->started_at ?? now(),
                'finished_at' => now(),
            ]);

            return;
        }

        $nonTerminal = array_diff($statuses, ['succeeded']);

        if ($nonTerminal === []) {
            $this->update([
                'status' => 'succeeded',
                'started_at' => $this->started_at ?? now(),
                'finished_at' => now(),
            ]);

            return;
        }

        $this->update([
            'status' => 'running',
            'started_at' => $this->started_at ?? now(),
            'finished_at' => null,
        ]);
    }
}
