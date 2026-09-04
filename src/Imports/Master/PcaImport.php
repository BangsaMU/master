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

class PcaImport implements ToCollection, WithHeadingRow, WithEvents, WithChunkReading
{
    use HandlesBatchImportBroadcast;

    private $error = [];
    private $success = [];
    protected ?Collection $existingPcas = null;

    public function getImportTable(): string
    {
        return 'master_pca';
    }

    public function collection(Collection $rows)
    {
        if ($this->existingPcas === null) {
            $this->existingPcas = DB::table('master_pca')
                ->select('id', 'pca_code', 'pca_name', 'deleted_at')
                ->get()
                ->keyBy(fn ($pca) => strtoupper(trim((string) $pca->pca_code)) . '|' . strtoupper(trim((string) $pca->pca_name)));
        }

        foreach ($rows as $key => $row) {
            $row_index = $key + 1;
            if ($row->filter()->isNotEmpty()) {
                $pcaCode = trim((string) ($row['pca_code'] ?? ''));
                $pcaName = trim((string) ($row['pca_name'] ?? ''));

                if (empty($pcaCode)) {
                    $this->error[] = "Row {$row_index} PCA Code : field is required.";
                } elseif (empty($pcaName)) {
                    $this->error[] = "Row {$row_index} PCA Name : field is required.";
                } else {
                    $lookupKey = strtoupper($pcaCode) . '|' . strtoupper($pcaName);
                    $exists = $this->existingPcas->get($lookupKey);

                    if (! $exists) {
                        $data = new Pca();
                        $data->pca_code = strtoupper($pcaCode);
                        $data->pca_name = $pcaName;
                        $data->save();

                        $this->existingPcas->put($lookupKey, (object) [
                            'id' => $data->id,
                            'pca_code' => $data->pca_code,
                            'pca_name' => $data->pca_name,
                            'deleted_at' => null,
                        ]);

                        $this->success[] = "Row {$row_index} : {$pcaCode} has been imported successfully.";
                    } else {
                        $this->error[] = "Row {$row_index}: PCA already exists!";
                    }
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
