<?php

namespace App\Sync\Requests;

use Illuminate\Contracts\Validation\Validator;
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
     *
     * `removals` is optional on the wire so an older client that only knows how
     * to push `cells` (no removals channel) keeps working after this backend
     * ships ahead of the client. A missing array is normalized to [] here.
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

        if ($this->input('removals') === null) {
            $this->merge(['removals' => []]);
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
            'cells' => ['present', 'array'],
            'cells.*.cell_id' => ['required', 'string', 'max:16'],
            'cells.*.text' => ['present', 'string'],
            'removals' => ['present', 'array'],
            'removals.*.cell_id' => ['required', 'string', 'max:16'],
            'device_id' => ['nullable', 'string', 'max:36'],
        ];
    }

    /**
     * A payload with neither cells nor removals is a no-op and rejected, matching
     * the prior `cells` required|min:1 contract for the common upsert path.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $cells = $this->input('cells', []);
            $removals = $this->input('removals', []);

            if (empty($cells) && empty($removals)) {
                $validator->errors()->add('cells', 'At least one cell or removal is required.');
            }
        });
    }
}
