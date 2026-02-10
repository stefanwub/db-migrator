<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DbCopyRow extends Model
{
    /** @use HasFactory<\Database\Factories\DbCopyRowFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'db_copy_id',
        'name',
        'dump_file_path',
        'status',
        'error_message',
        'source_row_count',
        'dest_row_count',
        'source_size',
        'dest_size',
    ];

    /**
     * Get the DB copy that this row belongs to.
     */
    public function dbCopy(): BelongsTo
    {
        return $this->belongsTo(DbCopy::class);
    }
}
