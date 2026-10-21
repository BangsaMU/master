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
    $statusMeta = [
        'new' => ['label' => 'Baru', 'class' => 'bg-green-lt'],
        'update' => ['label' => 'Update', 'class' => 'bg-yellow-lt'],
        'same' => ['label' => 'Tidak berubah', 'class' => 'bg-secondary-lt'],
        'locked' => ['label' => 'Milik app lain', 'class' => 'bg-purple-lt'],
        'error' => ['label' => 'Error', 'class' => 'bg-red-lt'],
    ];
    $allowUpdateOld = (bool) old('allow_update');
@endphp
@extends($themeLayout)

@section('title', 'Preview Import '.$definition->label())

@section('content_header')
    <h1>Preview Import {{ $definition->label() }}</h1>
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
                            <span class="badge {{ $meta['class'] }}">{{ $meta['label'] }}</span>
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
                        <dd class="col-sm-8"><span class="badge badge-outline text-blue">{{ $definition->keyLabel() }}</span></dd>

                        <dt class="col-sm-4">Kolom yang bisa diupdate</dt>
                        <dd class="col-sm-8">
                            @foreach ($definition->updatableFields() as $field)
                                <span class="badge bg-azure-lt mb-1">{{ $fields[$field] ?? $field }}</span>
                            @endforeach
                        </dd>

                        <dt class="col-sm-4">Akan terupdate</dt>
                        <dd class="col-sm-8">
                            @forelse ($analysis['update_fields'] as $label => $count)
                                <span class="badge bg-yellow-lt mb-1">{{ $label }}: {{ number_format($count, 0, ',', '.') }} baris</span>
                            @empty
                                <span class="text-muted">Tidak ada data existing yang berubah.</span>
                            @endforelse
                        </dd>
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
        <div class="card-header">
            <h3 class="card-title">Detail Baris</h3>
            <div class="card-actions">
                <div class="btn-group btn-group-sm" role="group" id="statusFilter">
                    <button type="button" class="btn active" data-status="all">Semua ({{ $totalRows }})</button>
                    @foreach ($statusMeta as $status => $meta)
                        @if (($summary[$status] ?? 0) > 0)
                            <button type="button" class="btn" data-status="{{ $status }}">{{ $meta['label'] }} ({{ $summary[$status] }})</button>
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
                            <td><span class="badge {{ $statusMeta[$item['status']]['class'] }}">{{ $statusMeta[$item['status']]['label'] }}</span></td>
                            @foreach ($fields as $field => $label)
                                <td>
                                    @if (isset($item['changes'][$field]))
                                        <div class="text-muted text-decoration-line-through small">{{ $item['changes'][$field]['old'] !== '' ? $item['changes'][$field]['old'] : '(kosong)' }}</div>
                                        <div class="text-yellow fw-bold">{{ $item['changes'][$field]['new'] }}</div>
                                    @else
                                        {{ $item['data'][$field] ?? '' }}
                                    @endif
                                </td>
                            @endforeach
                            <td class="small">
                                @if ($item['message'])
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
