<?php

namespace App\Sync\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrismInterferenceDismissalsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dismissals' => ['present', 'array'],
            'dismissals.*.resolution_key' => ['required', 'string', 'max:512'],
            'removals' => ['present', 'array'],
            'removals.*.resolution_key' => ['required', 'string', 'max:512'],
            'device_id' => ['nullable', 'string', 'max:36'],
        ];
    }
}
