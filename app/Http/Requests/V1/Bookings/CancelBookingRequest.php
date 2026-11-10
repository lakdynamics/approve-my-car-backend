<?php

namespace App\Http\Requests\V1\Bookings;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CancelBookingRequest extends FormRequest
{
    /**
     * Check ownership before validating, so someone else's booking
     * always gets a 404.
     */
    public function authorize(): Response
    {
        return Gate::inspect('cancel', $this->route('booking'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
