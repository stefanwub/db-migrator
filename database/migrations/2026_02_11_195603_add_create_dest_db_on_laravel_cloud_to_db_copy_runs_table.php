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
            $table->boolean('create_dest_db_on_laravel_cloud')
                ->default(false)
                ->after('dest_db_connections');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_copy_runs', function (Blueprint $table) {
            $table->dropColumn('create_dest_db_on_laravel_cloud');
        });
    }
};
