<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('db_copy_runs', function (Blueprint $table) {
            $table->string('status', 20)->default('queued')->after('id');
            $table->timestamp('started_at')->nullable()->after('dest_db_connections');
            $table->timestamp('finished_at')->nullable()->after('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_copy_runs', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'started_at',
                'finished_at',
            ]);
        });
    }
};
