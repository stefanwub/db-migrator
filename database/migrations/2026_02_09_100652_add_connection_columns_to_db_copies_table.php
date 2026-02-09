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
            $table->string('source_connection')->after('progress');
            $table->string('dest_connection')->after('source_connection');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_copies', function (Blueprint $table) {
            $table->dropColumn([
                'source_connection',
                'dest_connection',
            ]);
        });
    }
};
