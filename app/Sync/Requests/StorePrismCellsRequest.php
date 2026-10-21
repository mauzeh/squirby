<?php

namespace App\Sync\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrismCellsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $cells = $this->input('cells');

        if (is_array($cells)) {
            foreach ($cells as $index => $cell) {
                if (is_array($cell) && array_key_exists('text', $cell) && $cell['text'] === null) {
                    $cells[$index]['text'] = '';
                }
            }
            $this->merge(['cells' => $cells]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cells' => ['required', 'array', 'min:1'],
            'cells.*.cell_id' => ['required', 'string', 'max:16'],
            'cells.*.text' => ['present', 'string'],
            'device_id' => ['nullable', 'string', 'max:36'],
        ];
    }
}
