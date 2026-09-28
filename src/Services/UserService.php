<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Bangsamu\Master\Models\User;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UserService
{
    /**
     * Validate the provided token against SSO configuration HMAC / MD5 / static token.
     */
    public function validateToken(string $data, string $token, ?int $status = null): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $staticToken = (string) config('SsoConfig.main.TOKEN', '');
        if ($staticToken !== '' && hash_equals($staticToken, $token)) {
            return true;
        }

        $key = (string) config('SsoConfig.main.KEY', '');
        if ($key === '') {
            Log::warning('[UserService] SsoConfig.main.KEY is empty, cannot validate HMAC token.');

            return false;
        }

        // 1. HMAC SHA-256 on $data (Default in UserStatusSyncService)
        $expectedHmac = hash_hmac('sha256', $data, $key);
        if (hash_equals($expectedHmac, $token)) {
            return true;
        }

        // 2. MD5 on $data:$key (Legacy token support)
        $expectedMd5 = md5($data.':'.$key);
        if (hash_equals($expectedMd5, $token)) {
            return true;
        }

        // 3. Fallback check with data:status if status is provided
        if ($status !== null) {
            $dataWithStatus = $data.':'.$status;
            if (hash_equals(hash_hmac('sha256', $dataWithStatus, $key), $token)) {
                return true;
            }
            if (hash_equals(md5($dataWithStatus.':'.$key), $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize status input into integer (1 for active, 0 for inactive).
     */
    public function normalizeStatus(mixed $status): ?int
    {
        if (is_bool($status)) {
            return $status ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $status));

        if (in_array($normalized, ['1', 'active', 'true'], true)) {
            return 1;
        }

        if (in_array($normalized, ['0', 'inactive', 'false'], true)) {
            return 0;
        }

        return null;
    }

    /**
     * Synchronize user active/inactive status in local database.
     * Uses Bangsamu\Master\Models\User which points to default local DB (master_user table).
     *
     * @return array{status: bool, code: int, message: string, data?: array<string, mixed>}
     */
    public function syncUserStatus(string $email, mixed $status, string $token): array
    {
        $email = trim($email);
        $normalizedStatus = $this->normalizeStatus($status);

        // 1. Verify Token
        if (! $this->validateToken($email, $token, $normalizedStatus)) {
            Log::warning("[UserService] Unauthorized sync attempt for '{$email}'. Invalid token.");

            return [
                'status' => false,
                'code' => Response::HTTP_UNAUTHORIZED,
                'message' => 'Unauthorized: Invalid or missing token.',
            ];
        }

        // 2. Validate normalized status
        if ($normalizedStatus === null) {
            return [
                'status' => false,
                'code' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Invalid status value. Use 1 (active) or 0 (inactive).',
            ];
        }

        // 3. Find User in default local connection (table master_user)
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->first();

        if (! $user) {
            Log::notice("[UserService] User not found for email '{$email}' in local database.");

            return [
                'status' => false,
                'code' => Response::HTTP_NOT_FOUND,
                'message' => "User not found for '{$email}'.",
            ];
        }

        // 4. Update status flag
        $prevStatus = (int) ($user->is_active ?? 0);
        $user->is_active = $normalizedStatus;
        $user->save();

        $statusLabel = ($normalizedStatus === 1) ? 'active' : 'inactive';
        $prevStatusLabel = ($prevStatus === 1) ? 'active' : 'inactive';

        try {
            Log::info("[UserService] User '{$user->email}' status updated: {$prevStatusLabel} -> {$statusLabel}", [
                'user_id' => $user->id,
                'email' => $user->email,
                'previous_status' => $prevStatus,
                'current_status' => $normalizedStatus,
            ]);
        } catch (Throwable) {
            // Ignore logging failures
        }

        return [
            'status' => true,
            'code' => Response::HTTP_OK,
            'message' => "User '{$user->email}' status successfully set to {$statusLabel}.",
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'previous_status' => $prevStatus,
                'current_status' => $normalizedStatus,
                'status_label' => $statusLabel,
            ],
        ];
    }

    /**
     * Get cursor-paginated users for Big Data scalability.
     */
    public function getUsersCursorPaginated(int $perPage = 25, ?string $search = null): CursorPaginator
    {
        $query = User::query();

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        return $query->orderBy('id', 'desc')->cursorPaginate($perPage);
    }

    /**
     * Retrieve single user by ID or Email.
     */
    public function getUser(int|string $identifier): ?User
    {
        if (is_numeric($identifier)) {
            return User::find((int) $identifier);
        }

        return User::where('email', (string) $identifier)->first();
    }
}
