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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Identitas dasar
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('photo')->default('default.jpg');
            $table->string('username')->nullable()->unique();

            // Detail Kontak
            $table->string('phone')->unique();
            $table->string('email')->unique();
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('linkedin')->nullable();

            // Nomor Identitas
            $table->string('identity_number')->nullable()->unique();

            // Biodata dasar (pakai referensi untuk konsistensi)
            $table->enum('religion', ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu'])->nullable();
            $table->enum('blood_type', ['A', 'B', 'AB', 'O'])->nullable();
            $table->enum('gender', ['Laki-laki', 'Perempuan'])->nullable();
            $table->enum('citizenship', ['WNI', 'WNA'])->nullable();

            $table->string('height')->nullable();
            $table->string('weight')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->date('date_of_birth')->nullable();

            // Keamanan
            $table->string('code')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);    // Is Active
            $table->boolean('fst_setup')->default(false);   // First Setup Account
            $table->boolean('tfa_setup')->default(false);   // Two Factor Authentication

            // Google auth
            $table->string('google_id')->nullable();
            $table->string('google_token')->nullable();
            $table->string('remember_token')->nullable();
            $table->string('google_refresh_token')->nullable();

            // Audit
            $table->softDeletes();
            $table->timestamps();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
