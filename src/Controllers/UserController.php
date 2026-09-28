<?php

declare(strict_types=1);

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use Bangsamu\Master\Requests\SyncUserStatusRequest;
use Bangsamu\Master\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __construct(
        protected readonly UserService $userService
    ) {}

    /**
     * Endpoint to sync user active / inactive status in local database.
     * Compatible with master-data's UserStatusSyncService cURL requests.
     */
    public function syncStatus(SyncUserStatusRequest $request): JsonResponse
    {
        $result = $this->userService->syncUserStatus(
            (string) $request->input('email_id'),
            $request->input('status'),
            (string) $request->input('token')
        );

        return response()->json($result, $result['code'] ?? Response::HTTP_OK);
    }

    /**
     * Cursor-paginated user list for Big Data efficiency.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 25);
        $search = $request->input('search');

        $users = $this->userService->getUsersCursorPaginated($perPage, $search ? (string) $search : null);

        return response()->json([
            'status' => true,
            'code' => Response::HTTP_OK,
            'data' => $users->items(),
            'pagination' => [
                'per_page' => $users->perPage(),
                'next_cursor' => $users->nextCursor()?->encode(),
                'prev_cursor' => $users->previousCursor()?->encode(),
                'next_page_url' => $users->nextPageUrl(),
                'prev_page_url' => $users->previousPageUrl(),
            ],
        ]);
    }

    /**
     * Retrieve a single user by ID or Email.
     */
    public function show(int|string $id): JsonResponse
    {
        $user = $this->userService->getUser($id);

        if (! $user) {
            return response()->json([
                'status' => false,
                'code' => Response::HTTP_NOT_FOUND,
                'message' => "User '{$id}' not found.",
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => true,
            'code' => Response::HTTP_OK,
            'data' => $user,
        ]);
    }
}
