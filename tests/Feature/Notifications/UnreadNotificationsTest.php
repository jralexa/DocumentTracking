<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('unread notifications endpoint requires authentication', function () {
    $this->get(route('notifications.unread'))->assertRedirect(route('login'));
});

test('unread notifications endpoint returns only unread count', function () {
    $user = User::factory()->create([
        'role' => UserRole::Regular,
    ]);

    DB::table('notifications')->insert([
        [
            'id' => (string) Str::uuid(),
            'type' => 'tests.notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => 'Unread'], JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) Str::uuid(),
            'type' => 'tests.notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['message' => 'Read'], JSON_THROW_ON_ERROR),
            'read_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs($user)
        ->get(route('notifications.unread'))
        ->assertOk()
        ->assertJson([
            'unread_count' => 1,
        ]);
});
