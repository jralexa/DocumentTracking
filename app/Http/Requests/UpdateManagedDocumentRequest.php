<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagedDocumentRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'document_type' => ['required', Rule::in(['communication', 'submission', 'request', 'for_processing'])],
            'owner_type' => ['required', Rule::in(['district', 'school', 'personal', 'others'])],
            'owner_name' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
            'is_returnable' => ['nullable', 'boolean'],
            'return_deadline' => ['nullable', 'date', 'required_if:is_returnable,1'],
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
            'return_deadline.required_if' => 'Return deadline is required when document is marked returnable.',
        ];
    }
}
