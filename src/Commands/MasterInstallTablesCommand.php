<?php

declare(strict_types=1);

namespace Bangsamu\Master\Commands;

use Bangsamu\Master\Services\MasterTableInstaller;
use Illuminate\Console\Command;

class MasterInstallTablesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'master:install-tables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Buat tabel master_* lokal yang belum ada & upgrade skema tabel lama (skema dari model package master, isi lewat sync)';

    /**
     * Execute the console command.
     */
    public function handle(MasterTableInstaller $installer): int
    {
        $result = $installer->install();

        foreach ($result['created'] as $table) {
            $this->line("  <info>created</info> {$table}");
        }

        foreach ($result['upgraded'] as $table => $changes) {
            $this->line("  <comment>upgraded</comment> {$table}: ".implode(', ', $changes));
        }

        foreach ($result['failed'] as $table => $message) {
            $this->error("  gagal {$table}: {$message}");
        }

        $this->info(count($result['created']).' tabel master dibuat, '.count($result['upgraded']).' di-upgrade, '.count($result['failed']).' gagal.');

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
