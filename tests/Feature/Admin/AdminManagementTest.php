<?php

use App\DocumentWorkflowStatus;
use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Notifications\AdminUserCreatedNotification;
use App\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

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
    Notification::fake();

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
    ];

    $response = $this->actingAs($admin)->post(route('admin.users.store'), $payload);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('status');

    $createdUser = User::query()->where('email', $payload['email'])->first();
    expect($createdUser)->not->toBeNull();
    expect($createdUser?->role)->toBe(UserRole::Regular);
    expect($createdUser?->department_id)->toBe($targetDepartment->id);
    expect($createdUser?->must_change_password)->toBeTrue();
    Notification::assertSentTo($createdUser, AdminUserCreatedNotification::class);
});

test('admin can reset user password and email a new temporary password', function () {
    Notification::fake();

    $department = Department::factory()->create();

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'department_id' => $department->id,
    ]);

    $managedUser = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
        'must_change_password' => false,
    ]);

    $oldPasswordHash = $managedUser->password;

    $response = $this->actingAs($admin)->post(route('admin.users.reset-password', $managedUser));

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('status');

    $managedUser->refresh();

    expect($managedUser->must_change_password)->toBeTrue();
    expect(Hash::check('password', $managedUser->password))->toBeFalse();
    expect($managedUser->password)->not->toBe($oldPasswordHash);

    Notification::assertSentTo($managedUser, AdminUserCreatedNotification::class);
});

test('admin cannot change user department while user has active for action workload', function () {
    $oldDepartment = Department::factory()->create();
    $newDepartment = Department::factory()->create();

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'department_id' => $oldDepartment->id,
    ]);

    $managedUser = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $oldDepartment->id,
    ]);

    Document::factory()->create([
        'current_department_id' => $oldDepartment->id,
        'current_user_id' => $managedUser->id,
        'status' => DocumentWorkflowStatus::OnQueue,
    ]);

    $response = $this->actingAs($admin)->put(route('admin.users.update', $managedUser), [
        'name' => $managedUser->name,
        'email' => $managedUser->email,
        'role' => $managedUser->role->value,
        'department_id' => $newDepartment->id,
    ]);

    $response->assertSessionHasErrors('department_id');
    $response->assertSessionHas('department_reassignment_blockers');
    expect($managedUser->fresh()->department_id)->toBe($oldDepartment->id);
});
