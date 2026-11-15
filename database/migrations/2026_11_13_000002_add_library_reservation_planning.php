<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_reservations', function (Blueprint $table) {
            $table->foreignId('book_loan_id')
                ->nullable()
                ->after('book_copy_id')
                ->constrained('book_loans')
                ->nullOnDelete();
            $table->date('pickup_planned_at')->nullable()->after('reserved_at');
        });

        Schema::table('book_loans', function (Blueprint $table) {
            $table->unsignedTinyInteger('renewal_count')->default(0)->after('status');
            $table->dateTime('renewed_at')->nullable()->after('renewal_count');
        });
    }

    public function down(): void
    {
        Schema::table('book_loans', function (Blueprint $table) {
            $table->dropColumn(['renewal_count', 'renewed_at']);
        });

        Schema::table('book_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('book_loan_id');
            $table->dropColumn('pickup_planned_at');
        });
    }
};
