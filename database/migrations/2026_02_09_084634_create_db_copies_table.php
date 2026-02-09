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
        Schema::create('db_copies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('status', 20);
            $table->unsignedTinyInteger('progress')->nullable();

            $table->string('source_db');
            $table->string('dest_db');

            $table->string('callback_url');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->text('last_error')->nullable();

            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_copies');
    }
};
