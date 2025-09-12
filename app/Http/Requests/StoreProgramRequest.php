<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgramRequest extends FormRequest
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
            'exercise_id' => ['required_without:new_exercise_name', 'nullable', 'exists:exercises,id'],
            'new_exercise_name' => ['required_without:exercise_id', 'nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'sets' => ['required', 'integer', 'min:1'],
            'reps' => ['required', 'integer', 'min:1'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'comments' => ['nullable', 'string'],
        ];
    }
}