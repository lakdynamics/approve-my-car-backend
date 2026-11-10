<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_makes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            // Hidden makes stay linked to existing vehicles but leave the dropdown.
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('vehicles', function (Blueprint $table) {
            // Null when the user chose "Other" and typed the make.
            // vehicles.make keeps the display name either way.
            $table->foreignId('make_id')->nullable()->after('registration_number')
                ->constrained('vehicle_makes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('make_id');
        });

        Schema::dropIfExists('vehicle_makes');
    }
};
