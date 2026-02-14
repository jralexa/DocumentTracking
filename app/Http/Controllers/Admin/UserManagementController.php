<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Models\Department;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /**
     * Display users for administration.
     */
    public function index(): View
    {
        $users = User::query()
            ->with('department')
            ->orderBy('name')
            ->paginate(15);

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
            'departments' => Department::query()->orderBy('name')->get(),
            'roles' => UserRole::cases(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::query()->create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'department_id' => $validated['department_id'] ?? null,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User created successfully.');
    }

    /**
     * Show the user edit form.
     */
    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'managedUser' => $user,
            'departments' => Department::query()->orderBy('name')->get(),
            'roles' => UserRole::cases(),
        ]);
    }

    /**
     * Update a managed user account.
     */
    public function update(UpdateAdminUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->fill([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'role' => $validated['role'],
            'department_id' => $validated['department_id'] ?? null,
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User updated successfully.');
    }
}
