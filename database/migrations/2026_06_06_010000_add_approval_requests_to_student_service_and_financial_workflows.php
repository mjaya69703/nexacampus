<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'service_letter_requests',
            'student_leave_applications',
            'student_transfer_requests',
            'graduation_applications',
            'invoice_installment_requests',
        ] as $tableName) {
            if (! Schema::hasColumn($tableName, 'approval_request_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('approval_request_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('approval_requests')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'service_letter_requests',
            'student_leave_applications',
            'student_transfer_requests',
            'graduation_applications',
            'invoice_installment_requests',
        ] as $tableName) {
            if (Schema::hasColumn($tableName, 'approval_request_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('approval_request_id');
                });
            }
        }
    }
};
