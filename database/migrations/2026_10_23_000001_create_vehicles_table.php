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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('registration_number', 20);
            $table->string('make', 50);
            $table->string('model', 50);
            $table->unsignedSmallInteger('year');
            $table->string('vehicle_type', 30);
            $table->string('fuel_type', 20)->nullable();
            $table->string('transmission', 20)->nullable();
            $table->string('color', 30)->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->string('chassis_number', 50)->nullable();
            $table->string('engine_number', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Not unique: deleted vehicles keep their row, so "unique per user"
            // is checked in validation, ignoring deleted ones.
            $table->index(['user_id', 'registration_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
