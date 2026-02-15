<?php

namespace App\Http\Requests;

use App\DocumentVersionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SplitDocumentRequest extends FormRequest
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
            'confirm_routing_reviewed' => ['required', 'accepted'],
            'children' => ['required', 'array', 'min:1', 'max:10'],
            'children.*.routing_mode' => ['nullable', Rule::in(['branch', 'child'])],
            'children.*.subject' => ['required', 'string', 'max:255'],
            'children.*.document_type' => ['required', Rule::in($this->documentTypes())],
            'children.*.same_owner_as_parent' => ['nullable', 'boolean'],
            'children.*.owner_type' => ['nullable', 'required_unless:children.*.same_owner_as_parent,1', Rule::in($this->ownerTypes())],
            'children.*.owner_name' => ['nullable', 'required_unless:children.*.same_owner_as_parent,1', 'string', 'max:255'],
            'children.*.forward_version_type' => ['nullable', Rule::in($this->forwardVersionTypes())],
            'children.*.to_department_ids' => ['required', 'array', 'min:1'],
            'children.*.to_department_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('departments', 'id')->where(static fn ($query) => $query->where('is_active', true)),
                Rule::notIn([$currentDepartmentId]),
            ],
            'children.*.copy_kept' => ['nullable', 'boolean'],
            'children.*.copy_storage_location' => ['nullable', 'required_if:children.*.copy_kept,1', 'string', 'max:255'],
            'children.*.copy_purpose' => ['nullable', 'string', 'max:1000'],
            'children.*.original_storage_location' => ['nullable', 'string', 'max:255'],
            'children.*.is_returnable' => ['nullable', 'boolean'],
            'children.*.return_deadline' => ['nullable', 'date'],
            'children.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Prepare payload defaults and normalize branch behavior.
     */
    protected function prepareForValidation(): void
    {
        $document = $this->route('document');
        $children = $this->input('children', []);

        if (! is_array($children)) {
            return;
        }

        $normalizedChildren = [];

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $routingMode = (string) ($child['routing_mode'] ?? 'branch');
            $normalizedChild = $child;
            $normalizedChild['routing_mode'] = $routingMode;

            if ($routingMode === 'branch' && $document !== null) {
                $normalizedChild['subject'] = $document->subject;
                $normalizedChild['document_type'] = $document->document_type;
                $normalizedChild['same_owner_as_parent'] = '1';
                $normalizedChild['owner_type'] = $document->owner_type;
                $normalizedChild['owner_name'] = $document->owner_name;
            }

            $normalizedChildren[] = $normalizedChild;
        }

        $this->merge(['children' => $normalizedChildren]);
    }

    /**
     * Get validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_routing_reviewed.required' => 'Please confirm you reviewed the child routing details.',
            'confirm_routing_reviewed.accepted' => 'Please confirm you reviewed the child routing details.',
            'children.*.to_department_ids.required' => 'Select at least one destination department.',
            'children.*.to_department_ids.min' => 'Select at least one destination department.',
            'children.*.copy_storage_location.required_if' => 'Storage location is required when keeping a copy.',
            'children.*.owner_type.required_unless' => 'Owner type is required when not using parent owner.',
            'children.*.owner_name.required_unless' => 'Owner name is required when not using parent owner.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $children = $this->input('children', []);

            if (! is_array($children)) {
                return;
            }

            foreach ($children as $index => $child) {
                if (! is_array($child)) {
                    continue;
                }

                $forwardVersionTypeValue = (string) ($child['forward_version_type'] ?? DocumentVersionType::Original->value);
                $isOriginalForward = $forwardVersionTypeValue === DocumentVersionType::Original->value;
                $toDepartmentIds = $child['to_department_ids'] ?? [];

                if ($isOriginalForward && is_array($toDepartmentIds) && count($toDepartmentIds) > 1) {
                    $validator->errors()->add(
                        "children.$index.to_department_ids",
                        'Original version can only be routed to one destination. Use photocopy/certified copy for multi-destination routing.'
                    );
                }
            }
        });
    }

    /**
     * Get allowed document types for split children.
     *
     * @return array<int, string>
     */
    protected function documentTypes(): array
    {
        return ['communication', 'submission', 'request', 'for_processing'];
    }

    /**
     * Get allowed owner types for split children.
     *
     * @return array<int, string>
     */
    protected function ownerTypes(): array
    {
        return ['district', 'school', 'personal', 'others'];
    }

    /**
     * Get allowed forward version types.
     *
     * @return array<int, string>
     */
    protected function forwardVersionTypes(): array
    {
        return array_map(
            static fn (DocumentVersionType $type): string => $type->value,
            DocumentVersionType::cases()
        );
    }
}
