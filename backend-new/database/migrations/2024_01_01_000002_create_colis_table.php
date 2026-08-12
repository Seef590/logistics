<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('colis', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_id')->unique();
            $table->foreignId('expediteur_id')->constrained('users');
            $table->foreignId('destinataire_id')->nullable()->constrained('users');
            $table->foreignId('livreur_id')->nullable()->constrained('users');
            $table->foreignId('voyageur_id')->nullable()->constrained('users');
            $table->enum('status', ['created','pending','picked_up','in_transit','out_for_delivery','delivered','failed','returned'])->default('pending');
            $table->string('from_city');
            $table->string('to_city');
            $table->text('from_address');
            $table->text('to_address');
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->float('weight');
            $table->text('description')->nullable();
            $table->float('price');
            $table->boolean('is_paid')->default(false);
            $table->string('payment_method')->nullable();
            $table->string('pin_code');
            $table->boolean('pin_validated')->default(false);
            $table->string('qr_code')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('estimated_delivery')->nullable();
            $table->boolean('is_voyageur_eligible')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('colis'); }
};
