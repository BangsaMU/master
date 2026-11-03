@php
    $theme = config('app.themes');
    if ($theme == '_meridian') {
        $themeLayout = view()->exists('layouts.meridian') ? 'layouts.meridian' : 'master::layouts.meridian';
    } elseif ($theme == '_tabler') {
        $themeLayout = view()->exists('layouts.tabler') ? 'layouts.tabler' : 'master::layouts.tabler';
    } else {
        $themeLayout = 'adminlte::page';
    }

    $summary = $analysis['summary'];
    $fields = $definition->fields();
    $totalRows = count($analysis['rows']);
    // kelas mi-* didefinisikan di halaman ini: kelas warna Tabler (bg-green-lt dst.) tidak ada di AdminLTE/Bootstrap
    $statusMeta = [
        'new' => ['label' => 'Baru', 'class' => 'mi-badge-new'],
        'update' => ['label' => 'Update', 'class' => 'mi-badge-update'],
        'same' => ['label' => 'Tidak berubah', 'class' => 'mi-badge-same'],
        'locked' => ['label' => 'Milik app lain', 'class' => 'mi-badge-locked'],
        'error' => ['label' => 'Error', 'class' => 'mi-badge-error'],
    ];
    $allowUpdateOld = (bool) old('allow_update');
    // referensi yang belum ada di master: kuning = dibuat otomatis, merah = ditolak (saklar dashboard_settings)
    $refLabels = $definition->autoCreatableRefs();
    $autoCreateRefs = $analysis['auto_create_refs'] ?? false;
    $refClass = $autoCreateRefs ? 'mi-ref mi-ref-auto' : 'mi-ref mi-ref-reject';
    $settingUrl = Route::has('settings.dashboard.index') && (! function_exists('checkPermission') || checkPermission('is_admin'))
        ? route('settings.dashboard.index', \Bangsamu\Master\Services\Import\MasterImportSettings::GROUP)
        : null;
@endphp
@extends($themeLayout)

@section('title', 'Preview Import '.$definition->label())

@section('content_header')
    <h1>Preview Import {{ $definition->label() }}</h1>
    <div class="text-muted small">
        File <strong>{{ $payload['file_name'] }}</strong> &middot; {{ $totalRows }} baris &middot; diunggah {{ $payload['created_at'] }}
    </div>
@stop

@section('header')
    <div class="page-pretitle">Import Master Data</div>
    <h2 class="page-title">Preview Import {{ $definition->label() }}</h2>
    <div class="text-muted mt-1">
        File <strong>{{ $payload['file_name'] }}</strong> &middot; {{ $totalRows }} baris &middot; diunggah {{ $payload['created_at'] }}
    </div>
@endsection

@section('header-actions')
    <a href="{{ route($definition->indexRoute()) }}" class="btn btn-outline-secondary">Batal</a>
@endsection

@section('content')
    {{-- Warna sendiri (var Bootstrap 5.3 bila ada, fallback heksa) supaya terbaca di tema AdminLTE, Tabler, maupun mode gelap --}}
    <style>
        .mi-badge { display: inline-block; padding: .3em .6em; font-size: .75rem; font-weight: 600; line-height: 1.2; border: 1px solid transparent; border-radius: .375rem; white-space: nowrap; }
        .mi-badge-new { color: var(--bs-success-text-emphasis, #0a3622); background-color: var(--bs-success-bg-subtle, #d1e7dd); border-color: var(--bs-success-border-subtle, #a3cfbb); }
        .mi-badge-update { color: var(--bs-warning-text-emphasis, #664d03); background-color: var(--bs-warning-bg-subtle, #fff3cd); border-color: var(--bs-warning-border-subtle, #ffe69c); }
        .mi-badge-same { color: var(--bs-secondary-text-emphasis, #2b2f32); background-color: var(--bs-secondary-bg-subtle, #e2e3e5); border-color: var(--bs-secondary-border-subtle, #c4c8cb); }
        .mi-badge-locked { color: #432874; background-color: #e2d9f3; border-color: #c5b3e6; }
        .mi-badge-error { color: var(--bs-danger-text-emphasis, #58151c); background-color: var(--bs-danger-bg-subtle, #f8d7da); border-color: var(--bs-danger-border-subtle, #f1aeb5); }
        .mi-badge-key { color: var(--bs-primary-text-emphasis, #052c65); background-color: var(--bs-primary-bg-subtle, #cfe2ff); border-color: var(--bs-primary-border-subtle, #9ec5fe); }
        .mi-badge-field { color: var(--bs-info-text-emphasis, #055160); background-color: var(--bs-info-bg-subtle, #cff4fc); border-color: var(--bs-info-border-subtle, #9eeaf9); }
        .mi-text-update { color: #b58105; font-weight: 700; }
        .mi-ref { display: inline-block; padding: 0 .35em; border-radius: .25rem; font-weight: 600; }
        .mi-ref-auto { color: #946c00; background-color: #fff3cd; }
        .mi-ref-reject { color: #b02a37; background-color: #f8d7da; }
    </style>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    {{-- Ringkasan status --}}
    <div class="row row-cards mb-3">
        @foreach ($statusMeta as $status => $meta)
            <div class="col-6 col-md">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="mi-badge {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                            <span class="h2 mb-0">{{ number_format($summary[$status] ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row row-cards mb-3">
        {{-- Info kolom & data yang akan berubah --}}
        <div class="col-12 col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title">Ketentuan Import {{ $definition->label() }}</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Kunci unik</dt>
                        <dd class="col-sm-8"><span class="mi-badge mi-badge-key">{{ $definition->keyLabel() }}</span></dd>

                        <dt class="col-sm-4">Kolom yang bisa diupdate</dt>
                        <dd class="col-sm-8">
                            @foreach ($definition->updatableFields() as $field)
                                <span class="mi-badge mi-badge-field mb-1">{{ $fields[$field] ?? $field }}</span>
                            @endforeach
                        </dd>

                        <dt class="col-sm-4">Akan terupdate</dt>
                        <dd class="col-sm-8">
                            @forelse ($analysis['update_fields'] as $label => $count)
                                <span class="mi-badge mi-badge-update mb-1">{{ $label }}: {{ number_format($count, 0, ',', '.') }} baris</span>
                            @empty
                                <span class="text-muted">Tidak ada data existing yang berubah.</span>
                            @endforelse
                        </dd>

                        @if ($refLabels)
                            <dt class="col-sm-4">Referensi belum ada</dt>
                            <dd class="col-sm-8" id="importRefPolicy">
                                <span class="{{ $refClass }}">{{ $autoCreateRefs ? 'Dibuat otomatis' : 'Ditolak, baris dilewati' }}</span>
                                <div class="text-muted small mt-1">
                                    Berlaku untuk kode {{ implode(', ', $refLabels) }} yang belum ada di master. Saklar:
                                    @if ($settingUrl)
                                        <a href="{{ $settingUrl }}">Dashboard Settings &rarr; Master Import</a>
                                    @else
                                        Dashboard Settings &rarr; Master Import
                                    @endif
                                    (<code>{{ \Bangsamu\Master\Services\Import\MasterImportSettings::AUTO_CREATE_REFS }}</code>).
                                </div>
                            </dd>
                        @endif
                    </dl>
                    <div class="text-muted small mt-3">
                        Data yang sudah ada dicocokkan berdasarkan kunci unik. Kolom yang kosong di file tidak akan mengosongkan data lama.
                        Semua data baru maupun update dicatat di activity log.
                    </div>
                </div>
            </div>
        </div>

        {{-- Form proses import --}}
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-status-top bg-primary"></div>
                <div class="card-header">
                    <h3 class="card-title">Proses Import</h3>
                </div>
                <form action="{{ route('master.import.commit', [$definition->entity(), $token]) }}" method="POST" id="importCommitForm">
                    @csrf
                    <div class="card-body">
                        <p class="mb-2">
                            <strong>{{ $summary['new'] }}</strong> data baru akan dibuat.
                        </p>

                        @if ($summary['update'] > 0)
                            @if ($canUpdate)
                                <label class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="allow_update" value="1" id="allowUpdate" {{ $allowUpdateOld ? 'checked' : '' }}>
                                    <span class="form-check-label">
                                        Izinkan update <strong>{{ $summary['update'] }}</strong> data yang sudah ada
                                    </span>
                                </label>
                                <div id="updateReasonGroup" class="mb-2 {{ $allowUpdateOld ? '' : 'd-none' }}">
                                    <label class="form-label required" for="updateReason">Alasan update</label>
                                    <textarea name="update_reason" id="updateReason" rows="3" maxlength="500"
                                        class="form-control @error('update_reason') is-invalid @enderror"
                                        placeholder="Contoh: koreksi nama sesuai data SAP">{{ old('update_reason') }}</textarea>
                                    @error('update_reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-hint">Berlaku untuk semua baris update dan ditambahkan pada keterangan activity log.</div>
                                </div>
                                <p class="text-muted small mb-0" id="updateSkipHint">Tanpa centang, data yang sudah ada dilewati (tidak diubah).</p>
                            @else
                                <div class="alert alert-warning mb-0">
                                    {{ $summary['update'] }} data sudah ada dengan perubahan, tetapi Anda tidak memiliki izin update sehingga akan dilewati.
                                </div>
                            @endif
                        @endif

                        @if (($analysis['missing_rows'] ?? 0) > 0)
                            <p class="small mt-2 mb-0">
                                <span class="{{ $refClass }}">
                                    {{ $analysis['missing_rows'] }} baris memakai referensi yang belum ada di master{{ $autoCreateRefs ? ', referensinya akan dibuat otomatis.' : ' dan ditolak.' }}
                                </span>
                            </p>
                        @endif

                        @if ($summary['error'] > 0)
                            <p class="text-danger small mt-2 mb-0">{{ $summary['error'] }} baris error akan dilewati.</p>
                        @endif
                    </div>
                    <div class="card-body border-top d-none" id="importProgress">
                        <div class="d-flex justify-content-between small mb-1">
                            <span id="importProgressText">Menyiapkan...</span>
                            <span id="importProgressPercent">0%</span>
                        </div>
                        <div class="progress progress-sm">
                            <div class="progress-bar bg-primary" id="importProgressBar" style="width: 0%" role="progressbar"></div>
                        </div>
                        <div class="small text-muted mt-2" id="importProgressStats"></div>
                        <div class="alert alert-danger mt-2 mb-0 d-none" id="importProgressError"></div>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <a href="{{ route($definition->indexRoute()) }}" class="btn btn-link">Batal</a>
                        <button type="submit" class="btn btn-primary" {{ $summary['new'] === 0 && ($summary['update'] === 0 || ! $canUpdate) ? 'disabled' : '' }}>
                            Proses Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Detail baris --}}
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <h3 class="card-title mb-0">Detail Baris</h3>
            <div class="card-actions ms-auto">
                <div class="btn-group btn-group-sm" role="group" id="statusFilter">
                    <button type="button" class="btn btn-outline-secondary active" data-status="all">Semua ({{ $totalRows }})</button>
                    @foreach ($statusMeta as $status => $meta)
                        @if (($summary[$status] ?? 0) > 0)
                            <button type="button" class="btn btn-outline-secondary" data-status="{{ $status }}">{{ $meta['label'] }} ({{ $summary[$status] }})</button>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
        @if ($totalRows > $limit)
            <div class="card-body py-2">
                <span class="text-muted small">Ditampilkan {{ $limit }} dari {{ $totalRows }} baris pertama. Ringkasan dan proses import tetap mencakup semua baris.</span>
            </div>
        @endif
        <div class="table-responsive">
            <table class="table table-vcenter card-table table-sm" id="importPreviewTable">
                <thead>
                    <tr>
                        <th class="w-1">Baris</th>
                        <th>Status</th>
                        @foreach ($fields as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $item)
                        <tr data-status="{{ $item['status'] }}">
                            <td class="text-muted">{{ $item['row'] }}</td>
                            <td><span class="mi-badge {{ $statusMeta[$item['status']]['class'] }}">{{ $statusMeta[$item['status']]['label'] }}</span></td>
                            @foreach ($fields as $field => $label)
                                @php($missingRef = isset($item['missing'][$field]))
                                <td>
                                    @if (isset($item['changes'][$field]))
                                        <div class="text-muted text-decoration-line-through small">{{ $item['changes'][$field]['old'] !== '' ? $item['changes'][$field]['old'] : '(kosong)' }}</div>
                                        <div class="mi-text-update"><span @class([$refClass.' text-nowrap' => $missingRef])>{{ $item['changes'][$field]['new'] }}</span></div>
                                    @elseif ($missingRef)
                                        <span class="{{ $refClass }} text-nowrap">{{ $item['data'][$field] }}</span>
                                    @else
                                        {{ $item['data'][$field] ?? '' }}
                                    @endif
                                </td>
                            @endforeach
                            <td class="small">
                                @if ($item['missing'] ?? [])
                                    @foreach ($item['missing'] as $ref)
                                        <div class="mb-1"><span class="{{ $refClass }}">{{ $ref }} belum ada{{ $autoCreateRefs ? ', akan dibuat otomatis.' : ' di master.' }}</span></div>
                                    @endforeach
                                    @if ($item['status'] === 'error')
                                        <div class="text-danger">Ditolak: buat otomatis referensi tidak diizinkan, baris dilewati.</div>
                                    @endif
                                @elseif ($item['message'])
                                    <div class="{{ $item['status'] === 'error' ? 'text-danger' : 'text-muted' }}">{{ $item['message'] }}</div>
                                @endif
                                @if ($item['changes'])
                                    <div>Field terupdate: {{ implode(', ', array_column($item['changes'], 'label')) }}</div>
                                @endif
                                @foreach ($item['notes'] as $note)
                                    <div class="text-muted">{{ $note }}</div>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('js')
    <script>
        (function () {
            var allow = document.getElementById('allowUpdate');
            var group = document.getElementById('updateReasonGroup');
            var reason = document.getElementById('updateReason');
            var hint = document.getElementById('updateSkipHint');
            if (allow && group && reason) {
                var sync = function () {
                    group.classList.toggle('d-none', !allow.checked);
                    if (hint) hint.classList.toggle('d-none', allow.checked);
                    reason.required = allow.checked;
                };
                allow.addEventListener('change', sync);
                sync();
            }

            // Commit per batch lewat AJAX supaya file besar tidak kena timeout web server.
            var form = document.getElementById('importCommitForm');
            if (form && window.fetch && window.FormData) {
                var btn = form.querySelector('button[type=submit]');
                var box = document.getElementById('importProgress');
                var bar = document.getElementById('importProgressBar');
                var text = document.getElementById('importProgressText');
                var percent = document.getElementById('importProgressPercent');
                var stats = document.getElementById('importProgressStats');
                var errorBox = document.getElementById('importProgressError');
                var nf = new Intl.NumberFormat('id-ID');
                var nextOffset = 0;
                var running = false;

                var setLocked = function (locked) {
                    form.querySelectorAll('input, textarea').forEach(function (el) {
                        if (el.name !== '_token') el.readOnly = locked;
                    });
                    if (allow) allow.disabled = locked;
                };

                var render = function (res) {
                    var pct = res.total > 0 ? Math.floor(res.next_offset * 100 / res.total) : 100;
                    bar.style.width = pct + '%';
                    percent.textContent = pct + '%';
                    text.textContent = (res.done ? 'Selesai: ' : 'Memproses ') + nf.format(res.next_offset) + ' / ' + nf.format(res.total) + ' baris';
                    stats.textContent = nf.format(res.created) + ' dibuat, ' + nf.format(res.updated) + ' diupdate, '
                        + nf.format(res.skipped) + ' dilewati, ' + nf.format(res.failed) + ' gagal';
                };

                var fail = function (message) {
                    running = false;
                    errorBox.textContent = message + ' Klik "Lanjutkan Import" untuk melanjutkan dari baris terakhir.';
                    errorBox.classList.remove('d-none');
                    btn.disabled = false;
                    btn.textContent = 'Lanjutkan Import';
                };

                var step = function () {
                    var body = new FormData(form);
                    if (allow && allow.checked) body.set('allow_update', '1');
                    body.set('offset', nextOffset);

                    fetch(form.action, {
                        method: 'POST',
                        body: body,
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(function (response) {
                        return response.json().catch(function () { return {}; }).then(function (json) {
                            return { ok: response.ok, status: response.status, json: json };
                        });
                    }).then(function (r) {
                        if (r.status === 422) {
                            running = false;
                            setLocked(false);
                            box.classList.add('d-none');
                            btn.disabled = false;
                            btn.textContent = 'Proses Import';
                            var errors = r.json.errors || {};
                            var first = Object.keys(errors).map(function (k) { return errors[k][0]; })[0];
                            if (reason && errors.update_reason) {
                                reason.classList.add('is-invalid');
                                reason.focus();
                            }
                            alert(first || r.json.message || 'Data tidak valid.');
                            return;
                        }
                        if (!r.ok) {
                            if (r.json.redirect) {
                                running = false;
                                alert(r.json.message || 'Preview tidak ditemukan.');
                                window.location.href = r.json.redirect;
                                return;
                            }
                            fail(r.json.message || ('Server error (' + r.status + ').'));
                            return;
                        }
                        nextOffset = r.json.next_offset;
                        render(r.json);
                        if (r.json.done) {
                            running = false;
                            text.textContent += ', mengalihkan...';
                            window.location.href = r.json.redirect;
                        } else {
                            step();
                        }
                    }).catch(function () {
                        fail('Koneksi terputus.');
                    });
                };

                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (running) return;
                    if (allow && allow.checked && reason && reason.value.trim().length < 5) {
                        reason.classList.add('is-invalid');
                        reason.focus();
                        alert('Alasan update wajib diisi (minimal 5 karakter).');
                        return;
                    }
                    running = true;
                    setLocked(true);
                    errorBox.classList.add('d-none');
                    box.classList.remove('d-none');
                    btn.disabled = true;
                    btn.textContent = 'Memproses...';
                    step();
                });

                window.addEventListener('beforeunload', function (e) {
                    if (running) { e.preventDefault(); e.returnValue = ''; }
                });
            }

            var filter = document.getElementById('statusFilter');
            if (filter) {
                filter.addEventListener('click', function (e) {
                    var btn = e.target.closest('button[data-status]');
                    if (!btn) return;
                    filter.querySelectorAll('button').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    var status = btn.getAttribute('data-status');
                    document.querySelectorAll('#importPreviewTable tbody tr').forEach(function (tr) {
                        tr.classList.toggle('d-none', status !== 'all' && tr.getAttribute('data-status') !== status);
                    });
                });
            }
        })();
    </script>
@endpush
