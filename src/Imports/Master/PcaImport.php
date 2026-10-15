<?php

declare(strict_types=1);

namespace Bangsamu\Master\Imports\Master;

use Bangsamu\Master\Models\Pca;
use Bangsamu\Master\Traits\HandlesBatchImportBroadcast;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

class PcaImport implements ToCollection, WithChunkReading, WithEvents, WithHeadingRow
{
    use HandlesBatchImportBroadcast;

    private $error = [];

    private $success = [];

    /** Pre-loaded existing PCAs keyed by uppercase pca_code */
    protected ?Collection $existingPcas = null;

    public function getImportTable(): string
    {
        return 'master_pca';
    }

    public function collection(Collection $rows)
    {
        // Pre-load existing PCAs once per chunk (zero N+1 queries)
        if ($this->existingPcas === null) {
            $this->existingPcas = DB::table('master_pca')
                ->whereNull('deleted_at')
                ->select('id', 'pca_code', 'pca_name', 'deleted_at')
                ->get()
                ->keyBy(fn ($pca) => strtoupper(trim((string) $pca->pca_code)));
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            $rowCol = $row instanceof Collection ? $row : collect($row);

            if ($rowCol->filter()->isNotEmpty()) {
                $pcaCode = strtoupper(trim((string) ($row['pca_code'] ?? $row['pcacode'] ?? $row['PCA Code'] ?? '')));
                $pcaName = trim((string) ($row['pca_name'] ?? $row['pcaname'] ?? $row['PCA Name'] ?? ''));

                if (empty($pcaCode)) {
                    $this->error[] = "Row {$row_index}: PCA Code is required.";
                    continue;
                }

                if (strlen($pcaCode) > 20) {
                    $this->error[] = "Row {$row_index} [{$pcaCode}]: PCA Code exceeds maximum length of 20 characters.";
                    continue;
                }

                if (empty($pcaName)) {
                    $this->error[] = "Row {$row_index} [{$pcaCode}]: PCA Name is required.";
                    continue;
                }

                if (strlen($pcaName) > 150) {
                    $this->error[] = "Row {$row_index} [{$pcaCode}]: PCA Name exceeds maximum length of 150 characters.";
                    continue;
                }

                $exists = $this->existingPcas->get($pcaCode);

                if ($exists) {
                    $this->error[] = "Row {$row_index} [{$pcaCode}]: PCA Code already exists, skipped.";
                    continue;
                }

                try {
                    $data = new Pca;
                    $data->pca_code = $pcaCode;
                    $data->pca_name = $pcaName;
                    $data->app_code = config('SsoConfig.main.APP_CODE') ?? 'APP03';
                    $data->save();

                    $this->existingPcas->put($pcaCode, (object) [
                        'id'         => $data->id,
                        'pca_code'   => $data->pca_code,
                        'pca_name'   => $data->pca_name,
                        'deleted_at' => null,
                    ]);

                    $this->success[] = "Row {$row_index} [{$pcaCode}]: imported successfully.";
                } catch (Throwable $e) {
                    $this->error[] = "Row {$row_index} [{$pcaCode}]: Failed to import — {$e->getMessage()}";
                }
            }
        }
    }

    public function getError(): array
    {
        return $this->error;
    }

    public function getSuccess(): array
    {
        return $this->success;
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
