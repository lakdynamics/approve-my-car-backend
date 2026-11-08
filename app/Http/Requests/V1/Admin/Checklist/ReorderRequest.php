<?php

namespace App\Http\Requests\V1\Admin\Checklist;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * { "ids": [3, 1, 2] }: every id in the new order. ChecklistService
 * checks the ids are exactly the full set.
 */
class ReorderRequest extends FormRequest
{
    /**
     * The route's "can:admin" middleware already checked who is calling.
     */
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ];
    }
}
