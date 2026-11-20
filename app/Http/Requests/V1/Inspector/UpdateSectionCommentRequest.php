<?php

namespace App\Http\Requests\V1\Inspector;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * { "comment": "Front shock boots and bumpers damaged." }, or null to clear it.
 */
class UpdateSectionCommentRequest extends FormRequest
{
    /**
     * Only the inspector who accepted the booking (404 for anyone else).
     */
    public function authorize(): bool
    {
        Gate::authorize('work', $this->route('booking'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('comment'))) {
            $this->merge(['comment' => Str::squish($this->input('comment')) ?: null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comment' => ['present', 'nullable', 'string', 'max:2000'],
        ];
    }
}
