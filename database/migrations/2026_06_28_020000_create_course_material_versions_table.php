<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_material_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_material_id')
                ->constrained('course_materials')
                ->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('change_type')->default('updated');
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('category')->nullable();
            $table->unsignedTinyInteger('meeting_number')->nullable();
            $table->boolean('is_published')->default(true);
            $table->json('files_snapshot')->nullable();
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['course_material_id', 'version_number'], 'course_material_versions_unique');
            $table->index(['course_material_id', 'created_at'], 'course_material_versions_material_created_idx');
        });

        $materials = DB::table('course_materials')->get();
        $now = now();

        foreach ($materials as $material) {
            $files = DB::table('course_material_files')
                ->where('course_material_id', $material->id)
                ->get(['file_name', 'file_type', 'file_size', 'download_count'])
                ->map(fn ($file) => [
                    'file_name' => $file->file_name,
                    'file_type' => $file->file_type,
                    'file_size' => $file->file_size,
                    'download_count' => $file->download_count,
                ])
                ->values()
                ->all();

            if (empty($files) && $material->file_name) {
                $files[] = [
                    'file_name' => $material->file_name,
                    'file_type' => $material->file_type,
                    'file_size' => $material->file_size,
                    'download_count' => $material->download_count,
                ];
            }

            DB::table('course_material_versions')->insert([
                'course_material_id' => $material->id,
                'version_number' => 1,
                'change_type' => 'created',
                'title' => $material->title,
                'description' => $material->description,
                'category' => $material->category,
                'meeting_number' => $material->meeting_number,
                'is_published' => (bool) $material->is_published,
                'files_snapshot' => json_encode($files),
                'change_summary' => 'Snapshot awal materi yang sudah ada.',
                'created_by' => $material->created_by ?? $material->uploaded_by,
                'created_at' => $material->created_at ?? $now,
                'updated_at' => $material->updated_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_material_versions');
    }
};
