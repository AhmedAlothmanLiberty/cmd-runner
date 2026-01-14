<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function markRead(string $id, Request $request): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $redirect = $request->input('redirect');

        if (is_string($redirect) && $redirect !== '') {
            return redirect($redirect);
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->limit(6)->get();

        $payload = $notifications->map(function ($notification) {
            $data = $notification->data;

            return [
                'id' => $notification->id,
                'title' => $data['title'] ?? 'Task update',
                'task_title' => $data['task_title'] ?? '—',
                'assigned_name' => $data['assigned_name'] ?? 'Unassigned',
                'status' => $data['status'] ?? null,
                'actor_name' => $data['actor_name'] ?? null,
                'comment' => $data['comment'] ?? null,
                'url' => $data['url'] ?? route('admin.tasks.index'),
                'read_at' => $notification->read_at,
                'created_human' => $notification->created_at?->diffForHumans() ?? '—',
            ];
        });

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $payload,
        ]);
    }
}
