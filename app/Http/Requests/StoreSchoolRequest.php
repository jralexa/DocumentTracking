<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolRequest extends FormRequest
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
        return [
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('schools', 'name')->where(function ($query) {
                    return $query->where('district_id', $this->integer('district_id'));
                }),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
