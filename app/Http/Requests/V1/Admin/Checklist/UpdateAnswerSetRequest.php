<?php

namespace App\Http\Requests\V1\Admin\Checklist;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rename, hide or show an answer list. Options are managed separately.
 */
class UpdateAnswerSetRequest extends StoreAnswerSetRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', $this->uniqueName($this->route('answerSet'))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
