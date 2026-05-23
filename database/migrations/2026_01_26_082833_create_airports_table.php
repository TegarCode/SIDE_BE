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
        Schema::create('airports', function (Blueprint $table) {
            $table->id();
            $table->string('iata')->nullable();
            $table->string('icao')->nullable();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->default('Indonesia');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();
        });

    }

    
    
    public function down(): void
    {
        Schema::dropIfExists('airports');
    }
};
