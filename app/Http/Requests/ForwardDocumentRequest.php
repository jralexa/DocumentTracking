<?php

namespace App\Http\Requests;

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
        ];
    }
}
