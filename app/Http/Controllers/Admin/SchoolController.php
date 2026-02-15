<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Models\District;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SchoolController extends Controller
{
    /**
     * Display the school list.
     */
    public function index(): View
    {
        $schools = $this->schools();

        return view('admin.schools.index', [
            'schools' => $schools,
        ]);
    }

    /**
     * Show the school creation form.
     */
    public function create(): View
    {
        return view('admin.schools.create', [
            'districts' => $this->activeDistricts(),
        ]);
    }

    /**
     * Store a new school.
     */
    public function store(StoreSchoolRequest $request): RedirectResponse
    {
        School::query()->create($this->schoolPayload($request->validated(), true));

        return redirect()
            ->route('admin.organization.index', ['tab' => 'schools'])
            ->with('status', 'School created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): void
    {
        abort(404);
    }

    /**
     * Show the school edit form.
     */
    public function edit(School $school): View
    {
        return view('admin.schools.edit', [
            'school' => $school,
            'districts' => $this->editableDistricts($school),
        ]);
    }

    /**
     * Update an existing school.
     */
    public function update(UpdateSchoolRequest $request, School $school): RedirectResponse
    {
        $school->update($this->schoolPayload($request->validated(), false));

        return redirect()
            ->route('admin.organization.index', ['tab' => 'schools'])
            ->with('status', 'School updated successfully.');
    }

    /**
     * Remove the specified school.
     */
    public function destroy(School $school): RedirectResponse
    {
        if ($school->documents()->exists()) {
            return redirect()
                ->route('admin.organization.index', ['tab' => 'schools'])
                ->with('status', 'Cannot delete school linked to documents.');
        }

        $school->delete();

        return redirect()
            ->route('admin.organization.index', ['tab' => 'schools'])
            ->with('status', 'School deleted successfully.');
    }

    /**
     * Get paginated school listing.
     */
    protected function schools(): LengthAwarePaginator
    {
        return School::query()
            ->with('district:id,name')
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * Get active districts for school create form.
     *
     * @return Collection<int, District>
     */
    protected function activeDistricts(): Collection
    {
        return District::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Get district options for school edit form.
     *
     * @return Collection<int, District>
     */
    protected function editableDistricts(School $school): Collection
    {
        return District::query()
            ->where('is_active', true)
            ->orWhereKey($school->district_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Build normalized school payload for create/update actions.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function schoolPayload(array $validated, bool $defaultIsActive): array
    {
        return [
            'district_id' => (int) $validated['district_id'],
            'code' => isset($validated['code']) && $validated['code'] !== '' ? strtoupper((string) $validated['code']) : null,
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['is_active'] ?? $defaultIsActive),
        ];
    }
}
