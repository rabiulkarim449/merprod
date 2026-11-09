<?php

namespace ME\MerchandisingSfl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulletinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('bulletin') ? 'msfl_bulletin.edit' : 'msfl_bulletin.add');
    }

    /** Drop untouched blank rows; rows are saved in their on-screen order. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'operations' => collect($this->input('operations', []))
                ->filter(fn ($op) => filled($op['name'] ?? null) || filled($op['operation_id'] ?? null))
                ->values()
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'style_id' => ['required', Rule::exists('msfl_styles', 'id')],
            'bulletin_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'line_id' => ['nullable', Rule::exists('msfl_lines', 'id')],
            'target_per_hour' => ['required', 'integer', 'min:1', 'max:100000'],
            'working_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'remarks' => ['nullable', 'string', 'max:5000'],

            'operations' => ['required', 'array', 'min:1'],
            'operations.*.section' => ['nullable', 'string', 'max:100'],
            'operations.*.operation_id' => ['nullable', Rule::exists('msfl_operations', 'id')],
            'operations.*.name' => ['required', 'string', 'max:255'],
            'operations.*.machine_type_id' => ['nullable', Rule::exists('msfl_machine_types', 'id')],
            'operations.*.attachment' => ['nullable', 'string', 'max:50'],
            'operations.*.smv' => ['required', 'numeric', 'gt:0', 'max:100'],
            'operations.*.workplaces' => ['nullable', 'integer', 'min:1', 'max:99'],
            'operations.*.is_active' => ['nullable', 'boolean'],
            'operations.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['operations.required' => 'Add at least one operation.'];
    }
}
