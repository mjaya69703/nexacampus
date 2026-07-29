<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->foreignId('campus_location_id')->nullable()->after('id')->constrained('campus_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropForeign(['campus_location_id']);
            $table->dropColumn('campus_location_id');
        });
    }
};
