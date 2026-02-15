<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDistrictRequest;
use App\Http\Requests\UpdateDistrictRequest;
use App\Models\District;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class DistrictController extends Controller
{
    /**
     * Display the district list.
     */
    public function index(): View
    {
        $districts = $this->districts();

        return view('admin.districts.index', [
            'districts' => $districts,
        ]);
    }

    /**
     * Show the district creation form.
     */
    public function create(): View
    {
        return view('admin.districts.create');
    }

    /**
     * Store a new district.
     */
    public function store(StoreDistrictRequest $request): RedirectResponse
    {
        District::query()->create($this->districtPayload($request->validated(), true));

        return redirect()
            ->route('admin.organization.index', ['tab' => 'districts'])
            ->with('status', 'District created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): void
    {
        abort(404);
    }

    /**
     * Show the district edit form.
     */
    public function edit(District $district): View
    {
        return view('admin.districts.edit', [
            'district' => $district,
        ]);
    }

    /**
     * Update an existing district.
     */
    public function update(UpdateDistrictRequest $request, District $district): RedirectResponse
    {
        $district->update($this->districtPayload($request->validated(), false));

        return redirect()
            ->route('admin.organization.index', ['tab' => 'districts'])
            ->with('status', 'District updated successfully.');
    }

    /**
     * Remove the specified district.
     */
    public function destroy(District $district): RedirectResponse
    {
        if ($district->schools()->exists()) {
            return redirect()
                ->route('admin.organization.index', ['tab' => 'districts'])
                ->with('status', 'Cannot delete district with linked schools.');
        }

        $district->delete();

        return redirect()
            ->route('admin.organization.index', ['tab' => 'districts'])
            ->with('status', 'District deleted successfully.');
    }

    /**
     * Get paginated district listing.
     */
    protected function districts(): LengthAwarePaginator
    {
        return District::query()
            ->withCount('schools')
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * Build normalized district payload for create/update actions.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function districtPayload(array $validated, bool $defaultIsActive): array
    {
        return [
            'code' => strtoupper((string) $validated['code']),
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['is_active'] ?? $defaultIsActive),
        ];
    }
}
