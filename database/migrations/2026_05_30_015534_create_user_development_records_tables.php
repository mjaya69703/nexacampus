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
        Schema::create('user_development_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // certification, training, workshop, seminar, award, license
            $table->string('title');
            $table->string('organizer')->nullable();
            $table->string('credential_number')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null means ongoing or single day (start_date)
            $table->date('expires_at')->nullable(); // null means no expiration
            $table->decimal('cost', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'start_date']);
        });

        Schema::create('user_development_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_development_record_id')->constrained('user_development_records')->cascadeOnDelete();
            $table->string('document_type')->default('certificate'); // certificate, transcript, receipt, photo
            $table->string('file_path');
            $table->string('file_name');
            $table->integer('file_size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_development_attachments');
        Schema::dropIfExists('user_development_records');
    }
};
