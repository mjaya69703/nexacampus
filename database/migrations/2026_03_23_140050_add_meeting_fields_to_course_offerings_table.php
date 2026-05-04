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
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->unsignedSmallInteger('total_meetings')->nullable()->after('credits');
            $table->date('class_start_date')->nullable()->after('total_meetings');
            $table->date('class_end_date')->nullable()->after('class_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropColumn(['total_meetings', 'class_start_date', 'class_end_date']);
        });
    }
};
