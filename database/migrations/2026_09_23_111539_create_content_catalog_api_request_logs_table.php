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
        Schema::create('content_catalog_api_request_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('operation');
            $table->string('purpose')->nullable();
            $table->string('api_key_last_four', 4)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_path');
            $table->timestamp('requested_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_catalog_api_request_logs');
    }
};
