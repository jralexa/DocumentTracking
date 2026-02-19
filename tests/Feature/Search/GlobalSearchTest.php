<?php

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\UserRole;

test('global search requires authentication', function () {
    $this->get(route('search.global'))->assertRedirect(route('login'));
});

test('global search returns matching page and document results', function () {
    $department = Department::factory()->create(['name' => 'Records Section']);
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);

    $document = Document::factory()->create([
        'tracking_number' => '260299001',
        'subject' => 'Terminal Leave Application',
        'current_department_id' => $department->id,
    ]);

    $response = $this->actingAs($user)->get(route('search.global', ['q' => 'intake']));

    $response->assertSuccessful();
    $response->assertSee('Pages and Menus');
    $response->assertSee('Workplace - Intake');

    $documentResponse = $this->actingAs($user)->get(route('search.global', ['q' => 'terminal leave']));
    $documentResponse->assertSuccessful();
    $documentResponse->assertSee('Documents');
    $documentResponse->assertSee('Pages and Menus');
    $documentResponse->assertSee('No matching pages found.');
    $documentResponse->assertSee('Terminal Leave Application');
    $documentResponse->assertSee($document->tracking_number);
});

test('global search suggestions return menu and document destinations', function () {
    $department = Department::factory()->create(['name' => 'Records Section']);
    $user = User::factory()->create([
        'role' => UserRole::Regular,
        'department_id' => $department->id,
    ]);

    $document = Document::factory()->create([
        'tracking_number' => '260299002',
        'subject' => 'Intake Request Memo',
        'current_department_id' => $department->id,
    ]);

    $response = $this->actingAs($user)->get(route('search.suggestions', ['q' => 'intake']));

    $response->assertOk();
    $response->assertJsonStructure([
        'suggestions' => [
            ['type', 'label', 'description', 'href'],
        ],
    ]);

    $suggestions = collect($response->json('suggestions'));

    expect($suggestions->contains(fn (array $item): bool => $item['type'] === 'page' && $item['label'] === 'Workplace - Intake'))->toBeTrue();
    expect($suggestions->contains(fn (array $item): bool => $item['type'] === 'document' && $item['label'] === $document->tracking_number))->toBeTrue();
});
