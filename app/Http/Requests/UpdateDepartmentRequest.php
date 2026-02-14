<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Department|null $department */
        $department = $this->route('department');

        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('departments', 'code')->ignore($department?->id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department?->id)],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
