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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            
            // Docker configuration
            $table->string('db_name');
            $table->string('db_username')->nullable();
            $table->string('db_password')->nullable();
            $table->string('docker_app_container')->nullable();
            $table->string('docker_db_container')->nullable();
            $table->string('docker_status')->default('stopped'); // running, stopped, creating, error
            $table->integer('app_port')->nullable();
            $table->integer('db_port')->nullable();
            
            // Features / Modules
            $table->boolean('api_enabled')->default(false);
            $table->boolean('website_enabled')->default(false);
            $table->json('modules')->nullable(); // list of active modules
            
            // Status and owner
            $table->boolean('is_active')->default(true);
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            
            // Metadata
            $table->json('settings')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('last_health_check')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
