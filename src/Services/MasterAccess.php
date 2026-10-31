<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Bangsamu\Master\Support\MasterModules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan izin data master per modul + per baris (pemilik app_code/company_id, lihat MasterScope):
 *
 *  1. Di hub (Master Data): is_admin dan permission master lokal (tim data pusat) boleh semua pemilik.
 *  2. is_admin aplikasi klien tetap terbatas ke app_code aplikasinya (semua company), tidak ke data aplikasi lain.
 *  3. Di hub dengan session delegasi (datang dari aplikasi lain lewat launch token): modul + aksi harus didelegasikan,
 *     dan baris harus milik aplikasi + company asal; delegasi all_co (admin aplikasi asal) boleh semua company
 *     aplikasi itu termasuk company 0.
 *  4. Di aplikasi klien: permission lokal, dan baris tabel ber-scope harus milik aplikasi ini + company user;
 *     ALL_COMPANY_PERMISSION (default is_admin) boleh semua company aplikasi ini termasuk company 0.
 *
 * Nama permission dari MasterModules (nama baru + alias lama).
 */
class MasterAccess
{
    public function __construct(protected MasterScope $scope) {}

    protected function user()
    {
        return auth()->user();
    }

    /** cek satu permission lewat helper app checkPermission() (konvensi semua aplikasi), fallback Gate */
    protected function check(string $permission): bool
    {
        if ($permission === '' || $this->user() === null) {
            return false;
        }

        return function_exists('checkPermission') ? (bool) checkPermission($permission) : (bool) $this->user()->can($permission);
    }

    public function isAdmin(): bool
    {
        return $this->check('is_admin');
    }

    /** permission lokal (Spatie) untuk modul + aksi, termasuk alias lama */
    public function hasPermission(string $slug, string $ability): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        foreach (MasterModules::permissionNames($slug, $ability) as $name) {
            if ($this->check($name)) {
                return true;
            }
        }

        return false;
    }

    /** boleh mengelola baris company 0 (berlaku semua company) di aplikasi sendiri */
    public function canManageAllCompanies(): bool
    {
        return $this->isAdmin() || $this->check((string) config('MasterCrudConfig.ALL_COMPANY_PERMISSION', 'is_admin'));
    }

    /** izin tingkat modul (tombol Tambah, menu, route), tanpa cek baris */
    public function can(string $slug, string $ability): bool
    {
        return $this->allows($slug, $ability);
    }

    /**
     * Izin modul + aksi, dan bila $row diisi (objek/array baris atau id) juga cek pemilik baris.
     */
    public function allows(string $slug, string $ability, mixed $row = null): bool
    {
        if ($this->user() === null) {
            return false;
        }

        $table = MasterModules::table($slug);

        if ($this->scope->isHub()) {
            // is_admin / tim master-data: semua pemilik
            if ($this->hasPermission($slug, $ability)) {
                return true;
            }

            $delegation = $this->scope->delegation();
            if ($delegation === null) {
                return false;
            }
            if ($ability === 'read') {
                return $this->delegatesTable($delegation, $table);
            }
            if (! in_array($ability, (array) ($delegation['mods'][$table] ?? []), true)) {
                return false;
            }

            return $row === null || $this->rowOwnedBy($table, $row, (string) $delegation['app_code'], (int) $delegation['company_id'], ! empty($delegation['all_co']));
        }

        // aplikasi klien (termasuk is_admin-nya): hanya baris milik aplikasi ini + company user
        if (! $this->hasPermission($slug, $ability)) {
            return false;
        }

        return $row === null || $ability === 'read'
            || $this->rowOwnedBy($table, $row, $this->scope->appCode(), $this->scope->userCompanyId(), $this->canManageAllCompanies());
    }

    /** baris dalam scope (app_code, company) tertentu; tabel tanpa app_code tidak pernah lolos lewat jalur ini */
    public function rowOwnedBy(string $table, mixed $row, string $appCode, int $companyId, bool $allCompanies): bool
    {
        if (! $this->scope->hasAppCode($table)) {
            // tabel global: di klien boleh dengan permission (perilaku lama), delegasi tidak mencakup tabel global
            return ! $this->scope->isHub();
        }

        $row = $this->loadRow($table, $row);
        if ($row === null) {
            return false;
        }

        $owner = $this->scope->ownerOf($row);
        if ($owner['app_code'] !== $appCode) {
            return false;
        }
        if (! $this->scope->hasCompany($table)) {
            return true;
        }

        // pengelola semua company (admin aplikasi / delegasi all_co): company mana pun di aplikasinya, termasuk 0
        return $allCompanies || $owner['company_id'] === $companyId;
    }

    /**
     * @param  array<string, mixed>  $delegation
     */
    protected function delegatesTable(array $delegation, string $table): bool
    {
        return array_key_exists($table, (array) ($delegation['mods'] ?? []));
    }

    /** baris lengkap (kolom pemilik) dari objek/array/id; objek tanpa kolom pemilik dibaca ulang dari DB */
    protected function loadRow(string $table, mixed $row): ?object
    {
        if (is_array($row)) {
            $row = (object) $row;
        }
        if (is_object($row) && property_exists($row, 'app_code') && (! $this->scope->hasCompany($table) || property_exists($row, 'company_id'))) {
            return $row;
        }
        if (is_object($row) && isset($row->app_code) && (! $this->scope->hasCompany($table) || isset($row->company_id))) {
            return $row; // model Eloquent (atribut magic)
        }

        $id = is_object($row) ? ($row->id ?? $row->No ?? null) : $row;
        if ($id === null || $id === '') {
            return null;
        }

        return DB::table($table)->where('id', $id)->first();
    }

    /** abort 403 bila tidak boleh */
    public function authorize(string $slug, string $ability, mixed $row = null): void
    {
        $table = MasterModules::table($slug);
        if (is_scalar($row) && $this->scope->hasAppCode($table)) {
            $row = DB::table($table)->where('id', $row)->first() ?? abort(404);
        }
        if (! $this->allows($slug, $ability, $row)) {
            abort(403, $this->denyMessage($slug, $ability, $row !== null));
        }
    }

    /**
     * Cek simpan dari form store (id terisi = update baris itu, kosong = tambah).
     */
    public function authorizeStore(string $slug, Request $request): ?object
    {
        if (! $request->filled('id')) {
            $this->authorize($slug, 'create');

            return null;
        }

        $table = MasterModules::table($slug);
        $row = DB::table($table)->where('id', $request->input('id'))->first();
        if ($row === null) {
            abort(404);
        }
        $this->authorize($slug, 'update', $row);

        return $row;
    }

    protected function denyMessage(string $slug, string $ability, bool $withRow): string
    {
        $delegation = $this->scope->delegation();
        if ($withRow && $delegation !== null) {
            return 'Data ini bukan milik '.$this->scope->ownerLabel(['app_code' => $delegation['app_code'], 'company_id' => (int) $delegation['company_id']]).', tidak dapat diubah.';
        }
        if ($withRow && Schema::hasTable(MasterModules::table($slug)) && $this->scope->isScoped(MasterModules::table($slug)) && ! $this->scope->isHub()) {
            return 'Data ini bukan milik aplikasi/company Anda, tidak dapat diubah.';
        }

        return 'Anda tidak punya izin '.$ability.' data master '.$slug.'.';
    }
}
