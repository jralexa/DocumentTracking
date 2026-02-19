<?php

namespace App\Http\Controllers\Admin;

use App\DocumentWorkflowStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentTransfer;
use App\Models\User;
use App\Notifications\AdminUserCreatedNotification;
use App\Services\SystemLogService;
use App\TransferStatus;
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
     * Create a new controller instance.
     */
    public function __construct(protected SystemLogService $systemLogService) {}

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

        $this->systemLogService->admin(
            action: 'user_created',
            message: 'Admin created a new user account.',
            user: $createdBy,
            request: $request,
            entity: $user,
            context: [
                'role' => $user->role?->value,
                'department_id' => $user->department_id,
            ]
        );

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
        $actor = $request->user();
        $currentDepartmentId = $user->department_id;
        $targetDepartmentId = isset($validated['department_id']) && $validated['department_id'] !== ''
            ? (int) $validated['department_id']
            : null;

        if ($currentDepartmentId !== $targetDepartmentId) {
            $blockingCounts = $this->departmentChangeBlockingCounts($user, $currentDepartmentId);

            if ($blockingCounts['for_action_count'] > 0 || $blockingCounts['pending_outgoing_count'] > 0) {
                return back()
                    ->withInput()
                    ->with('department_reassignment_blockers', $this->departmentChangeBlockerDetails($user, $currentDepartmentId))
                    ->withErrors([
                        'department_id' => $this->departmentChangeBlockedMessage($blockingCounts),
                    ]);
            }
        }

        $user->fill($this->baseUserPayload($validated));

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->must_change_password = true;
        }

        $user->save();

        $this->systemLogService->admin(
            action: 'user_updated',
            message: 'Admin updated a user account.',
            user: $actor,
            request: $request,
            entity: $user,
            context: [
                'role' => $user->role?->value,
                'department_id' => $user->department_id,
            ]
        );

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User updated successfully.');
    }

    /**
     * Reset user password and send a new temporary password by email.
     */
    public function resetTemporaryPassword(User $user): RedirectResponse
    {
        $actor = auth()->user();

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
            createdByName: $actor?->name,
            isReset: true
        ));

        $this->systemLogService->admin(
            action: 'user_password_reset',
            message: 'Admin reset a user temporary password.',
            user: $actor,
            entity: $user
        );

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Temporary password reset and emailed successfully.');
    }

    /**
     * Remove a managed user account.
     */
    public function destroy(User $user): RedirectResponse
    {
        $actor = auth()->user();

        if ($user->id === auth()->id()) {
            return redirect()
                ->route('admin.users.index')
                ->with('status', 'You cannot delete your own account.');
        }

        $user->delete();

        $this->systemLogService->admin(
            action: 'user_deleted',
            message: 'Admin deleted a user account.',
            user: $actor,
            entity: $user
        );

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
        $role = (string) $validated['role'];
        $departmentId = isset($validated['department_id']) && $validated['department_id'] !== ''
            ? (int) $validated['department_id']
            : null;

        if ($role === UserRole::Guest->value) {
            $departmentId = null;
        }

        return [
            'name' => $validated['name'],
            'email' => strtolower((string) $validated['email']),
            'role' => $role,
            'department_id' => $departmentId,
        ];
    }

    /**
     * Count active ownership records that block department reassignment.
     *
     * @return array{for_action_count:int,pending_outgoing_count:int}
     */
    protected function departmentChangeBlockingCounts(User $user, ?int $currentDepartmentId): array
    {
        if ($currentDepartmentId === null) {
            return [
                'for_action_count' => 0,
                'pending_outgoing_count' => 0,
            ];
        }

        $forActionCount = Document::query()
            ->where('current_user_id', $user->id)
            ->where('current_department_id', $currentDepartmentId)
            ->where('status', DocumentWorkflowStatus::OnQueue->value)
            ->count();

        $pendingOutgoingCount = DocumentTransfer::query()
            ->where('forwarded_by_user_id', $user->id)
            ->where('from_department_id', $currentDepartmentId)
            ->where('status', TransferStatus::Pending->value)
            ->whereNull('accepted_at')
            ->count();

        return [
            'for_action_count' => $forActionCount,
            'pending_outgoing_count' => $pendingOutgoingCount,
        ];
    }

    /**
     * Build validation message for blocked department reassignment.
     *
     * @param  array{for_action_count:int,pending_outgoing_count:int}  $blockingCounts
     */
    protected function departmentChangeBlockedMessage(array $blockingCounts): string
    {
        return sprintf(
            'Cannot change department. User still has %d for-action documents and %d pending outgoing transfers. Reassign or resolve them first.',
            $blockingCounts['for_action_count'],
            $blockingCounts['pending_outgoing_count']
        );
    }

    /**
     * Build actionable blocker details used by admin handover UI.
     *
     * @return array{
     *   for_action_documents:array<int, array{tracking_number:string,subject:string|null}>,
     *   pending_outgoing_transfers:array<int, array{transfer_id:int,tracking_number:string,subject:string|null,to_department:string|null,forwarded_at:string|null}>
     * }
     */
    protected function departmentChangeBlockerDetails(User $user, ?int $currentDepartmentId): array
    {
        if ($currentDepartmentId === null) {
            return [
                'for_action_documents' => [],
                'pending_outgoing_transfers' => [],
            ];
        }

        $forActionDocuments = Document::query()
            ->where('current_user_id', $user->id)
            ->where('current_department_id', $currentDepartmentId)
            ->where('status', DocumentWorkflowStatus::OnQueue->value)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['tracking_number', 'subject', 'metadata'])
            ->map(fn (Document $document): array => [
                'tracking_number' => $this->resolveTrackingLabel($document),
                'subject' => $document->subject,
            ])
            ->values()
            ->all();

        $pendingOutgoingTransfers = DocumentTransfer::query()
            ->with([
                'document:id,tracking_number,subject,metadata',
                'toDepartment:id,name',
            ])
            ->where('forwarded_by_user_id', $user->id)
            ->where('from_department_id', $currentDepartmentId)
            ->where('status', TransferStatus::Pending->value)
            ->whereNull('accepted_at')
            ->orderByDesc('forwarded_at')
            ->limit(10)
            ->get(['id', 'document_id', 'to_department_id', 'forwarded_at'])
            ->map(fn (DocumentTransfer $transfer): array => [
                'transfer_id' => $transfer->id,
                'tracking_number' => $this->resolveTrackingLabel($transfer->document),
                'subject' => $transfer->document?->subject,
                'to_department' => $transfer->toDepartment?->name,
                'forwarded_at' => $transfer->forwarded_at?->format('Y-m-d H:i'),
            ])
            ->values()
            ->all();

        return [
            'for_action_documents' => $forActionDocuments,
            'pending_outgoing_transfers' => $pendingOutgoingTransfers,
        ];
    }

    /**
     * Resolve display tracking label for list presentation.
     */
    protected function resolveTrackingLabel(?Document $document): string
    {
        if ($document === null) {
            return '-';
        }

        $displayTracking = $document->metadata['display_tracking'] ?? null;

        if (is_string($displayTracking) && $displayTracking !== '') {
            return $displayTracking;
        }

        return $document->tracking_number;
    }
}
