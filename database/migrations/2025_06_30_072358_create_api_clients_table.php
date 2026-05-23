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
    Schema::create('api_clients', function (Blueprint $table) {
      $table->id();
      $table->uuid('uuid')->unique();
      $table->string('name');
      $table->text('description')->nullable();
      $table->string('api_key')->unique();
      $table->json('abilities')->nullable();
      $table->json('allowed_domains')->nullable();
      $table->boolean('active')->default(true);
      $table->timestamps();
      $table->softDeletes();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('api_clients');
  }
};
