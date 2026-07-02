<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_key');
            $table->string('channel', 40)->default('whatsapp');
            $table->string('title');
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->json('variables')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['event_key', 'channel']);
            $table->index(['channel', 'is_active']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_key');
            $table->string('channel', 40);
            $table->string('provider', 60)->nullable();
            $table->string('status', 40)->default('queued');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->nullableMorphs('source');
            $table->string('provider_message_id')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['channel', 'status']);
            $table->index(['event_key', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');
    }
};
