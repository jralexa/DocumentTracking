<?php

use App\Models\Department;
use App\Models\User;
use App\UserRole;

test('admin can access administration pages', function () {
    $department = Department::factory()->create();
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'department_id' => $department->id,
    ]);

    $this->actingAs($admin)->get(route('admin.departments.index'))->assertSuccessful();
    $this->actingAs($admin)->get(route('admin.users.index'))->assertSuccessful();
    $this->actingAs($admin)->get(route('admin.roles-permissions.index'))->assertSuccessful();
});

test('non admin cannot access administration pages', function () {
    $department = Department::factory()->create();
    $manager = User::factory()->create([
        'role' => UserRole::Manager,
        'department_id' => $department->id,
    ]);

    $this->actingAs($manager)->get(route('admin.departments.index'))->assertForbidden();
    $this->actingAs($manager)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($manager)->get(route('admin.roles-permissions.index'))->assertForbidden();
});

test('admin can create user with role and department', function () {
    $adminDepartment = Department::factory()->create();
    $targetDepartment = Department::factory()->create();

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'department_id' => $adminDepartment->id,
    ]);

    $payload = [
        'name' => 'Queue Tester',
        'email' => 'queue.tester@example.com',
        'role' => UserRole::Regular->value,
        'department_id' => $targetDepartment->id,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $response = $this->actingAs($admin)->post(route('admin.users.store'), $payload);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('status');

    $createdUser = User::query()->where('email', $payload['email'])->first();
    expect($createdUser)->not->toBeNull();
    expect($createdUser?->role)->toBe(UserRole::Regular);
    expect($createdUser?->department_id)->toBe($targetDepartment->id);
});
