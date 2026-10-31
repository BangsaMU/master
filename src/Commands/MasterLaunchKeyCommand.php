<?php

namespace Bangsamu\Master\Commands;

use Bangsamu\Master\Services\MasterLaunchToken;
use Bangsamu\Master\Support\MasterModules;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Kunci launch token "Edit di Master Data".
 *  - di aplikasi klien: `master:launch-key` membuat pasangan kunci (privat ke .env, publik untuk hub);
 *  - di hub: `master:launch-key --register=APP32 --public=...` mendaftarkan kunci publik aplikasi itu,
 *    `--modules=master_item_code,master_uom` membatasi tabel yang boleh didelegasikan.
 */
class MasterLaunchKeyCommand extends Command
{
    protected $signature = 'master:launch-key
        {--register= : (hub) app_code aplikasi yang didaftarkan, mis. APP32}
        {--public= : (hub) kunci publik base64 dari aplikasi itu}
        {--modules= : (hub) daftar tabel yang boleh didelegasikan, dipisah koma; "*" = semua tabel ber-scope}
        {--show : (klien) tampilkan kunci publik dari MASTER_LAUNCH_PRIVATE_KEY yang terpasang}';

    protected $description = 'Buat/daftarkan kunci launch token Edit di Master Data (Ed25519)';

    public function handle(MasterLaunchToken $tokens): int
    {
        if ($appCode = $this->option('register')) {
            return $this->register($tokens, strtoupper(trim((string) $appCode)));
        }

        if ($this->option('show')) {
            $secret = (string) config('MasterCrudConfig.LAUNCH_PRIVATE_KEY');
            if ($secret === '') {
                $this->error('MASTER_LAUNCH_PRIVATE_KEY belum diisi.');

                return self::FAILURE;
            }
            $this->line(MasterLaunchToken::publicKeyFromSecret($secret));

            return self::SUCCESS;
        }

        $pair = MasterLaunchToken::generateKeyPair();
        $this->info('Simpan di .env aplikasi ini (rahasia, jangan di-commit):');
        $this->line('MASTER_LAUNCH_PRIVATE_KEY='.$pair['secret']);
        $this->newLine();
        $this->info('Daftarkan di aplikasi Master Data:');
        $this->line('php artisan master:launch-key --register='.config('SsoConfig.main.APP_CODE').' --public='.$pair['public']);

        return self::SUCCESS;
    }

    protected function register(MasterLaunchToken $tokens, string $appCode): int
    {
        if ($public = $this->option('public')) {
            try {
                $tokens->registerPublicKey($appCode, (string) $public);
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->info("Kunci publik {$appCode} didaftarkan.");
        }

        if (($modules = $this->option('modules')) !== null) {
            $tables = array_values(array_filter(array_map('trim', explode(',', (string) $modules))));
            if ($tables === ['*']) {
                $tables = array_values(array_map(fn ($slug) => MasterModules::table($slug), array_keys(MasterModules::MODULES)));
            }
            $unknown = array_filter($tables, fn ($table) => MasterModules::slugForTable($table) === null);
            if ($unknown !== []) {
                $this->error('Tabel tidak dikenal: '.implode(', ', $unknown));

                return self::FAILURE;
            }
            $tokens->setAllowedTables($appCode, $tables);
            $this->info("Modul delegasi {$appCode}: ".implode(', ', $tables));
        }

        if (! $this->option('public') && $this->option('modules') === null) {
            $this->line($tokens->publicKeyFor($appCode) !== null ? "Kunci publik {$appCode} terdaftar." : "Kunci publik {$appCode} belum terdaftar.");
            $allowed = $tokens->allowedTables($appCode);
            $this->line('Modul delegasi: '.($allowed === null ? 'default (semua tabel ber-scope)' : implode(', ', $allowed)));
        }

        return self::SUCCESS;
    }
}
