<?php

declare(strict_types=1);

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use Bangsamu\Master\Requests\MasterImportCommitRequest;
use Bangsamu\Master\Requests\MasterImportPreviewRequest;
use Bangsamu\Master\Services\Import\MasterImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Import master data dengan preview: upload -> preview -> commit (opsional izinkan update + alasan).
 */
class MasterImportController extends Controller
{
    /** Batas baris yang dirender di tabel preview (ringkasan tetap menghitung semua baris). */
    public const PREVIEW_LIMIT = 2000;

    public function __construct(protected MasterImportService $service) {}

    public function preview(MasterImportPreviewRequest $request, string $entity): RedirectResponse
    {
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $definition = $this->service->definition($entity);
        $file = $request->file('file');
        $rows = $this->service->readFile($definition, $file);

        if ($rows === []) {
            return redirect()->route($definition->indexRoute())->with('error_message', ['File tidak berisi data untuk diimport.']);
        }

        $token = $this->service->store($definition, $rows, $file->getClientOriginalName(), $request->user()->getAuthIdentifier());

        return redirect()->route('master.import.show', [$entity, $token]);
    }

    public function show(string $entity, string $token): View|RedirectResponse
    {
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $definition = $this->service->definition($entity);
        abort_unless($this->service->canCreate($definition), 403);

        $payload = $this->service->load($token, $definition, auth()->id());
        if ($payload === null) {
            return redirect()->route($definition->indexRoute())->with('error_message', ['Preview import tidak ditemukan atau sudah kedaluwarsa, silakan upload ulang.']);
        }

        $analysis = $this->service->analyze($definition, $payload['rows']);

        return view('master::layouts.import.preview', [
            'definition' => $definition,
            'payload' => $payload,
            'token' => $token,
            'analysis' => $analysis,
            'rows' => array_slice($analysis['rows'], 0, self::PREVIEW_LIMIT),
            'limit' => self::PREVIEW_LIMIT,
            'canUpdate' => $this->service->canUpdate($definition),
            'data' => ['page' => ['title' => 'Preview Import '.$definition->label(), 'sheet_name' => 'Preview Import '.$definition->label()]],
        ]);
    }

    public function commit(MasterImportCommitRequest $request, string $entity, string $token): RedirectResponse
    {
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $definition = $this->service->definition($entity);
        $payload = $this->service->load($token, $definition, $request->user()->getAuthIdentifier());
        if ($payload === null) {
            return redirect()->route($definition->indexRoute())->with('error_message', ['Preview import tidak ditemukan atau sudah kedaluwarsa, silakan upload ulang.']);
        }

        $result = $this->service->commit(
            $definition,
            $token,
            $payload,
            $request->boolean('allow_update'),
            $request->input('update_reason'),
            $request->user(),
        );

        $summary = "Import {$definition->label()} selesai: {$result['created']} dibuat, {$result['updated']} diupdate, {$result['skipped']} dilewati, {$result['failed']} gagal.";

        // view index menampilkan pesan tanpa escape, isi file harus di-escape di sini
        return redirect()->route($definition->indexRoute())
            ->with('success_message', array_map('e', array_merge([$summary], $result['success'])))
            ->with('error_message', array_map('e', $result['errors']));
    }
}
