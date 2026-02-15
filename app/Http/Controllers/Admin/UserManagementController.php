<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Models\Department;
use App\Models\User;
use App\Notifications\AdminUserCreatedNotification;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /**
     * Display users for administration.
     */
    public function index(): View
    {
        $users = $this->managedUsers();

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the user creation form.
     */
    public function create(): View
    {
        return view('admin.users.create', [
            'departments' => $this->departments(),
            'roles' => $this->roles(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        $createdBy = $request->user();
        $temporaryPassword = $this->generateTemporaryPassword();
        $user = User::query()->create($this->createUserPayload($request->validated(), $temporaryPassword));
        $user->notify(new AdminUserCreatedNotification(
            temporaryPassword: $temporaryPassword,
            createdByName: $createdBy?->name,
            isReset: false
        ));

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User created successfully. Login credentials were sent by email.');
    }

    /**
     * Show the user edit form.
     */
    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'managedUser' => $user,
            'departments' => $this->departments(),
            'roles' => $this->roles(),
        ]);
    }

    /**
     * Update a managed user account.
     */
    public function update(UpdateAdminUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $user->fill($this->baseUserPayload($validated));

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->must_change_password = true;
        }

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User updated successfully.');
    }

    /**
     * Reset user password and send a new temporary password by email.
     */
    public function resetTemporaryPassword(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()
                ->route('admin.users.index')
                ->with('status', 'Use your profile page to update your own password.');
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ])->save();

        $user->notify(new AdminUserCreatedNotification(
            temporaryPassword: $temporaryPassword,
            createdByName: auth()->user()?->name,
            isReset: true
        ));

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Temporary password reset and emailed successfully.');
    }

    /**
     * Remove a managed user account.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()
                ->route('admin.users.index')
                ->with('status', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User deleted successfully.');
    }

    /**
     * Get paginated managed users.
     */
    protected function managedUsers(): LengthAwarePaginator
    {
        return User::query()
            ->with('department')
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * Get available department options.
     *
     * @return Collection<int, Department>
     */
    protected function departments(): Collection
    {
        return Department::query()->orderBy('name')->get();
    }

    /**
     * Get available user roles.
     *
     * @return array<int, UserRole>
     */
    protected function roles(): array
    {
        return UserRole::cases();
    }

    /**
     * Build payload for user creation.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function createUserPayload(array $validated, string $temporaryPassword): array
    {
        return array_merge($this->baseUserPayload($validated), [
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);
    }

    /**
     * Generate a temporary password for newly created or reset accounts.
     */
    protected function generateTemporaryPassword(): string
    {
        return Str::password(12, true, true, false, false);
    }

    /**
     * Build base user payload for create/update actions.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function baseUserPayload(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'email' => strtolower((string) $validated['email']),
            'role' => $validated['role'],
            'department_id' => $validated['department_id'] ?? null,
        ];
    }
}
