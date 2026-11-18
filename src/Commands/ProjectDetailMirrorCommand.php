<?php

namespace Bangsamu\Master\Commands;

use Bangsamu\Master\Services\ProjectOwnerDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * (Hub) Rapikan Project Detail company pemilik (MasterCrudConfig.PROJECT_OWNER_COMPANY_ID): tiap project aktif punya
 * satu baris pemilik (dibuat, atau dipulihkan bila terhapus) dengan project_code_client / project_name_client sama
 * dengan project_code / project_name. Baris company lain dan baris klien tidak disentuh; project dengan baris
 * pemilik ganda dilaporkan dan dilewati. `--dry-run` hanya menampilkan.
 */
class ProjectDetailMirrorCommand extends Command
{
    protected $signature = 'master:project-detail-mirror
        {--dry-run : Hanya tampilkan yang akan diubah, tanpa menulis}';

    protected $description = 'Samakan Project Detail company pemilik dengan master_project (buat/pulihkan baris pemilik, cermin kode dan nama)';

    private const LABELS = [
        'created' => 'dibuat',
        'restored' => 'dipulihkan',
        'updated' => 'disamakan',
        'duplicate' => 'dilewati (ganda)',
        'failed' => 'gagal',
    ];

    public function handle(ProjectOwnerDetail $ownerDetail): int
    {
        if (! $ownerDetail->enabled()) {
            $this->error('Perintah ini hanya untuk aplikasi Master Data (MASTER_HUB=true) dengan tabel master_project, master_project_detail, dan master_company.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $counts = array_fill_keys(array_keys(self::LABELS), 0);
        $rows = [];
        $checked = 0;

        DB::table(ProjectOwnerDetail::TABLE_PROJECT)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($projects) use ($ownerDetail, $dry, &$counts, &$rows, &$checked): void {
                foreach ($projects as $project) {
                    $checked++;
                    $result = $ownerDetail->reconcile($project, $dry);
                    if ($result['status'] === 'ok') {
                        continue;
                    }

                    $counts[$result['status']]++;
                    $rows[] = [
                        $project->project_code,
                        self::LABELS[$result['status']],
                        $result['before'] ?? '-',
                        $result['after'] ?? '-',
                        $result['note'] ?? '',
                    ];
                }
            });

        if ($rows !== []) {
            $this->table(['Project', $dry ? 'Akan' : 'Hasil', 'Sebelum (kode | nama client)', 'Sesudah (kode | nama client)', 'Keterangan'], $rows);
        }

        $summary = [];
        foreach (self::LABELS as $status => $label) {
            $summary[] = $counts[$status].' '.$label;
        }
        $this->line(($dry ? '[dry-run] ' : '').$checked.' project aktif diperiksa, company pemilik id '.$ownerDetail->companyId().': '.implode(', ', $summary).'.');
        if ($dry && $rows !== []) {
            $this->line('Jalankan tanpa --dry-run untuk menerapkannya.');
        }

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
