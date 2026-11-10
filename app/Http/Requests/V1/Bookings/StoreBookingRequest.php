<?php

namespace App\Http\Requests\V1\Bookings;

use App\Enums\LocationSource;
use App\Enums\TimeSlot;
use App\Enums\VehicleType;
use App\Http\Requests\V1\Vehicles\Concerns\NormalizesVehicleInput;
use App\Http\Requests\V1\Vehicles\Concerns\ValidatesVehicleMake;
use App\Models\Booking;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The vehicle comes either as one of the customer's saved vehicles or as
 * typed-in details. The location comes either as coordinates + text
 * (gps, map) or as one of their saved addresses. Fields for the other
 * kind are dropped.
 */
class StoreBookingRequest extends FormRequest
{
    use NormalizesVehicleInput, ValidatesVehicleMake;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $today = Booking::localToday();
        [$minLat, $maxLat] = config('bookings.service_area.latitude');
        [$minLng, $maxLng] = config('bookings.service_area.longitude');
        $saved = LocationSource::SavedAddress->value;
        // With a saved vehicle, typed vehicle details are ignored.
        $savedVehicle = Rule::excludeIf(fn () => $this->filled('vehicle_id'));

        return [
            // Either one of my saved vehicles (its details are copied), or
            // the details typed in. Typed fields are ignored with vehicle_id.
            'vehicle_id' => [
                'nullable', 'integer',
                Rule::exists('vehicles', 'id')->where('user_id', $this->user()->id)->whereNull('deleted_at'),
            ],
            'make_id' => [$savedVehicle, 'nullable', 'integer', $this->listedMake()],
            'make' => [$savedVehicle, 'required_without:make_id', 'nullable', 'string', 'max:50'],
            'model' => [$savedVehicle, 'required', 'string', 'max:50'],
            'year' => [$savedVehicle, 'required', 'integer', 'between:1950,'.((int) date('Y') + 1)],
            'vehicle_type' => [$savedVehicle, 'required', Rule::enum(VehicleType::class)],
            'registration_number' => [$savedVehicle, 'nullable', 'string', 'max:20'],
            'mileage' => [$savedVehicle, 'nullable', 'integer', 'between:0,2000000'],

            'scheduled_date' => [
                'required', 'date_format:Y-m-d',
                'after_or_equal:'.$today->addDays(config('bookings.min_days_ahead'))->toDateString(),
                'before_or_equal:'.$today->addDays(config('bookings.max_days_ahead'))->toDateString(),
            ],
            'time_slot' => ['required', Rule::enum(TimeSlot::class)],
            'location_source' => ['required', Rule::enum(LocationSource::class)],

            // GPS or map pin.
            'latitude' => ["exclude_if:location_source,{$saved}", 'required', 'numeric', "between:{$minLat},{$maxLat}"],
            'longitude' => ["exclude_if:location_source,{$saved}", 'required', 'numeric', "between:{$minLng},{$maxLng}"],
            'address_text' => ["exclude_if:location_source,{$saved}", 'required', 'string', 'max:255'],

            // Saved address.
            'address_id' => [
                "exclude_unless:location_source,{$saved}", 'required', 'integer',
                Rule::exists('user_addresses', 'id')->where('user_id', $this->user()->id),
            ],

            'location_notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        $maxDays = config('bookings.max_days_ahead');

        return [
            'vehicle_id.exists' => 'Select one of your vehicles.',
            ...$this->makeMessages(),
            'address_id.exists' => 'Select one of your saved addresses.',
            'scheduled_date.after_or_equal' => 'Choose a date from tomorrow onwards.',
            'scheduled_date.before_or_equal' => "You can book up to {$maxDays} days ahead.",
            'latitude.between' => 'We only serve locations in Sri Lanka.',
            'longitude.between' => 'We only serve locations in Sri Lanka.',
        ];
    }
}
