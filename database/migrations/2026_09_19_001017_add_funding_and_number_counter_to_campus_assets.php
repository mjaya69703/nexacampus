<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Batch aset grup+unit: sumber dana per unit + counter nomor urut/tahun.
     */
    public function up(): void
    {
        Schema::table('campus_assets', function (Blueprint $table) {
            $table->string('funding_source', 50)->nullable()->after('purchase_cost');
            $table->string('funding_detail')->nullable()->after('funding_source');
        });

        Schema::create('asset_number_counters', function (Blueprint $table) {
            $table->string('year', 4)->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_number_counters');

        Schema::table('campus_assets', function (Blueprint $table) {
            $table->dropColumn(['funding_source', 'funding_detail']);
        });
    }
};
