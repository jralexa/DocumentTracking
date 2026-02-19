<?php

use App\Models\Department;
use App\Models\User;
use App\UserRole;

test('admin can create a department without submitting a code', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.departments.store'), [
        'name' => 'Planning Unit',
        'abbreviation' => 'plan',
        'is_active' => '1',
    ]);

    $response->assertRedirect(route('admin.organization.index', ['tab' => 'departments']));
    $response->assertSessionHas('status');

    $department = Department::query()->where('name', 'Planning Unit')->first();
    expect($department)->not->toBeNull();
    expect($department?->code)->toBe('PLAN');
});

test('department code stays unchanged when editing details', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $department = Department::factory()->create([
        'code' => 'LEGAL',
        'name' => 'Legal Unit',
        'abbreviation' => 'LEG',
    ]);

    $response = $this->actingAs($admin)->put(route('admin.departments.update', $department), [
        'name' => 'Legal Affairs Unit',
        'abbreviation' => 'LAU',
        'is_active' => '1',
    ]);

    $response->assertRedirect(route('admin.organization.index', ['tab' => 'departments']));
    $response->assertSessionHas('status');

    $department->refresh();
    expect($department->code)->toBe('LEGAL');
    expect($department->name)->toBe('Legal Affairs Unit');
    expect($department->abbreviation)->toBe('LAU');
});

test('department forms do not ask users to provide code', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $department = Department::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.departments.create'))
        ->assertSuccessful()
        ->assertDontSee('name="code"', false);

    $this->actingAs($admin)
        ->get(route('admin.departments.edit', $department))
        ->assertSuccessful()
        ->assertDontSee('name="code"', false);
});

test('department code generation resolves conflicts without validation errors', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Department::factory()->create([
        'code' => 'OPS',
        'name' => 'Operations Legacy',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.departments.store'), [
        'name' => 'Operations Team',
        'abbreviation' => 'ops',
        'is_active' => '1',
    ]);

    $response->assertRedirect(route('admin.organization.index', ['tab' => 'departments']));
    $response->assertSessionHasNoErrors();

    $department = Department::query()->where('name', 'Operations Team')->first();
    expect($department)->not->toBeNull();
    expect($department?->code)->toBe('OPS_2');
});
