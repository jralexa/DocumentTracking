<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    /**
     * Display the department list.
     */
    public function index(): View
    {
        $departments = $this->departments();

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
        Department::query()->create($this->departmentPayload($request->validated(), true));

        return redirect()
            ->route('admin.organization.index', ['tab' => 'departments'])
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
        $department->update($this->departmentPayload($request->validated(), false));

        return redirect()
            ->route('admin.organization.index', ['tab' => 'departments'])
            ->with('status', 'Department updated successfully.');
    }

    /**
     * Delete a department when no users are linked.
     */
    public function destroy(Department $department): RedirectResponse
    {
        if ($department->users()->exists()) {
            return redirect()
                ->route('admin.organization.index', ['tab' => 'departments'])
                ->with('status', 'Cannot delete department with linked users.');
        }

        $department->delete();

        return redirect()
            ->route('admin.organization.index', ['tab' => 'departments'])
            ->with('status', 'Department deleted successfully.');
    }

    /**
     * Get paginated department listing.
     */
    protected function departments(): LengthAwarePaginator
    {
        return Department::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * Build normalized department payload for create/update actions.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function departmentPayload(array $validated, bool $defaultIsActive): array
    {
        return [
            'code' => strtoupper((string) $validated['code']),
            'name' => $validated['name'],
            'abbreviation' => $validated['abbreviation'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? $defaultIsActive),
        ];
    }
}
