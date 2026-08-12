<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransferNotification;
use App\Services\NotificationDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'max:24'],
            'channel' => ['nullable', 'in:email,whatsapp'],
        ]);

        $notifications = TransferNotification::query()
            ->with('transfer:id,code,status')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->string('channel')))
            ->latest()
            ->paginate(50);

        return response()->json($notifications);
    }

    public function sendPending(Request $request, NotificationDeliveryService $deliveryService): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'data' => $deliveryService->sendPending($data['limit'] ?? 50),
        ]);
    }
}
