<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users');
            $table->foreignId('to_user_id')->constrained('users');
            $table->foreignId('colis_id')->constrained('colis');
            $table->tinyInteger('score');
            $table->text('comment')->nullable();
            $table->unique(['from_user_id','colis_id']);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('ratings'); }
};
