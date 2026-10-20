<?php

namespace Bangsamu\Master\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Token "launch" untuk viewer annotation.
 *
 * Token dibuat di route annotation.open (navigasi top-level, cookie app terkirim normal)
 * dan berisi user, file, session id, dan waktu kedaluwarsa. Saat service annotation
 * memanggil api/getcurrentuser / getSignature / getParaf, token didekripsi dan session id
 * di dalamnya dicek ulang ke session store app (server-side, tanpa cookie lintas-origin).
 */
class AnnotationLaunchTokenService
{
    public const PREFIX = 'alt1.';

    public const MODE_LEGACY = 'legacy';

    public const MODE_LAUNCH = 'launch';

    public function mode(): string
    {
        $mode = strtolower((string) config('AnnotationConfig.launch.TOKEN_MODE', self::MODE_LEGACY));

        return $mode === self::MODE_LAUNCH ? self::MODE_LAUNCH : self::MODE_LEGACY;
    }

    public function isLaunchMode(): bool
    {
        return $this->mode() === self::MODE_LAUNCH;
    }

    /**
     * Token lama (api_token / md5(email)) selalu diterima di mode legacy,
     * di mode launch hanya bila ANNOTATION_ACCEPT_LEGACY_TOKEN=true.
     */
    public function acceptsLegacyToken(): bool
    {
        if (! $this->isLaunchMode()) {
            return true;
        }

        return filter_var(config('AnnotationConfig.launch.ACCEPT_LEGACY_TOKEN', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function checksSession(): bool
    {
        return filter_var(config('AnnotationConfig.launch.CHECK_SESSION', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function acceptsStoredImageToken(): bool
    {
        return filter_var(config('AnnotationConfig.launch.IMAGE_TOKEN_PERMANENT', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function ttlMinutes(): int
    {
        $ttl = (int) config('AnnotationConfig.launch.TOKEN_TTL', 120);

        return $ttl > 0 ? $ttl : 120;
    }

    public function isLaunchToken($token): bool
    {
        return is_string($token) && str_starts_with($token, self::PREFIX);
    }

    /**
     * Buat token terenkripsi untuk user + file + session saat ini.
     */
    public function issue(Authenticatable $user, string $fileUrl, string $sessionId, ?string $guard = null): string
    {
        $now = now()->getTimestamp();
        $payload = [
            'v' => 1,
            'uid' => $user->getAuthIdentifier(),
            'em' => $user->email ?? null,
            'f' => $fileUrl,
            'sid' => $sessionId,
            'g' => $guard ?? Auth::getDefaultDriver(),
            'app' => config('SsoConfig.main.APP_CODE'),
            'iat' => $now,
            'exp' => $now + ($this->ttlMinutes() * 60),
        ];

        // output Crypt sudah base64, dibuat url-safe karena viewer JS menyambung token ke URL tanpa encode
        $encrypted = Crypt::encryptString(json_encode($payload));

        return self::PREFIX.rtrim(strtr($encrypted, '+/', '-_'), '=');
    }

    /**
     * Dekripsi token. null bila format salah / dipalsukan.
     */
    public function decode($token): ?array
    {
        if (! $this->isLaunchToken($token)) {
            return null;
        }

        $encrypted = strtr(substr($token, strlen(self::PREFIX)), '-_', '+/');
        $padding = strlen($encrypted) % 4;
        if ($padding) {
            $encrypted .= str_repeat('=', 4 - $padding);
        }

        try {
            $payload = json_decode(Crypt::decryptString($encrypted), true);
        } catch (DecryptException $e) {
            return null;
        }

        if (! is_array($payload) || ($payload['v'] ?? null) !== 1 || empty($payload['uid']) || empty($payload['sid'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Validasi token launch lengkap dan kembalikan user-nya, null bila tidak valid.
     */
    public function resolveUser($token, $fileName = null)
    {
        $payload = $this->decode($token);
        if ($payload === null) {
            return $this->reject('token tidak valid / dipalsukan');
        }

        if ((int) ($payload['exp'] ?? 0) < now()->getTimestamp()) {
            return $this->reject('token kedaluwarsa', $payload);
        }

        if (! empty($fileName) && self::normalizeFileName($fileName) !== self::normalizeFileName($payload['f'] ?? '')) {
            return $this->reject('file tidak sesuai token', $payload, ['file_name' => $fileName]);
        }

        $guard = $payload['g'] ?? null;
        $user = $this->retrieveUser($payload['uid'], $guard);
        if (! $user || strcasecmp((string) ($user->email ?? ''), (string) ($payload['em'] ?? '')) !== 0) {
            return $this->reject('user tidak ditemukan', $payload);
        }

        if ($this->checksSession() && ! $this->sessionBelongsToUser($payload['sid'], $payload['uid'], $guard)) {
            return $this->reject('session sudah tidak aktif', $payload);
        }

        return $user;
    }

    /**
     * Validasi token untuk gambar tanda tangan / paraf. Viewer menyimpan URL getSignature/getParaf (berisi token
     * launch) ke anotasi dan memuatnya lagi tiap dokumen dibuka, jadi token yang asli (terdekripsi dengan APP_KEY
     * app, user-nya masih ada) tetap diterima setelah kedaluwarsa, untuk file lain, atau setelah session berakhir.
     * Dulu tanda tangan lama hilang di viewer begitu session penanda tangan habis (401).
     */
    public function resolveImageUser($token)
    {
        if (! $this->acceptsStoredImageToken()) {
            return $this->resolveUser($token);
        }

        $payload = $this->decode($token);
        if ($payload === null) {
            return $this->reject('token tidak valid / dipalsukan');
        }

        $user = $this->retrieveUser($payload['uid'], $payload['g'] ?? null);
        if (! $user || strcasecmp((string) ($user->email ?? ''), (string) ($payload['em'] ?? '')) !== 0) {
            return $this->reject('user tidak ditemukan', $payload);
        }

        if ((int) ($payload['exp'] ?? 0) < now()->getTimestamp()) {
            Log::info('[AnnotationLaunchToken] token gambar kedaluwarsa tetap diterima', ['uid' => $payload['uid'], 'file' => $payload['f'] ?? null]);
        }

        return $user;
    }

    /**
     * Cek session id masih ada di session store (redis/file/database) dan login sebagai user yang sama.
     */
    public function sessionBelongsToUser(string $sessionId, $userId, ?string $guard = null): bool
    {
        try {
            $guardInstance = Auth::guard($guard);
            if (! method_exists($guardInstance, 'getName')) {
                return false;
            }

            // store terpisah supaya session request saat ini (bila ada) tidak tersentuh dan tidak disimpan
            $store = clone app('session')->driver();
            $store->flush();
            $store->setId($sessionId);
            if ($store->getId() !== $sessionId) {
                return false;
            }
            $store->start();

            $loggedInId = $store->get($guardInstance->getName());

            return $loggedInId !== null && (string) $loggedInId === (string) $userId;
        } catch (\Throwable $e) {
            Log::warning('[AnnotationLaunchToken] gagal membaca session', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * URL viewer di service annotation, format sama dengan link lama.
     */
    public function viewerUrl(string $fileUrl, string $token, ?string $callback = null): string
    {
        $query = http_build_query([
            'app_id' => config('SsoConfig.main.APP_CODE'),
            'file_url' => base64_encode($fileUrl),
            'token' => $token,
            'callback' => base64_encode($callback ?? url('api/setAnnotationId')),
        ]);

        return rtrim((string) config('AnnotationConfig.main.URL'), '/').'/viewer?'.$query;
    }

    /**
     * Link lama (token md5(email)), dipakai helper saat mode legacy.
     */
    public function legacyViewerUrl(string $fileUrl, ?string $callback = null): ?string
    {
        $user = Auth::user();
        if (! $user || empty($user->email)) {
            return null;
        }

        return config('AnnotationConfig.main.URL').'/viewer?app_id='.config('SsoConfig.main.APP_CODE')
            .'&file_url='.base64_encode($fileUrl)
            .'&token='.md5($user->email)
            .'&callback='.base64_encode($callback ?? url('api/setAnnotationId'));
    }

    /**
     * Link yang dipakai app: mode legacy = link viewer lama, mode launch = route annotation.open.
     */
    public function launchUrl(?string $fileUrl, ?string $callback = null): ?string
    {
        if (empty($fileUrl)) {
            return null;
        }

        $fileUrl = $this->absoluteUrl($fileUrl);
        $callback = $callback !== null ? $this->absoluteUrl($callback) : null;

        if (! $this->isLaunchMode()) {
            return $this->legacyViewerUrl($fileUrl, $callback);
        }

        return route('annotation.open', array_filter([
            'file_url' => $fileUrl,
            'callback' => $callback,
        ]));
    }

    /**
     * file_url / callback hanya boleh ke host app sendiri (atau ANNOTATION_ALLOWED_HOSTS),
     * supaya token tidak bisa dikirim ke domain lain lewat link /annotation/open buatan orang.
     */
    public function isAllowedUrl(?string $url, ?string $requestHost = null): bool
    {
        $parts = parse_url((string) $url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        $hostWithPort = isset($parts['port']) ? $host.':'.$parts['port'] : $host;

        return in_array($host, $this->allowedHosts($requestHost), true)
            || in_array($hostWithPort, $this->allowedHosts($requestHost), true);
    }

    public function allowedHosts(?string $requestHost = null): array
    {
        $hosts = array_map('trim', explode(',', (string) config('AnnotationConfig.launch.ALLOWED_HOSTS', '')));
        $hosts[] = parse_url((string) config('app.url'), PHP_URL_HOST);
        $hosts[] = $requestHost;

        return array_values(array_unique(array_filter(array_map(fn ($h) => strtolower((string) $h), $hosts))));
    }

    public function absoluteUrl(string $url): string
    {
        return preg_match('~^https?://~i', $url) ? $url : url($url);
    }

    /**
     * Samakan bentuk nama file: tanpa query string, tanpa folder, tanpa .pdf.
     * Viewer mengirim file_name tanpa ekstensi, token menyimpan file_url lengkap.
     */
    public static function normalizeFileName(?string $value): string
    {
        $value = (string) $value;
        $path = parse_url($value, PHP_URL_PATH);
        $path = $path !== null && $path !== false ? $path : Str::before($value, '?');
        $base = rawurldecode(basename($path));

        return strtolower(preg_replace('/\.pdf$/i', '', $base));
    }

    protected function retrieveUser($userId, ?string $guard = null)
    {
        try {
            $provider = Auth::guard($guard)->getProvider();

            return $provider ? $provider->retrieveById($userId) : null;
        } catch (\Throwable $e) {
            $model = '\\App\\Models\\User';

            return class_exists($model) ? $model::find($userId) : null;
        }
    }

    protected function reject(string $reason, array $payload = [], array $context = [])
    {
        Log::info('[AnnotationLaunchToken] ditolak: '.$reason, $context + [
            'uid' => $payload['uid'] ?? null,
            'file' => $payload['f'] ?? null,
        ]);

        return null;
    }
}
