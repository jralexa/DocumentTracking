<?php

namespace App\Policies;

use App\Models\User;

class DocumentAccessPolicy
{
    /**
     * Determine whether the user can view document lists.
     */
    public function view(User $user): bool
    {
        return $user->canViewDocuments();
    }

    /**
     * Determine whether the user can process documents in queues.
     */
    public function process(User $user): bool
    {
        return $user->canProcessDocuments();
    }

    /**
     * Determine whether the user can manage document records.
     */
    public function manage(User $user): bool
    {
        return $user->canManageDocuments();
    }

    /**
     * Determine whether the user can export document reports.
     */
    public function export(User $user): bool
    {
        return $user->canExportReports();
    }
}
