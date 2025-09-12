<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgramRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'exercise_id' => ['required', 'exists:exercises,id'],
            'date' => ['required', 'date'],
            'sets' => ['required', 'integer', 'min:1'],
            'reps' => ['required', 'integer', 'min:1'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'comments' => ['nullable', 'string'],
        ];
    }
}