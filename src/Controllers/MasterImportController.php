<?php

declare(strict_types=1);

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use Bangsamu\Master\Requests\MasterImportCommitRequest;
use Bangsamu\Master\Requests\MasterImportPreviewRequest;
use Bangsamu\Master\Services\Import\MasterImportService;
use Illuminate\Http\JsonResponse;
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

        $total = $this->service->countRows($file);
        if ($total !== null && $total - 1 > $this->service->maxRows()) {
            $max = number_format($this->service->maxRows(), 0, ',', '.');

            return redirect()->route($definition->indexRoute())->with('error_message', [
                'File berisi '.number_format($total - 1, 0, ',', '.')." baris, melebihi batas {$max} baris per import. Silakan pecah file menjadi beberapa bagian.",
            ]);
        }

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

    /**
     * AJAX (expectsJson): proses satu batch mulai `offset`, balas progress JSON; batch terakhir menyimpan flash message.
     * Form biasa: proses semua baris lalu redirect (fallback tanpa JavaScript).
     */
    public function commit(MasterImportCommitRequest $request, string $entity, string $token): RedirectResponse|JsonResponse
    {
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $definition = $this->service->definition($entity);
        $indexUrl = route($definition->indexRoute());
        $payload = $this->service->load($token, $definition, $request->user()->getAuthIdentifier());

        if ($payload === null) {
            $message = 'Preview import tidak ditemukan atau sudah kedaluwarsa, silakan upload ulang.';

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'redirect' => $indexUrl], 410)
                : redirect($indexUrl)->with('error_message', [$message]);
        }

        $args = [$request->boolean('allow_update'), $request->input('update_reason'), $request->user()];

        if (! $request->expectsJson()) {
            $progress = $this->service->commit($definition, $token, $payload, ...$args);
            $this->flashResult($definition->label(), $progress);

            return redirect($indexUrl);
        }

        $progress = $this->service->commitChunk($definition, $token, $payload, (int) $request->input('offset', 0), $this->service->chunkSize(), ...$args);
        if ($progress['done']) {
            $this->flashResult($definition->label(), $progress);
        }

        return response()->json([
            'done' => $progress['done'],
            'next_offset' => $progress['next_offset'],
            'total' => $progress['total'],
            'created' => $progress['created'],
            'updated' => $progress['updated'],
            'skipped' => $progress['skipped'],
            'failed' => $progress['failed'],
            'redirect' => $progress['done'] ? $indexUrl : null,
        ]);
    }

    /**
     * Ringkasan hasil import ke flash session (view index menampilkan pesan tanpa escape, jadi di-escape di sini).
     */
    protected function flashResult(string $label, array $progress): void
    {
        $success = array_merge(
            ["Import {$label} selesai: {$progress['created']} dibuat, {$progress['updated']} diupdate, {$progress['skipped']} dilewati, {$progress['failed']} gagal."],
            $progress['success'],
            $progress['more_success'] > 0 ? ["... dan {$progress['more_success']} baris berhasil lainnya."] : [],
        );
        $errors = array_merge(
            $progress['errors'],
            $progress['more_errors'] > 0 ? ["... dan {$progress['more_errors']} baris gagal lainnya."] : [],
        );

        session()->flash('success_message', array_map('e', $success));
        session()->flash('error_message', array_map('e', $errors));
    }
}
