<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCase;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    /**
     * Display global search results.
     */
    public function index(Request $request): View
    {
        $user = $this->resolveAuthenticatedUser($request);
        $query = trim((string) $request->query('q', ''));

        return view('search.index', [
            'query' => $query,
            'pages' => $this->pageResults($user, $query),
            'documents' => $this->documentResults($query),
            'cases' => $this->caseResults($query, $user),
        ]);
    }

    /**
     * Return autocomplete suggestions for global search.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $user = $this->resolveAuthenticatedUser($request);
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['suggestions' => []]);
        }

        $pageSuggestions = $this->pageResults($user, $query)
            ->take(6)
            ->map(function (array $page): array {
                return [
                    'type' => 'page',
                    'label' => $page['label'],
                    'description' => $page['description'],
                    'href' => $page['href'],
                ];
            });

        $documentSuggestions = $this->documentResults($query)
            ->take(6)
            ->map(function (Document $document): array {
                return [
                    'type' => 'document',
                    'label' => $document->tracking_number,
                    'description' => $document->subject,
                    'href' => route('documents.track', ['tracking_number' => $document->tracking_number]),
                ];
            });

        $caseSuggestions = $this->caseResults($query, $user)
            ->take(4)
            ->map(function (DocumentCase $documentCase): array {
                return [
                    'type' => 'case',
                    'label' => $documentCase->case_number,
                    'description' => $documentCase->title,
                    'href' => route('cases.show', $documentCase),
                ];
            });

        $suggestions = $pageSuggestions
            ->concat($documentSuggestions)
            ->concat($caseSuggestions)
            ->take(10)
            ->values();

        return response()->json(['suggestions' => $suggestions]);
    }

    /**
     * Resolve authenticated user from request context.
     */
    protected function resolveAuthenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return $user;
    }

    /**
     * Build page/menu search results based on user access.
     *
     * @return Collection<int, array{label:string,href:string,description:string,keywords:string}>
     */
    protected function pageResults(User $user, string $query): Collection
    {
        $pages = collect($this->availablePages($user));

        if ($query === '') {
            return $pages->take(12)->values();
        }

        $queryLower = mb_strtolower($query);

        return $pages
            ->filter(function (array $page) use ($queryLower): bool {
                $haystack = mb_strtolower($page['label'].' '.$page['description'].' '.$page['keywords']);

                return str_contains($haystack, $queryLower);
            })
            ->values();
    }

    /**
     * Build document search results.
     *
     * @return Collection<int, Document>
     */
    protected function documentResults(string $query): Collection
    {
        if ($query === '') {
            return collect();
        }

        $like = '%'.$query.'%';

        return Document::query()
            ->with('currentDepartment:id,name')
            ->where(function (Builder $builder) use ($query, $like): void {
                $builder
                    ->where('tracking_number', $query)
                    ->orWhere('metadata->display_tracking', $query)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('reference_number', 'like', $like)
                    ->orWhere('owner_name', 'like', $like);
            })
            ->latest('updated_at')
            ->limit(10)
            ->get();
    }

    /**
     * Build case search results.
     *
     * @return Collection<int, DocumentCase>
     */
    protected function caseResults(string $query, User $user): Collection
    {
        if ($query === '' || ! $user->hasAnyRole([UserRole::Admin, UserRole::Manager])) {
            return collect();
        }

        $like = '%'.$query.'%';

        return DocumentCase::query()
            ->where('case_number', 'like', $like)
            ->orWhere('title', 'like', $like)
            ->orderByDesc('opened_at')
            ->limit(10)
            ->get();
    }

    /**
     * Define all available searchable pages for the user.
     *
     * @return array<int, array{label:string,href:string,description:string,keywords:string}>
     */
    protected function availablePages(User $user): array
    {
        $pages = [];

        $pages[] = [
            'label' => 'Dashboard',
            'href' => route('dashboard'),
            'description' => 'Work highlights and alerts',
            'keywords' => 'home main alerts queues',
        ];

        if ($user->canIntakeDocuments()) {
            $pages[] = [
                'label' => 'Workplace - Intake',
                'href' => route('documents.create'),
                'description' => 'Receive and record documents',
                'keywords' => 'intake receive record create',
            ];
        }

        if ($user->canProcessDocuments()) {
            $pages[] = [
                'label' => 'Workplace - Queue',
                'href' => route('documents.queues.index'),
                'description' => 'Route, accept, complete, and recall',
                'keywords' => 'queue route process workflow accept forward complete recall',
            ];
        }

        $pages[] = [
            'label' => 'Workplace - Monitor (Track)',
            'href' => route('documents.track'),
            'description' => 'Track document routing history',
            'keywords' => 'monitor track timeline transfer history',
        ];

        if ($user->canViewDocuments()) {
            $pages[] = [
                'label' => 'Workplace - Monitor (List/Search)',
                'href' => route('documents.index'),
                'description' => 'Browse and filter document records',
                'keywords' => 'monitor list search filter documents',
            ];
        }

        if ($user->hasAnyRole([UserRole::Admin, UserRole::Manager])) {
            $pages[] = [
                'label' => 'Cases',
                'href' => route('cases.index'),
                'description' => 'Case list and timelines',
                'keywords' => 'case casefile timeline',
            ];
        }

        if ($user->canProcessDocuments()) {
            $pages[] = [
                'label' => 'Custody - Originals',
                'href' => route('custody.originals.index'),
                'description' => 'Current original custody records',
                'keywords' => 'custody originals release',
            ];
            $pages[] = [
                'label' => 'Custody - Copies',
                'href' => route('custody.copies.index'),
                'description' => 'Active copy inventory',
                'keywords' => 'custody copies inventory',
            ];
        }

        if ($user->hasAnyRole([UserRole::Admin, UserRole::Manager])) {
            $pages[] = [
                'label' => 'Custody - Returnables',
                'href' => route('custody.returnables.index'),
                'description' => 'Returnable original tracking',
                'keywords' => 'returnable returned deadline',
            ];
        }

        if ($user->canExportReports()) {
            $pages[] = [
                'label' => 'Reports - Monthly Department',
                'href' => route('reports.departments.monthly'),
                'description' => 'Department monthly report',
                'keywords' => 'reports monthly department csv',
            ];
            $pages[] = [
                'label' => 'Reports - Aging/Overdue',
                'href' => route('reports.aging-overdue'),
                'description' => 'Aging and overdue analytics',
                'keywords' => 'reports aging overdue',
            ];
            $pages[] = [
                'label' => 'Reports - SLA Compliance',
                'href' => route('reports.sla-compliance'),
                'description' => 'SLA compliance metrics',
                'keywords' => 'reports sla compliance',
            ];
            $pages[] = [
                'label' => 'Reports - Performance',
                'href' => route('reports.performance'),
                'description' => 'Department and user productivity',
                'keywords' => 'reports performance productivity',
            ];
            $pages[] = [
                'label' => 'Reports - Custody',
                'href' => route('reports.custody'),
                'description' => 'Custody and returnable metrics',
                'keywords' => 'reports custody copies returnable',
            ];
        }

        if ($user->hasRole(UserRole::Admin)) {
            $pages[] = [
                'label' => 'Administration - Organization',
                'href' => route('admin.organization.index'),
                'description' => 'Departments, districts, schools',
                'keywords' => 'admin organization departments districts schools',
            ];
            $pages[] = [
                'label' => 'Administration - Users',
                'href' => route('admin.users.index'),
                'description' => 'User management and roles',
                'keywords' => 'admin users roles permissions',
            ];
        }

        $pages[] = [
            'label' => 'Profile',
            'href' => route('profile.edit'),
            'description' => 'Personal account settings',
            'keywords' => 'profile account settings password',
        ];

        return $pages;
    }
}
