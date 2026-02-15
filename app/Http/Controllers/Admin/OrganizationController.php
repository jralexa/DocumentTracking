<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\District;
use App\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    /**
     * Display organization master data in a tabbed page.
     */
    public function index(Request $request): View
    {
        $tab = $this->resolveTab((string) $request->query('tab', 'departments'));
        $records = $this->recordsForTab($tab);

        return view('admin.organization.index', [
            'tab' => $tab,
            'records' => $records,
        ]);
    }

    /**
     * Resolve valid tab key.
     */
    protected function resolveTab(string $tab): string
    {
        $allowedTabs = ['departments', 'districts', 'schools'];

        if (! in_array($tab, $allowedTabs, true)) {
            return 'departments';
        }

        return $tab;
    }

    /**
     * Get paginated records for selected tab.
     */
    protected function recordsForTab(string $tab): LengthAwarePaginator
    {
        if ($tab === 'districts') {
            return District::query()
                ->withCount('schools')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString();
        }

        if ($tab === 'schools') {
            return School::query()
                ->with('district:id,name')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString();
        }

        return Department::query()
            ->withCount('users')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
    }
}
