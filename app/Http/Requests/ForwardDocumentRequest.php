<?php

namespace App\Http\Requests;

use App\DocumentVersionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForwardDocumentRequest extends FormRequest
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
        $document = $this->route('document');
        $currentDepartmentId = $document?->current_department_id;

        return [
            'to_department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where(static fn ($query) => $query->where('is_active', true)),
                Rule::notIn([$currentDepartmentId]),
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'forward_version_type' => ['nullable', Rule::in(array_map(static fn (DocumentVersionType $type): string => $type->value, DocumentVersionType::cases()))],
            'copy_kept' => ['nullable', 'boolean'],
            'copy_storage_location' => ['nullable', 'required_if:copy_kept,1', 'string', 'max:255'],
            'copy_purpose' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_department_id.not_in' => 'Destination department must be different from the current department.',
            'copy_storage_location.required_if' => 'Storage location is required when keeping a copy.',
        ];
    }
}
