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
        Schema::table('content_catalog_api_settings', function (Blueprint $table): void {
            $table->boolean('disable_after_enabled')->default(false);
            $table->unsignedInteger('disable_after_hours')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_catalog_api_settings', function (Blueprint $table): void {
            $table->dropColumn(['disable_after_enabled', 'disable_after_hours']);
        });
    }
};
