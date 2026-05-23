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
        Schema::table('side_page_views', function (Blueprint $table) {
            $table->index(['module', 'path', 'ip_hash', 'created_at'], 'idx_pv_module_path_ip_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('side_page_views', function (Blueprint $table) {
            //
        });
    }
};
