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
        Schema::create('contacts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('nama', 191);
            $table->string('email', 191);
            $table->string('jenis', 32)->index();
            $table->text('pesan');
            $table->string('ip_hash', 64)->nullable()->index();
            $table->string('user_agent', 255)->nullable();
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
        Schema::dropIfExists('contacts');
    }
};
