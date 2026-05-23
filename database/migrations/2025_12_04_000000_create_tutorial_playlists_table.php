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
        Schema::create('tutorial_playlists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->text('desc');
            $table->string('url', 2048);
            $table->string('thumbnail', 2048);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tutorial_playlists');
    }
};
