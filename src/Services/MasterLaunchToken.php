<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Bangsamu\Master\Models\DashboardSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Launch token "Edit di Master Data": aplikasi klien (mis. SPB APP32) menandatangani identitas user, company, dan
 * izin modul master yang ia punya di aplikasi itu; aplikasi Master Data (hub) memverifikasi lalu me-login-kan user
 * dengan session delegasi (lihat MasterScope::SESSION_KEY, MasterAccess).
 *
 * Tanda tangan Ed25519 (ekstensi sodium): kunci privat hanya di .env aplikasi klien (MASTER_LAUNCH_PRIVATE_KEY),
 * kunci publik didaftarkan di hub (dashboard_settings group master_launch, key master_launch.public_key.{APP}).
 * Kunci publik aman dibaca aplikasi lain yang berbagi db_master, berbeda dengan HMAC/SSO_KEY bersama. Token berlaku
 * singkat (LAUNCH_TTL) dan sekali pakai (jti di cache).
 *
 * Format: mlt1.<base64url(payload JSON)>.<base64url(signature)>
 */
class MasterLaunchToken
{
    public const PREFIX = 'mlt1.';

    public const SETTING_GROUP = 'master_launch';

    /** batas atas umur token walau LAUNCH_TTL diisi besar (detik) */
    public const MAX_TTL = 600;

    public function __construct(protected MasterScope $scope) {}

    /**
     * Pasangan kunci baru (base64): secret untuk .env klien, public untuk didaftarkan di hub.
     *
     * @return array{secret: string, public: string}
     */
    public static function generateKeyPair(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
            'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
        ];
    }

    public static function publicKeyFromSecret(string $secret): string
    {
        return base64_encode(sodium_crypto_sign_publickey_from_secretkey(self::decodeKey($secret, SODIUM_CRYPTO_SIGN_SECRETKEYBYTES)));
    }

    /** klien bisa membuat token: kunci privat terisi */
    public function canIssue(): bool
    {
        return filled(config('MasterCrudConfig.LAUNCH_PRIVATE_KEY'));
    }

    public function ttl(): int
    {
        return max(10, min(self::MAX_TTL, (int) config('MasterCrudConfig.LAUNCH_TTL', 120)));
    }

    /**
     * Buat token. $claims minimal: sub (email), cid (company), mods (tabel => aksi), next (path di hub).
     *
     * @param  array<string, mixed>  $claims
     */
    public function issue(array $claims): string
    {
        $secret = self::decodeKey((string) config('MasterCrudConfig.LAUNCH_PRIVATE_KEY'), SODIUM_CRYPTO_SIGN_SECRETKEYBYTES);
        $now = time();

        $payload = array_merge($claims, [
            'v' => 1,
            'iss' => $this->scope->appCode(),
            'aud' => $this->scope->globalAppCode(),
            'iat' => $now,
            'exp' => $now + $this->ttl(),
            'jti' => (string) Str::uuid(),
        ]);

        $body = self::b64url(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $signature = sodium_crypto_sign_detached(self::PREFIX.$body, $secret);

        return self::PREFIX.$body.'.'.self::b64url($signature);
    }

    /**
     * Verifikasi token di hub dan tandai jti terpakai.
     *
     * @return array<string, mixed> payload
     *
     * @throws RuntimeException pesan alasan penolakan (untuk log; user cukup diberi pesan umum)
     */
    public function verify(string $token): array
    {
        if (! str_starts_with($token, self::PREFIX) || substr_count($token, '.') !== 2) {
            throw new RuntimeException('format token salah');
        }

        [, $body, $signature] = explode('.', $token);
        $payload = json_decode((string) self::b64urlDecode($body), true);
        if (! is_array($payload) || ($payload['v'] ?? null) !== 1 || empty($payload['iss']) || empty($payload['sub']) || empty($payload['jti'])) {
            throw new RuntimeException('isi token tidak lengkap');
        }

        $publicKey = $this->publicKeyFor((string) $payload['iss']);
        if ($publicKey === null) {
            throw new RuntimeException('kunci publik aplikasi '.$payload['iss'].' belum didaftarkan');
        }

        $rawSignature = self::b64urlDecode($signature);
        if ($rawSignature === null || strlen($rawSignature) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($rawSignature, self::PREFIX.$body, $publicKey)) {
            throw new RuntimeException('tanda tangan tidak valid');
        }

        $now = time();
        if (($payload['aud'] ?? null) !== $this->scope->appCode()) {
            throw new RuntimeException('token bukan untuk aplikasi ini');
        }
        if ((int) ($payload['exp'] ?? 0) < $now) {
            throw new RuntimeException('token kedaluwarsa');
        }
        if ((int) ($payload['iat'] ?? 0) > $now + 60 || (int) $payload['exp'] - (int) $payload['iat'] > self::MAX_TTL) {
            throw new RuntimeException('waktu token tidak wajar');
        }
        if (! Cache::add('master_launch_jti:'.$payload['jti'], 1, (int) $payload['exp'] - $now + 60)) {
            throw new RuntimeException('token sudah dipakai');
        }

        return $payload;
    }

    /** kunci publik biner aplikasi, null bila belum terdaftar / rusak */
    public function publicKeyFor(string $appCode): ?string
    {
        $value = $this->setting('public_key.'.$appCode);
        if ($value === null) {
            return null;
        }

        try {
            return self::decodeKey($value, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
        } catch (RuntimeException $e) {
            return null;
        }
    }

    public function registerPublicKey(string $appCode, string $publicKey): void
    {
        self::decodeKey($publicKey, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES); // validasi
        $this->putSetting('public_key.'.$appCode, trim($publicKey), 'Kunci publik launch '.$appCode);
    }

    /**
     * Tabel yang boleh didelegasikan aplikasi ini; null = default (semua tabel ber-scope di hub).
     *
     * @return list<string>|null
     */
    public function allowedTables(string $appCode): ?array
    {
        $value = $this->setting('modules.'.$appCode);
        if ($value === null) {
            return null;
        }
        $tables = json_decode($value, true);

        return is_array($tables) ? array_values(array_filter($tables, 'is_string')) : [];
    }

    /**
     * @param  list<string>  $tables
     */
    public function setAllowedTables(string $appCode, array $tables): void
    {
        $this->putSetting('modules.'.$appCode, json_encode(array_values($tables)), 'Modul master yang boleh didelegasikan '.$appCode, 'json');
    }

    protected function setting(string $key): ?string
    {
        if (! Schema::hasTable('dashboard_settings')) {
            return null;
        }
        $value = DashboardSettings::query()->where('key', self::SETTING_GROUP.'.'.$key)->value('value');

        return filled($value) ? (string) $value : null;
    }

    protected function putSetting(string $key, string $value, string $label, string $type = 'text'): void
    {
        DashboardSettings::query()->updateOrCreate(
            ['key' => self::SETTING_GROUP.'.'.$key],
            ['value' => $value, 'group' => self::SETTING_GROUP, 'type' => $type, 'label' => $label]
        );
    }

    public static function decodeKey(string $value, int $length): string
    {
        $raw = base64_decode(trim($value), true);
        if ($raw === false || strlen($raw) !== $length) {
            throw new RuntimeException('kunci launch tidak valid (harus base64 '.$length.' byte)');
        }

        return $raw;
    }

    protected static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    protected static function b64urlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
