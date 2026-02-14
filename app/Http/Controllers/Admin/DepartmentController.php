<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    /**
     * Display the department list.
     */
    public function index(): View
    {
        $departments = Department::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.departments.index', [
            'departments' => $departments,
        ]);
    }

    /**
     * Show the department creation form.
     */
    public function create(): View
    {
        return view('admin.departments.create');
    }

    /**
     * Store a new department.
     */
    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Department::query()->create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'abbreviation' => $validated['abbreviation'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()
            ->route('admin.departments.index')
            ->with('status', 'Department created successfully.');
    }

    /**
     * Show the department edit form.
     */
    public function edit(Department $department): View
    {
        return view('admin.departments.edit', [
            'department' => $department,
        ]);
    }

    /**
     * Update an existing department.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $validated = $request->validated();

        $department->update([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'abbreviation' => $validated['abbreviation'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()
            ->route('admin.departments.index')
            ->with('status', 'Department updated successfully.');
    }
}
