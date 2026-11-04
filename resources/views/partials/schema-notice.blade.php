{{-- Banner pengelola aplikasi (MasterCrudConfig.SCHEMA_NOTICE_PERMISSION): struktur tabel master lokal tertinggal dari
     paket / DB master, dengan perintah perbaikan siap salin. Dicek ulang tiap jam, hilang setelah perintahnya dijalankan. --}}
@php($masterSchemaStatus = app(\Bangsamu\Master\Services\MasterSchemaStatus::class))
@if ($masterSchemaStatus->visible())
    @php($masterSchemaIssues = $masterSchemaStatus->issues())
    @if ($masterSchemaStatus->hasIssues($masterSchemaIssues))
        @php($masterSchemaCommands = $masterSchemaStatus->commands($masterSchemaIssues))
        @php($masterSchemaDocker = $masterSchemaStatus->dockerPrefix())
        <div class="alert alert-warning mb-3" role="alert" id="master-schema-notice">
            <div class="fw-semibold">
                <i class="fas fa-database me-1"></i> Struktur tabel master aplikasi ini belum sesuai dengan paket / Master Data
            </div>
            <div class="small mb-2">
                Sinkron tetap berjalan, tetapi kolom yang belum ada tidak ikut tersalin dan aturan pemilik data (company)
                belum berlaku penuh. Jalankan perintah di bawah (hanya menambah kolom/index, data tidak diubah). Pemberitahuan
                ini hilang setelah perintah selesai dan hanya tampil untuk pengelola aplikasi.
            </div>

            @if ($masterSchemaDocker)
                <div class="small fw-semibold">Dari host (Docker), cek dulu dengan --dry-run lalu jalankan:</div>
                <pre class="mb-2 p-2 bg-body border rounded small" style="white-space: pre-wrap; user-select: all;"><code>@foreach ($masterSchemaCommands as $masterSchemaCommand){{ $masterSchemaDocker }} php artisan {{ $masterSchemaCommand }} --dry-run
{{ $masterSchemaDocker }} php artisan {{ $masterSchemaCommand }}
@endforeach</code></pre>
            @endif
            <div class="small fw-semibold">{{ $masterSchemaDocker ? 'Atau di dalam container' : 'Di folder aplikasi' }}:</div>
            <pre class="mb-2 p-2 bg-body border rounded small" style="white-space: pre-wrap; user-select: all;"><code>@foreach ($masterSchemaCommands as $masterSchemaCommand)php artisan {{ $masterSchemaCommand }}
@endforeach</code></pre>
            @if ($masterSchemaIssues['scope'] !== [])
                <div class="small">
                    Atau skrip setup dari folder project di host (container dideteksi otomatis, tiap langkah ditanya dulu):
                    <code>vendor/bin/master-scope-setup --skip-composer</code>
                </div>
            @endif

            <details class="mt-2 small">
                <summary>Rincian tabel</summary>
                <ul class="mb-0 mt-1">
                    @foreach ($masterSchemaIssues['scope'] + $masterSchemaIssues['scope_tables'] as $masterSchemaTable => $masterSchemaChanges)
                        <li><code>{{ $masterSchemaTable }}</code>: {{ implode('; ', $masterSchemaChanges) }}</li>
                    @endforeach
                    @foreach ($masterSchemaIssues['columns'] as $masterSchemaTable => $masterSchemaColumns)
                        <li><code>{{ $masterSchemaTable }}</code>: kolom di Master Data yang belum ada di sini:
                            {{ implode(', ', $masterSchemaColumns) }}</li>
                    @endforeach
                </ul>
            </details>
        </div>
    @endif
@endif
