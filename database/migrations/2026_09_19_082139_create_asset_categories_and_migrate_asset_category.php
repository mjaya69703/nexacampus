<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori aset jadi master terpisah (Batch aset grup+unit lanjutan).
     *
     * - tabel asset_categories (name, code/slug, desc, is_active)
     * - seed 5 nilai enum lama sebagai baris awal
     * - campus_assets += category_id, backfill by string, drop kolom string
     *
     * down() mengembalikan kolom string berisi nama kategori.
     */
    private const LEGACY = [
        'electronics' => 'Elektronik & IT',
        'audio_video' => 'Audio Video & Multimedia',
        'furniture' => 'Furniture / Mebel',
        'lab' => 'Peralatan Laboratorium',
        'general' => 'Umum / Lainnya',
    ];

    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('desc')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        foreach (self::LEGACY as $code => $name) {
            DB::table('asset_categories')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        Schema::table('campus_assets', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('name')->constrained('asset_categories')->nullOnDelete();
        });

        // Backfill: string lama -> id kategori (nilai asing jadi "Lainnya").
        $map = DB::table('asset_categories')->pluck('id', 'code')->all();

        if (! isset($map['general'])) {
            $map['general'] = DB::table('asset_categories')->insertGetId([
                'name' => 'Umum / Lainnya', 'code' => 'general',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('campus_assets')->orderBy('id')->chunkById(500, function ($assets) use ($map) {
            foreach ($assets as $asset) {
                $categoryId = $map[$asset->category] ?? $map['general'];

                if ($asset->category !== null && $asset->category !== '' && ! isset($map[$asset->category])) {
                    echo "Kategori asing '{$asset->category}' pada aset id {$asset->id} dipetakan ke 'general'.\n";
                }

                DB::table('campus_assets')->where('id', $asset->id)->update(['category_id' => $categoryId]);
            }
        });

        Schema::table('campus_assets', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('campus_assets', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
        });

        $names = DB::table('asset_categories')->pluck('name', 'id')->all();

        DB::table('campus_assets')->whereNotNull('category_id')->orderBy('id')->chunkById(500, function ($assets) use ($names) {
            foreach ($assets as $asset) {
                DB::table('campus_assets')->where('id', $asset->id)->update([
                    'category' => $names[$asset->category_id] ?? 'general',
                ]);
            }
        });

        Schema::table('campus_assets', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('asset_categories');
    }
};
