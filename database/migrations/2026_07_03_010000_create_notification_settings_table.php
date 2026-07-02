<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('whatsapp_provider', 40)->default('official_cloud_api');
            $table->text('official_config')->nullable();
            $table->text('unofficial_config')->nullable();
            $table->string('fallback_channel', 40)->default('in_app');
            $table->unsignedTinyInteger('retry_attempts')->default(3);
            $table->unsignedSmallInteger('timeout_seconds')->default(15);
            $table->string('last_health_status', 40)->nullable();
            $table->text('last_health_message')->nullable();
            $table->timestamp('last_health_checked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
