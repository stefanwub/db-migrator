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
        Schema::create('db_copy_rows', function (Blueprint $table) {
            $table->id();

            $table->uuid('db_copy_id');

            $table->string('name');
            $table->string('dump_file_path');
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();

            $table->unsignedBigInteger('source_row_count')->nullable();
            $table->unsignedBigInteger('dest_row_count')->nullable();
            $table->unsignedBigInteger('source_size')->nullable();
            $table->unsignedBigInteger('dest_size')->nullable();

            $table->timestamps();

            $table->foreign('db_copy_id')
                ->references('id')
                ->on('db_copies')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('db_copy_rows');
    }
};
