<?php

namespace App\Http\Requests;

use App\Models\District;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistrictRequest extends FormRequest
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
        /** @var District|null $district */
        $district = $this->route('district');

        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('districts', 'code')->ignore($district?->id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('districts', 'name')->ignore($district?->id)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
