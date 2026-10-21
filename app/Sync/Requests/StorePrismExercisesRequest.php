<?php

namespace App\Sync\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrismExercisesRequest extends FormRequest
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
            'upserts' => ['present', 'array'],
            'upserts.*.exercise_id' => ['required', 'string', 'max:255'],
            'upserts.*.record' => ['required', 'array'],
            'removals' => ['present', 'array'],
            'removals.*.exercise_id' => ['required', 'string', 'max:255'],
            'device_id' => ['nullable', 'string', 'max:36'],
        ];
    }
}
