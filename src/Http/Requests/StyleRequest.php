<?php

namespace ME\MerchandisingSfl\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ME\MerchandisingSfl\Models\Style;
use ME\MerchandisingSfl\Services\FileUploadService;

class StyleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('style') ? 'msfl_style.edit' : 'msfl_style.add');
    }

    public function rules(): array
    {
        return [
            // Style No / Name / Buyer live in Master Data → Styles: a new tech
            // pack picks one of those styles; an edit can't change them.
            'style_id' => $this->route('style')
                ? ['prohibited']
                : ['required', Rule::exists('msfl_styles', 'id')->whereNull('deleted_at')],
            'inquiry_id' => ['nullable', Rule::exists('msfl_inquiries', 'id')],
            'season_id' => ['nullable', Rule::exists('msfl_seasons', 'id')],
            'merchandiser_id' => ['nullable', Rule::exists('users', 'id')],
            'product_type_id' => ['nullable', Rule::exists('msfl_product_types', 'id')],
            'wash_type_id' => ['nullable', Rule::exists('msfl_wash_types', 'id')],
            'smv' => ['nullable', 'numeric', 'min:0'],
            'target_cm' => ['nullable', 'numeric', 'min:0'],
            'confirm_cm' => ['nullable', 'numeric', 'min:0'],
            'fabric_sourced_by' => ['required', Rule::in(['self', 'buyer'])],
            'fabric_description' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'development_status' => ['required', Rule::in(array_keys(Style::STATUSES))],
            'is_active' => ['nullable', 'boolean'],
            'tech_pack_file' => ['nullable', ...FileUploadService::RULES],
            'artwork_file' => ['nullable', ...FileUploadService::RULES],
            'size_chart_file' => ['nullable', ...FileUploadService::RULES],
        ];
    }
}
