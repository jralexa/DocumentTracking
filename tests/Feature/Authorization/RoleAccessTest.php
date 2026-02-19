<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:admin'])->get('/_test/admin-only', function () {
        return response()->json(['ok' => true]);
    });
});

test('role middleware allows matching roles', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->get('/_test/admin-only');

    $response->assertSuccessful();
});

test('role middleware blocks non-matching roles', function () {
    $regularUser = User::factory()->create(['role' => UserRole::Regular]);

    $response = $this->actingAs($regularUser)->get('/_test/admin-only');

    $response->assertForbidden();
});

test('document ability matrix follows role policy', function () {
    $expectedPermissionsByRole = [
        [UserRole::Admin, ['view' => true, 'process' => true, 'manage' => true, 'export' => true]],
        [UserRole::Manager, ['view' => true, 'process' => true, 'manage' => true, 'export' => true]],
        [UserRole::Regular, ['view' => true, 'process' => true, 'manage' => false, 'export' => false]],
        [UserRole::Guest, ['view' => true, 'process' => false, 'manage' => false, 'export' => false]],
    ];

    foreach ($expectedPermissionsByRole as [$role, $permissions]) {
        $user = User::factory()->create(['role' => $role]);

        expect(Gate::forUser($user)->allows('documents.view'))->toBe($permissions['view']);
        expect(Gate::forUser($user)->allows('documents.process'))->toBe($permissions['process']);
        expect(Gate::forUser($user)->allows('documents.manage'))->toBe($permissions['manage']);
        expect(Gate::forUser($user)->allows('documents.export'))->toBe($permissions['export']);
    }
});
