<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleType;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A vehicle a user has added. Deleting one only hides it, so later
 * records that point to it (such as inspections) stay intact.
 */
#[Fillable([
    'registration_number',
    'make_id',
    'make',
    'model',
    'year',
    'vehicle_type',
    'fuel_type',
    'transmission',
    'color',
    'mileage',
    'chassis_number',
    'engine_number',
])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'mileage' => 'integer',
            'vehicle_type' => VehicleType::class,
            'fuel_type' => FuelType::class,
            'transmission' => Transmission::class,
        ];
    }

    /**
     * The user who owns this vehicle.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The make from the dropdown list. Null when the user typed it ("Other").
     * The "make" column always holds the display name.
     *
     * @return BelongsTo<VehicleMake, $this>
     */
    public function vehicleMake(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'make_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
