<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            // Multi-scope targeting
            $table->string('target_type'); // Cast to AnnouncementTargetType enum
            $table->unsignedBigInteger('target_id')->nullable(); // NULL jika global

            // Creator info
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            // Content
            $table->string('title');
            $table->longText('content'); // HTML from Jodit editor

            // Metadata
            $table->string('priority')->default('normal'); // Cast to AnnouncementPriority enum
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();

            // Optional single attachment
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_type')->nullable();
            $table->integer('attachment_size')->nullable(); // in bytes

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            // Indexes for performance
            $table->index(['target_type', 'target_id']);
            $table->index(['is_published', 'published_at']);
            $table->index(['priority', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
