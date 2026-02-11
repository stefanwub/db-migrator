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
        Schema::table('db_copies', function (Blueprint $table) {
            $table->foreignId('db_copy_run_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('db_copy_runs')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_copies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('db_copy_run_id');
        });
    }
};
