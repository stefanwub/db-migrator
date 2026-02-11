<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DbCopy extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The primary key type.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'status',
        'progress',
        'source_connection',
        'source_db',
        'dest_connection',
        'dest_db',
        'total_source_size',
        'callback_url',
        'started_at',
        'finished_at',
        'last_error',
        'created_by_user_id',
        'db_copy_run_id',
    ];

    /**
     * Get the casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'total_source_size' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the user that created the DB copy.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the rows associated with this DB copy.
     */
    public function rows(): HasMany
    {
        return $this->hasMany(DbCopyRow::class);
    }

    /**
     * Get the run associated with this DB copy.
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(DbCopyRun::class, 'db_copy_run_id');
    }

    /**
     * Get the duration of the copy in seconds, if available.
     */
    public function durationSeconds(): ?int
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->finished_at);
    }

    /**
     * Get the duration of the copy in seconds, if available.
     */
    public function durationMilliseconds(): ?int
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return $this->started_at->diffInMilliseconds($this->finished_at);
    }

    /**
     * Get a human-readable duration between start and finish.
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
}
