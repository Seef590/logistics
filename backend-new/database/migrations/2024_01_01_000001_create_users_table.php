<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('password');
            $table->enum('role', ['admin','expediteur','livreur','destinataire','voyageur']);
            $table->string('city');
            $table->float('rating')->default(0);
            $table->integer('rating_count')->default(0);
            $table->boolean('is_online')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->enum('verification_status', ['pending','approved','rejected'])->default('pending');
            $table->string('cin')->nullable();
            $table->string('license_number')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->integer('warnings')->default(0);
            $table->timestamp('suspended_until')->nullable();
            $table->boolean('is_banned')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('users'); }
};
