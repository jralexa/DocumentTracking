<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemLogIndexRequest;
use App\Models\SystemLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class SystemLogController extends Controller
{
    /**
     * Display system logs with filters.
     */
    public function index(SystemLogIndexRequest $request): View
    {
        $validated = $request->validated();

        $logs = SystemLog::query()
            ->with('user:id,name,email')
            ->when(isset($validated['q']) && $validated['q'] !== '', function (Builder $builder) use ($validated): void {
                $query = (string) $validated['q'];
                $like = '%'.$query.'%';

                $builder->where(function (Builder $nested) use ($like): void {
                    $nested
                        ->where('message', 'like', $like)
                        ->orWhere('action', 'like', $like)
                        ->orWhere('category', 'like', $like)
                        ->orWhere('route_name', 'like', $like)
                        ->orWhere('request_path', 'like', $like);
                });
            })
            ->when(isset($validated['category']) && $validated['category'] !== '', function (Builder $builder) use ($validated): void {
                $builder->where('category', $validated['category']);
            })
            ->when(isset($validated['level']) && $validated['level'] !== '', function (Builder $builder) use ($validated): void {
                $builder->where('level', $validated['level']);
            })
            ->when(isset($validated['date_from']) && $validated['date_from'] !== '', function (Builder $builder) use ($validated): void {
                $builder->whereDate('occurred_at', '>=', $validated['date_from']);
            })
            ->when(isset($validated['date_to']) && $validated['date_to'] !== '', function (Builder $builder) use ($validated): void {
                $builder->whereDate('occurred_at', '<=', $validated['date_to']);
            })
            ->latest('occurred_at')
            ->paginate(25)
            ->withQueryString();

        $categories = SystemLog::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('admin.system-logs.index', [
            'logs' => $logs,
            'categories' => $categories,
            'filters' => $validated,
        ]);
    }
}
