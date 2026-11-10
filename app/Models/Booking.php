<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\LocationSource;
use App\Enums\TimeSlot;
use App\Enums\VehicleType;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's request to have a vehicle inspected at a place and time.
 *
 * Nothing is mass assignable: BookingService and InspectorBookingService
 * set every field, so status changes always follow the rules.
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'time_slot' => TimeSlot::class,
            'location_source' => LocationSource::class,
            'vehicle_type' => VehicleType::class,
            'vehicle_year' => 'integer',
            'vehicle_mileage' => 'integer',
            'scheduled_date' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * The start of today where the customers are (Sri Lanka).
     */
    public static function localToday(): CarbonImmutable
    {
        return CarbonImmutable::now(config('bookings.timezone'))->startOfDay();
    }

    /**
     * The customer. Still loaded if their account was deleted later.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * The saved vehicle it was booked for, if the customer picked one.
     * For reference only: the booking has its own copy of the details
     * (the vehicle_* columns). Still loaded if the vehicle was deleted.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<VehicleMake, $this>
     */
    public function vehicleMake(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'vehicle_make_id');
    }

    /**
     * The inspector who accepted it, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id')->withTrashed();
    }

    /**
     * The saved address it was made from, if any. For reference only:
     * the booking has its own copy of the location.
     *
     * @return BelongsTo<UserAddress, $this>
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class);
    }

    /**
     * @param  Builder<Booking>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::active());
    }

    /**
     * Soonest first: by date, then morning before afternoon before evening.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeSoonestFirst(Builder $query): void
    {
        $query->orderBy('scheduled_date')
            ->orderByRaw("case time_slot when 'morning' then 1 when 'afternoon' then 2 else 3 end")
            ->orderBy('id');
    }
}
