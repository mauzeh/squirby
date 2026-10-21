<?php

namespace App\Sync\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrismProgramRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'week_count' => ['required', 'integer', 'min:0', 'max:65535'],
            'metadata' => ['nullable', 'array'],
            'device_id' => ['nullable', 'string', 'max:36'],
        ];
    }
}
