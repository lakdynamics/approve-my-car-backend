<?php

namespace App\Http\Resources\V1;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Load "user" and "inspector" before using this, to avoid
 * one query per booking in lists.
 *
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'scheduled_date' => $this->scheduled_date->toDateString(),
            'time_slot' => $this->time_slot,
            'time_slot_label' => $this->time_slot->label(),
            'location' => [
                'source' => $this->location_source,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'address_text' => $this->address_text,
                'notes' => $this->location_notes,
            ],
            'notes' => $this->notes,
            // The booking's own copy of the vehicle details.
            'vehicle' => [
                'saved_vehicle_id' => $this->vehicle_id,
                'make_id' => $this->vehicle_make_id,
                'make' => $this->vehicle_make,
                'model' => $this->vehicle_model,
                'year' => $this->vehicle_year,
                'vehicle_type' => $this->vehicle_type,
                'registration_number' => $this->vehicle_registration,
                'mileage' => $this->vehicle_mileage,
            ],
            // The customer already knows who they are; inspectors and
            // admins need their name and phone.
            'customer' => $this->when(
                $request->user()?->id !== $this->user_id,
                fn () => $this->person($this->user),
            ),
            'inspector' => $this->inspector_id === null ? null : $this->person($this->inspector),
            'accepted_at' => $this->accepted_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function person(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => $user->phone,
        ];
    }
}
