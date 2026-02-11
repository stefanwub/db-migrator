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
        Schema::create('db_copy_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source_system_db_connection');
            $table->string('source_system_db_name');
            $table->string('source_admin_app_connection');
            $table->string('source_admin_app_name');
            $table->string('source_db_connection');
            $table->json('dest_db_connections');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_copy_runs');
    }
};
