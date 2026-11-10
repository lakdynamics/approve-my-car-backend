<?php

namespace App\Http\Requests\V1\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'uuid'],
            'platform' => ['required', Rule::in(['android', 'ios', 'web'])],
            'token' => ['required', 'string', 'max:4096', 'regex:/\A\S+\z/'],
        ];
    }
}
