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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();

            $table->foreignId('building_id')
                ->nullable()
                ->constrained('buildings')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('name');
            $table->string('code', 50)->nullable()->unique();

            $table->string('floor')->nullable();
            $table->unsignedInteger('capacity')->nullable();

            $table->enum('type', [
                'Classroom',
                'Laboratory',
                'Auditorium',
                'Office',
                'Meeting Room',
                'Library',
                'Other',
            ])->default('Classroom');

            $table->boolean('is_active')->default(true);
            $table->text('desc')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
