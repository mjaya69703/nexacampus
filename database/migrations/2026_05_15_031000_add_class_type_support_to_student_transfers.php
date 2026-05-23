<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('class_type')->nullable()->after('current_semester');
        });

        Schema::table('student_transfer_requests', function (Blueprint $table) {
            $table->string('from_class_type')->nullable()->after('transfer_type');
            $table->string('to_class_type')->nullable()->after('from_class_type');
        });
    }

    public function down(): void
    {
        Schema::table('student_transfer_requests', function (Blueprint $table) {
            $table->dropColumn(['from_class_type', 'to_class_type']);
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn('class_type');
        });
    }
};
