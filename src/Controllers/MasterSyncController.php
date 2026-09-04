<?php

declare(strict_types=1);

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use Bangsamu\Master\Services\MasterDataSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class MasterSyncController extends Controller
{
    public function __construct(
        protected readonly MasterDataSyncService $syncService
    ) {}

    /**
     * Universal sync endpoint for all 15 master tables.
     */
    public function sync(Request $request): JsonResponse
    {
        $payload = $request->all();
        $chunkSize = (int) $request->input('chunk_size', 250);

        $result = $this->syncService->syncFromBroadcast($payload, $chunkSize);

        $statusCode = ($result['success'] ?? false)
            ? Response::HTTP_OK
            : Response::HTTP_UNPROCESSABLE_ENTITY;

        return response()->json($result, $statusCode);
    }

    /**
     * Trigger synchronization of master items (backward-compatible alias).
     */
    public function syncItems(Request $request): JsonResponse
    {
        return $this->sync($request);
    }

    /**
     * Authenticate private WebSocket channel subscription for client browser.
     *
     * Supports:
     * - private-user.{email}
     * - private-mcu.user.{email}
     * - private-App.Models.User.{id}
     */
    public function channelAuth(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $channelName = (string) $request->input('channel_name');
        $socketId = (string) $request->input('socket_id');

        if (empty($channelName) || empty($socketId)) {
            return response()->json([
                'message' => 'channel_name and socket_id are required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = Auth::user();
        $userEmail = strtolower((string) $user->email);

        // Clean channel name
        $cleanChannel = $channelName;
        if (Str::startsWith($cleanChannel, 'private-')) {
            $cleanChannel = Str::after($cleanChannel, 'private-');
        }

        $isAuthorized = false;

        // Check if channel is user-specific by email or ID
        if (Str::startsWith($cleanChannel, 'user.')) {
            $channelEmail = strtolower(Str::after($cleanChannel, 'user.'));
            $isAuthorized = ($channelEmail === $userEmail);
        } elseif (Str::startsWith($cleanChannel, 'mcu.user.')) {
            $channelEmail = strtolower(Str::after($cleanChannel, 'mcu.user.'));
            $isAuthorized = ($channelEmail === $userEmail);
        } elseif (Str::startsWith($cleanChannel, 'App.Models.User.')) {
            $channelUserId = Str::after($cleanChannel, 'App.Models.User.');
            $isAuthorized = ((string) $user->id === $channelUserId);
        }

        if (! $isAuthorized) {
            return response()->json([
                'message' => 'Forbidden. You do not have permission to join this channel.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Generate HMAC-SHA256 Pusher auth signature
        $reverbKey = (string) (config('broadcasting.connections.reverb.key')
            ?: config('broadcasting.connections.pusher.key')
            ?: env('REVERB_APP_KEY')
            ?: env('PUSHER_APP_KEY')
            ?: config('MasterConfig.senada.app_key')
            ?: 'senada_hub_key');

        $reverbSecret = (string) (config('broadcasting.connections.reverb.secret')
            ?: config('broadcasting.connections.pusher.secret')
            ?: env('REVERB_APP_SECRET')
            ?: env('PUSHER_APP_SECRET')
            ?: config('MasterConfig.senada.app_secret')
            ?: 'senada_hub_secret');

        $signature = hash_hmac('sha256', "{$socketId}:{$channelName}", $reverbSecret);
        $auth = "{$reverbKey}:{$signature}";

        return response()->json([
            'auth' => $auth,
        ], Response::HTTP_OK);
    }
}
