<?php

declare(strict_types=1);

namespace Bangsamu\Master\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Awalan kode project per company (MasterCrudConfig.PROJECT_CODE_PREFIX, env MASTER_PROJECT_CODE_PREFIX). Company yang
 * terdaftar hanya boleh memakai kode berawalan salah satu awalannya, mis. `ME:1,2,3,8,9;MEB:7` = kode company ME harus
 * berawalan 1, 2, 3, 8, atau 9 dan kode company MEB berawalan 7 (kode 73034 untuk ME ditolak). Company yang tidak
 * terdaftar bebas, termasuk memakai awalan company lain. Kosong (bawaan paket) = tanpa aturan.
 *
 * "Kode company" = Project Code untuk company pemilik project (Create Project, Edit kode, Import Project baru, Pindah
 * pemilik) dan Project Code Client untuk baris Project Detail company itu (tambah, ubah kode, Import baris baru, Pindah
 * company). Project baru yang juga mendapat baris company pembuat (session delegasi) dicek untuk company itu juga,
 * karena barisnya memakai kode yang sama. Kode yang tidak berubah tidak dicek, jadi data lama di luar aturan tetap
 * bisa diubah nama/tanggalnya; Restore dan Buat Detail (cermin kode yang sudah ada) juga tidak dicek.
 *
 * Bentuk config: string `KODE_COMPANY:awalan,awalan;KODE_COMPANY:awalan` atau array ['ME' => ['1', '2'], 'MEB' => '7'].
 * Kode company dan awalan tidak membedakan huruf besar/kecil; awalan boleh lebih dari satu karakter (mis. `71`).
 *
 * Sejak v3.23.0 juga panjang maksimal kode (MasterCrudConfig.PROJECT_CODE_MAX_LENGTH, env MASTER_PROJECT_CODE_MAX_LENGTH,
 * kosong/0 = tanpa batas tambahan selain kolom), untuk semua company. Sejak v3.24.0 (permintaan user 2026-11-26
 * "perketat") panjang dicek di SETIAP simpan, juga bila kodenya tidak berubah: Create, Update, Import (baris baru dan
 * lama), Pindah pemilik, Pindah company. Data lama yang kodenya lebih panjang tidak bisa disimpan sebelum kodenya
 * dipendekkan. Restore dan Buat Detail tetap tidak dicek. Awalan tetap hanya dicek bila kode baru / berubah.
 */
class ProjectCodePrefix
{
    /** @var array<int, ?string> master_company.id => company_code huruf besar (null = tidak ada), per request */
    protected array $companyCodes = [];

    /**
     * Aturan yang berlaku.
     *
     * @return array<string, list<string>> company_code huruf besar => awalan huruf besar
     */
    public function rules(): array
    {
        $config = config('MasterCrudConfig.PROJECT_CODE_PREFIX', []);
        if (is_string($config)) {
            $parsed = [];
            foreach (preg_split('/[;\r\n]+/', $config) ?: [] as $part) {
                if (str_contains($part, ':')) {
                    [$company, $prefixes] = explode(':', $part, 2);
                    $parsed[$company] = $prefixes;
                }
            }
            $config = $parsed;
        }

        $rules = [];
        foreach ((array) $config as $company => $prefixes) {
            $company = strtoupper(trim((string) $company));
            $list = is_array($prefixes) ? $prefixes : explode(',', (string) $prefixes);
            $list = array_values(array_unique(array_filter(
                array_map(fn ($prefix) => strtoupper(trim((string) $prefix)), $list),
                fn (string $prefix) => $prefix !== '',
            )));
            if ($company !== '' && $list !== []) {
                $rules[$company] = $list;
            }
        }

        return $rules;
    }

    /**
     * Awalan yang boleh dipakai company $companyId.
     *
     * @return list<string> kosong = bebas
     */
    public function prefixesFor(int $companyId): array
    {
        $rules = $this->rules();
        if ($rules === []) {
            return [];
        }
        $code = $this->companyCode($companyId);

        return $code !== null ? ($rules[$code] ?? []) : [];
    }

    /** Panjang maksimal kode project (MasterCrudConfig.PROJECT_CODE_MAX_LENGTH), null = tanpa batas tambahan. */
    public function maxLength(): ?int
    {
        $max = (int) config('MasterCrudConfig.PROJECT_CODE_MAX_LENGTH', 0);

        return $max > 0 ? $max : null;
    }

    /** Pesan bila $code lebih panjang dari maxLength(), null = sesuai / tanpa batas / kode kosong. */
    public function lengthMessage(?string $code, string $label = 'Project Code'): ?string
    {
        $code = strtoupper(trim((string) $code));
        $max = $this->maxLength();
        if ($code === '' || $max === null || mb_strlen($code) <= $max) {
            return null;
        }

        return $label.' '.$code.' melebihi '.$max.' karakter ('.mb_strlen($code).' karakter).';
    }

    /**
     * Aturan lengkap untuk kode yang diketik (baru / diubah): panjang maksimal lalu awalan company $companyId. null =
     * sesuai / bebas / kode kosong.
     */
    public function message(int $companyId, ?string $code, string $label = 'Project Code'): ?string
    {
        return $this->lengthMessage($code, $label) ?? $this->prefixMessage($companyId, $code, $label);
    }

    /**
     * Hanya awalan company $companyId, tanpa panjang maksimal.
     * null = sesuai / bebas / kode kosong.
     */
    public function prefixMessage(int $companyId, ?string $code, string $label = 'Project Code'): ?string
    {
        $code = strtoupper(trim((string) $code));
        $prefixes = $this->prefixesFor($companyId);
        if ($code === '' || $prefixes === [] || $this->matches($code, $prefixes)) {
            return null;
        }

        $company = (string) $this->companyCode($companyId);
        $message = $label.' '.$code.' tidak sesuai untuk company '.$company.': kode company '.$company.' harus berawalan '
            .$this->listLabel($prefixes).'.';
        foreach ($this->rules() as $otherCompany => $otherPrefixes) {
            if ($otherCompany === $company) {
                continue;
            }
            foreach ($otherPrefixes as $prefix) {
                if (str_starts_with($code, $prefix)) {
                    return $message.' Awalan '.$prefix.' milik company '.$otherCompany.'.';
                }
            }
        }

        return $message;
    }

    /**
     * Kode $code harus sesuai panjang maksimal dan awalan company $companyId.
     *
     * @throws ValidationException
     */
    public function assert(int $companyId, ?string $code, string $field = 'project_code', string $label = 'Project Code'): void
    {
        $message = $this->message($companyId, $code, $label);
        if ($message !== null) {
            throw ValidationException::withMessages([$field => [$message]]);
        }
    }

    /**
     * Kode $code tidak melebihi panjang maksimal (setiap simpan, sejak v3.24.0).
     *
     * @throws ValidationException
     */
    public function assertLength(?string $code, string $field = 'project_code', string $label = 'Project Code'): void
    {
        $message = $this->lengthMessage($code, $label);
        if ($message !== null) {
            throw ValidationException::withMessages([$field => [$message]]);
        }
    }

    /**
     * Simpan data lama (Update): panjang maksimal SELALU dicek (sejak v3.24.0, juga bila kode tidak berubah, jadi kode
     * lama yang lebih panjang harus dipendekkan dulu), awalan hanya bila kode berubah dari $oldCode (null = data baru,
     * selalu dicek): data lama dengan awalan di luar aturan tetap bisa disimpan selama kodenya tidak diubah.
     *
     * @throws ValidationException
     */
    public function assertChanged(int $companyId, ?string $code, ?string $oldCode, string $field = 'project_code', string $label = 'Project Code'): void
    {
        if ($oldCode !== null && strtoupper(trim((string) $code)) === strtoupper(trim($oldCode))) {
            $this->assertLength($code, $field, $label);

            return;
        }

        $this->assert($companyId, $code, $field, $label);
    }

    /**
     * Pesan pertama untuk beberapa company sekaligus (project baru: company pemilik lalu company pembuat), null = sesuai.
     *
     * @param  list<int>  $companyIds
     */
    public function messageForCompanies(array $companyIds, ?string $code, string $label = 'Project Code'): ?string
    {
        // panjang berlaku untuk semua company (juga bila daftar company kosong)
        if (($length = $this->lengthMessage($code, $label)) !== null) {
            return $length;
        }

        $owner = $companyIds[0] ?? null;
        foreach ($companyIds as $companyId) {
            $message = $this->prefixMessage((int) $companyId, $code, $label);
            if ($message !== null) {
                // baris company pembuat (session delegasi) memakai kode yang sama dengan project
                return (int) $companyId === (int) $owner ? $message : $message
                    .' Project baru yang dibuat lewat session company '.$this->companyCode((int) $companyId)
                    .' juga mendapat baris Project Detail company itu dengan kode yang sama.';
            }
        }

        return null;
    }

    /**
     * messageForCompanies() sebagai error validasi.
     *
     * @param  list<int>  $companyIds
     *
     * @throws ValidationException
     */
    public function assertForCompanies(array $companyIds, ?string $code, string $field = 'project_code', string $label = 'Project Code'): void
    {
        $message = $this->messageForCompanies($companyIds, $code, $label);
        if ($message !== null) {
            throw ValidationException::withMessages([$field => [$message]]);
        }
    }

    /**
     * Teks bantuan form, mis. "Maksimal 5 karakter · Awalan kode: ME 1, 2, 3, 8, 9 · MEB 7 · company lain bebas"; null =
     * tanpa aturan.
     */
    public function hint(): ?string
    {
        $parts = [];
        if (($max = $this->maxLength()) !== null) {
            $parts[] = 'Maksimal '.$max.' karakter';
        }

        $rules = $this->rules();
        if ($rules !== []) {
            $prefixes = [];
            foreach ($rules as $company => $list) {
                $prefixes[] = $company.' '.implode(', ', $list);
            }
            $parts[] = 'Awalan kode: '.implode(' · ', $prefixes).' · company lain bebas';
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    /** @param  list<string>  $prefixes */
    protected function matches(string $code, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $prefixes  "7" / "1 atau 2" / "1, 2, 3, 8, atau 9" */
    protected function listLabel(array $prefixes): string
    {
        if (count($prefixes) === 1) {
            return $prefixes[0];
        }
        $last = array_pop($prefixes);

        return implode(', ', $prefixes).(count($prefixes) > 1 ? ',' : '').' atau '.$last;
    }

    /** company_code company $companyId (baris terhapus juga), huruf besar; null = tidak ada. */
    protected function companyCode(int $companyId): ?string
    {
        if (! array_key_exists($companyId, $this->companyCodes)) {
            $code = $companyId > 0 && SchemaColumns::hasTable('master_company')
                ? DB::table('master_company')->where('id', $companyId)->value('company_code')
                : null;
            $this->companyCodes[$companyId] = filled($code) ? strtoupper(trim((string) $code)) : null;
        }

        return $this->companyCodes[$companyId];
    }
}
