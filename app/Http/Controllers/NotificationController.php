<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Return the unread database notification count for the authenticated user.
     */
    public function unread(Request $request): JsonResponse
    {
        $user = $this->resolveAuthenticatedUser($request);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Resolve authenticated user from request context.
     */
    protected function resolveAuthenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return $user;
    }
}
