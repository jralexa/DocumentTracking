<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReleaseOriginalCustodyRequest extends FormRequest
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
        $currentOriginalDepartmentId = $document?->original_current_department_id;

        return [
            'to_department_id' => [
                'nullable',
                'required_without:release_to',
                'integer',
                Rule::exists('departments', 'id')->where(static fn ($query) => $query->where('is_active', true)),
                Rule::notIn([$currentOriginalDepartmentId]),
            ],
            'release_to' => ['nullable', 'required_without:to_department_id', 'string', 'max:255'],
            'original_storage_location' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
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
            'to_department_id.not_in' => 'Destination department must be different from current original holder department.',
            'to_department_id.required_without' => 'Select a destination department or provide release-to details.',
            'release_to.required_without' => 'Provide release-to details when not routing to a department.',
            'copy_storage_location.required_if' => 'Storage location is required when keeping a copy.',
        ];
    }
}
