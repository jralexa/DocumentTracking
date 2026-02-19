<?php

namespace App\Http\Requests;

use App\Models\User;
use App\UserRole;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $managedUser */
        $managedUser = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($managedUser?->id)],
            'role' => ['required', Rule::in(array_map(static fn (UserRole $role): string => $role->value, UserRole::cases()))],
            'department_id' => [
                'nullable',
                'exists:departments,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->input('role') === UserRole::Guest->value && $value !== null && $value !== '') {
                        $fail('Guest personnel accounts must not be assigned to a department.');
                    }
                },
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
