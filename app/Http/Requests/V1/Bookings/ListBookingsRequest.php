<?php

namespace App\Http\Requests\V1\Bookings;

use App\Enums\BookingStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query filters for the three booking lists. Each list uses the ones
 * that apply to it: customers use "filter", inspectors "scope", and
 * admins "status" and "date".
 */
class ListBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', Rule::in(['active', 'past'])],
            'scope' => ['nullable', Rule::in(['available', 'mine', 'history'])],
            'status' => ['nullable', Rule::enum(BookingStatus::class)],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
