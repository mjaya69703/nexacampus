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
        Schema::create('systems', function (Blueprint $table) {
            $table->id();
            // App Settings
            $table->string('app_name');
            $table->string('app_version');
            $table->string('app_description');
            $table->string('app_url');
            $table->string('app_email');

            // Logo Settings
            $table->string('app_favicon')->default('favicon.png');
            $table->string('app_logo_vertikal')->default('logo-vertikal.png');
            $table->string('app_logo_horizontal')->default('logo-horizontal.png');

            // System Settings
            $table->boolean('is_installed')->default(false);          // INSTALLATION STATUS
            $table->boolean('maintenance_mode')->default(false);      // MAINTENANCE MODE
            $table->boolean('enable_captcha')->default(false);        // CAPTCHA LOGIN
            $table->integer('max_login_attempts')->default(5);        // MAX LOGIN ATTEMPTS
            $table->integer('login_decay_seconds')->default(60);      // TIMEOUT AFTER MAX LOGIN ATTEMPTS

            // Audit Tracking
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });

        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            // Campus Identity
            $table->string('name');
            $table->string('phone');
            // $table->string('faximile');
            $table->string('whatsapp');
            $table->string('email_info');
            $table->string('email_humas');
            $table->string('domain');

            // Academic Settings
            $table->string('tahun_akademik_id')->nullable();

            // Logo Settings
            $table->string('favicon')->default('favicon.png');
            $table->string('logo_vertikal')->default('logo-vertikal.png');
            $table->string('logo_horizontal')->default('logo-horizontal.png');

            // Address Settings
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Social Media Settings
            $table->string('tiktok')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('xtwitter')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();

            // Audit Tracking
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
        Schema::dropIfExists('systems');
        Schema::dropIfExists('campuses');
    }
};
