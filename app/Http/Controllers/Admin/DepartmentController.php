<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\SystemLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected SystemLogService $systemLogService) {}

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
        $department = Department::query()->create($this->departmentPayload($request->validated(), true));

        $this->systemLogService->admin(
            action: 'department_created',
            message: 'Admin created a department.',
            user: $request->user(),
            request: $request,
            entity: $department
        );

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
        $department->update($this->departmentPayload($request->validated(), false, $department));

        $this->systemLogService->admin(
            action: 'department_updated',
            message: 'Admin updated a department.',
            user: $request->user(),
            request: $request,
            entity: $department
        );

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

        $this->systemLogService->admin(
            action: 'department_deleted',
            message: 'Admin deleted a department.',
            user: request()->user(),
            request: request(),
            entity: $department
        );

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
    protected function departmentPayload(array $validated, bool $defaultIsActive, ?Department $department = null): array
    {
        $abbreviation = isset($validated['abbreviation']) && $validated['abbreviation'] !== ''
            ? strtoupper((string) $validated['abbreviation'])
            : null;

        $code = $department?->code;
        if ($code === null || $code === '') {
            $codeSeed = $abbreviation ?? (string) $validated['name'];
            $code = $this->generateUniqueDepartmentCode($codeSeed);
        }

        return [
            'code' => $code,
            'name' => $validated['name'],
            'abbreviation' => $abbreviation,
            'is_active' => (bool) ($validated['is_active'] ?? $defaultIsActive),
        ];
    }

    /**
     * Generate a unique, stable department code.
     */
    protected function generateUniqueDepartmentCode(string $seed): string
    {
        $normalizedSeed = trim($seed);
        $baseCode = strtoupper(Str::slug($normalizedSeed, '_'));
        if ($baseCode === '') {
            $baseCode = 'DEPARTMENT';
        }

        $baseCode = Str::limit($baseCode, 50, '');
        $candidate = $baseCode;
        $suffix = 2;

        while (Department::query()->where('code', $candidate)->exists()) {
            $suffixText = '_'.$suffix;
            $prefixLimit = max(1, 50 - strlen($suffixText));
            $candidate = Str::limit($baseCode, $prefixLimit, '').$suffixText;
            $suffix++;
        }

        return $candidate;
    }
}
