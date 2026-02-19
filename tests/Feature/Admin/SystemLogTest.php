<?php

use App\Models\Department;
use App\Models\SystemLog;
use App\Models\User;
use App\UserRole;

test('admin can access system logs page', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'department_id' => $department->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.system-logs.index'))
        ->assertSuccessful()
        ->assertSee('System Logs');
});

test('non admin cannot access system logs page', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create([
        'role' => UserRole::Manager,
        'department_id' => $department->id,
    ]);

    $this->actingAs($manager)
        ->get(route('admin.system-logs.index'))
        ->assertForbidden();
});

test('admin user creation writes a system log', function () {
    $adminDepartment = Department::factory()->create();
    $targetDepartment = Department::factory()->create();
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'department_id' => $adminDepartment->id,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Log User',
        'email' => 'log.user@example.com',
        'role' => UserRole::Regular->value,
        'department_id' => $targetDepartment->id,
    ]);

    $response->assertRedirect(route('admin.users.index'));

    expect(SystemLog::query()
        ->where('category', 'admin')
        ->where('action', 'user_created')
        ->where('user_id', $admin->id)
        ->exists())->toBeTrue();
});
