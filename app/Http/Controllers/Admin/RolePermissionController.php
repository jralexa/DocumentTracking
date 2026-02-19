<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\UserRole;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    /**
     * Display role and permission matrix.
     */
    public function index(): View
    {
        return view('admin.roles-permissions.index', [
            'roles' => UserRole::cases(),
            'capabilities' => $this->capabilities(),
            'roleCapabilities' => $this->roleCapabilities(),
        ]);
    }

    /**
     * Get capability labels and ability keys.
     *
     * @return array<string, string>
     */
    protected function capabilities(): array
    {
        return [
            'Receive and Record Documents' => 'documents.intake',
            'View Document Lists' => 'documents.view',
            'Process Workflow' => 'documents.process',
            'Manage Documents' => 'documents.manage',
            'Export Reports' => 'documents.export',
        ];
    }

    /**
     * Get role to capabilities matrix.
     *
     * @return array<string, array<int, string>>
     */
    protected function roleCapabilities(): array
    {
        return [
            UserRole::Admin->value => [
                'documents.intake',
                'documents.view',
                'documents.process',
                'documents.manage',
                'documents.export',
            ],
            UserRole::Manager->value => [
                'documents.intake',
                'documents.view',
                'documents.process',
                'documents.manage',
                'documents.export',
            ],
            UserRole::Regular->value => [
                'documents.intake',
                'documents.view',
                'documents.process',
            ],
            UserRole::Guest->value => [
                'documents.intake',
            ],
        ];
    }
}
