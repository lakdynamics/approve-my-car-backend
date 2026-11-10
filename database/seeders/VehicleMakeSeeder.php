<?php

namespace Database\Seeders;

use App\Models\VehicleMake;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Common vehicle makes in Sri Lanka. Safe to run again: existing makes
 * are left alone (a make an admin hid stays hidden).
 *
 * php artisan db:seed --class=VehicleMakeSeeder
 */
class VehicleMakeSeeder extends Seeder
{
    public const MAKES = [
        // Cars, vans, SUVs
        'Toyota', 'Nissan', 'Honda', 'Suzuki', 'Maruti Suzuki', 'Mitsubishi', 'Mazda',
        'Daihatsu', 'Subaru', 'Isuzu', 'Hyundai', 'Kia', 'Lexus', 'Mercedes-Benz', 'BMW',
        'Audi', 'Volkswagen', 'Ford', 'Chevrolet', 'Peugeot', 'Renault', 'Land Rover',
        'Jaguar', 'Volvo', 'Porsche', 'Mini', 'Tata', 'Mahindra', 'Micro', 'Perodua',
        'Proton', 'MG', 'BYD', 'Chery', 'DFSK', 'Changan', 'Geely', 'Haval', 'Tesla',
        // Motorcycles and three-wheelers
        'Bajaj', 'TVS', 'Hero', 'Yamaha', 'Kawasaki', 'Piaggio', 'Royal Enfield', 'KTM',
        // Lorries and buses
        'Ashok Leyland', 'Hino', 'Mitsubishi Fuso', 'Eicher', 'Foton', 'JAC', 'Kubota',
    ];

    public function run(): void
    {
        foreach (self::MAKES as $name) {
            VehicleMake::firstOrCreate(['name' => $name]);
        }

        // Link vehicles added before the list existed, matching the name
        // ignoring case, and use the list's spelling.
        DB::table('vehicles')
            ->join('vehicle_makes', DB::raw('LOWER(vehicles.make)'), '=', DB::raw('LOWER(vehicle_makes.name)'))
            ->whereNull('vehicles.make_id')
            ->update([
                'vehicles.make_id' => DB::raw('vehicle_makes.id'),
                'vehicles.make' => DB::raw('vehicle_makes.name'),
            ]);
    }
}
