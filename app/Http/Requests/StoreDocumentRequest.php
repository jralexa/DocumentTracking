<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
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
            'quick_mode' => ['nullable', 'boolean'],
            'case_title' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'document_type' => ['required', Rule::in(['communication', 'submission', 'request', 'for_processing'])],
            'owner_type' => ['required', Rule::in(['district', 'school', 'personal', 'others'])],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_reference' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'required_unless:quick_mode,1', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'initial_remarks' => ['nullable', 'string', 'max:1000'],
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
