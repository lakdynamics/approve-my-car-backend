<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bookings keep their own copy of the vehicle details, so a customer can
 * book an inspection for a vehicle they haven't saved (e.g. one they're
 * buying). vehicle_id is now only set when they picked a saved vehicle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->change();

            $table->foreignId('vehicle_make_id')->nullable()->after('vehicle_id')
                ->constrained('vehicle_makes')->nullOnDelete();
            $table->string('vehicle_make', 50)->nullable()->after('vehicle_make_id');
            $table->string('vehicle_model', 50)->nullable()->after('vehicle_make');
            $table->unsignedSmallInteger('vehicle_year')->nullable()->after('vehicle_model');
            $table->string('vehicle_type', 30)->nullable()->after('vehicle_year');
            $table->string('vehicle_registration', 20)->nullable()->after('vehicle_type');
            $table->unsignedInteger('vehicle_mileage')->nullable()->after('vehicle_registration');
        });

        // Copy the details of existing bookings from their saved vehicle.
        DB::table('bookings')
            ->join('vehicles', 'vehicles.id', '=', 'bookings.vehicle_id')
            ->update([
                'bookings.vehicle_make_id' => DB::raw('vehicles.make_id'),
                'bookings.vehicle_make' => DB::raw('vehicles.make'),
                'bookings.vehicle_model' => DB::raw('vehicles.model'),
                'bookings.vehicle_year' => DB::raw('vehicles.year'),
                'bookings.vehicle_type' => DB::raw('vehicles.vehicle_type'),
                'bookings.vehicle_registration' => DB::raw('vehicles.registration_number'),
                'bookings.vehicle_mileage' => DB::raw('vehicles.mileage'),
            ]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('vehicle_make', 50)->nullable(false)->change();
            $table->string('vehicle_model', 50)->nullable(false)->change();
            $table->unsignedSmallInteger('vehicle_year')->nullable(false)->change();
            $table->string('vehicle_type', 30)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_make_id');
            $table->dropColumn([
                'vehicle_make', 'vehicle_model', 'vehicle_year', 'vehicle_type',
                'vehicle_registration', 'vehicle_mileage',
            ]);
            // Bookings without a saved vehicle must be removed before rolling back.
            $table->foreignId('vehicle_id')->nullable(false)->change();
        });
    }
};
