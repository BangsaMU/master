{{--
    Card ringkasan di atas daftar Project (hub, ProjectController::index -> $data['page']['owner_summary'], dihitung
    ProjectOwnerDetail::summary): jumlah company aktif, project master, dan Project Code Client (detail) per company,
    jumlah project per company pemilik, serta cek 1:1 project master dengan baris company pemiliknya (pemilik bawaan
    Meindo, atau owner_company_id untuk project non-Meindo). Termasuk fungsi + form tombol "Buat Detail".
--}}
@php
    /** @var array $s */
    $s = $data['page']['owner_summary'];
    $owner = $s['owner'];
    $ownerLabel = trim(($owner['code'] ?? '') . ($owner['name'] ? ' - ' . $owner['name'] : '')) ?: 'company id ' . $owner['id'];
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
@endphp
<div class="row mb-3" id="project-owner-summary">
    <div class="col-12 col-md-4 mb-2">
        <div class="card h-100 mb-0">
            <div class="card-body py-2">
                <div class="text-muted small">Company aktif</div>
                <div class="fs-3 fw-bold" data-summary="company_total">{{ $nf($s['company_total']) }}</div>
                @if ($s['company_deleted'] > 0)
                    <div class="text-muted small">{{ $nf($s['company_deleted']) }} company terhapus tidak dihitung</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 mb-2">
        <div class="card h-100 mb-0">
            <div class="card-body py-2">
                <div class="text-muted small">Project master (aktif)</div>
                <div class="fs-3 fw-bold" data-summary="project_total">{{ $nf($s['project_total']) }}</div>
                @if ($s['project_deleted'] > 0)
                    <div class="text-muted small">{{ $nf($s['project_deleted']) }} project terhapus tidak dihitung</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 mb-2">
        <div class="card h-100 mb-0">
            <div class="card-body py-2">
                <div class="text-muted small">Project Code Client (detail aktif)</div>
                <div class="fs-3 fw-bold" data-summary="detail_total">{{ $nf($s['detail_total']) }}</div>
                <div class="text-muted small">semua company, termasuk yang company/project-nya sudah terhapus</div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card mb-0">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                    <div class="fw-bold">Project Code Client per company</div>
                    @if ($s['consistent'])
                        <span class="badge bg-success badge-success text-bg-success" data-summary="status">
                            Sesuai: {{ $nf($s['project_total']) }} project, masing-masing punya satu baris company pemiliknya
                        </span>
                    @else
                        <span class="badge bg-warning badge-warning text-bg-warning" data-summary="status">Belum sesuai</span>
                    @endif
                </div>

                <div class="table-responsive" style="max-height: 14rem; overflow-y: auto;">
                    <table class="table table-sm table-bordered mb-2" style="width:100%">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th class="text-end text-right" style="width: 10rem;">Project milik</th>
                                <th class="text-end text-right" style="width: 12rem;">Project Code Client</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($s['companies'] as $company)
                                <tr @class(['table-active' => $company['owner']])>
                                    <td>
                                        <strong>{{ $company['code'] }}</strong> {{ $company['name'] }}
                                        @if ($company['owner'])
                                            <span class="badge bg-primary badge-primary text-bg-primary ms-1 ml-1">pemilik utama</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-right" data-summary-owned="{{ $company['code'] }}">{{ $nf($company['owned'] ?? 0) }}</td>
                                    <td class="text-end text-right">{{ $nf($company['detail']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">Belum ada company aktif.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <ul class="small mb-0 ps-3 pl-3" data-summary="notes">
                    @if (! $owner['active'])
                        <li class="text-danger">Company pemilik bawaan (id {{ $owner['id'] }}) tidak ditemukan atau sudah dihapus, jadi detail pemilik project miliknya tidak bisa dibuat.</li>
                    @endif
                    @if ($s['missing'] > 0)
                        <li class="text-danger">
                            {{ $nf($s['missing']) }} project aktif belum punya Project Detail {{ ($s['other_owner'] ?? 0) > 0 ? 'company pemiliknya' : $ownerLabel }}
                            (contoh kode: {{ implode(', ', $s['missing_sample']) }}{{ $s['missing'] > count($s['missing_sample']) ? ', ...' : '' }}).
                            Klik <em>Buat Detail</em> di kolom Action baris itu.
                        </li>
                    @endif
                    @if ($s['duplicate'] > 0)
                        <li class="text-danger">{{ $nf($s['duplicate']) }} project punya lebih dari satu detail aktif company pemiliknya (seharusnya satu banding satu). Rapikan lewat menu Project Detail.</li>
                    @endif
                    @if ($s['orphan_company'] > 0)
                        <li class="text-muted">{{ $nf($s['orphan_company']) }} detail aktif memakai company yang sudah terhapus: tidak terhitung di company mana pun di tabel atas.</li>
                    @endif
                    @if ($s['orphan_project'] > 0)
                        <li class="text-muted">{{ $nf($s['orphan_project']) }} detail aktif milik project yang sudah terhapus: tidak ikut dihitung per company.</li>
                    @endif
                    @if (($s['other_owner'] ?? 0) > 0)
                        <li class="text-muted" data-summary="other_owner">{{ $nf($s['other_owner']) }} project aktif milik company selain {{ $owner['code'] }} (project non-{{ $owner['code'] }}): tidak punya baris {{ $owner['code'] }}, dan itu bukan kekurangan.</li>
                    @endif
                    @if ($s['consistent'] && $s['orphan_company'] === 0 && $s['orphan_project'] === 0)
                        <li class="text-muted">Setiap project aktif punya tepat satu detail company pemiliknya; tidak ada detail yatim.</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

@push('js')
    <form action="" id="owner-detail-form" method="POST" autocomplete="off">
        @csrf
    </form>
    <script>
        // tombol "Buat Detail" di kolom Action daftar Project: konfirmasi lalu POST (aksi menulis data, bukan link biasa)
        function notificationBeforeOwnerDetail(event, el) {
            event.preventDefault();
            var submit = function() {
                $('#owner-detail-form').attr('action', $(el).attr('href'));
                $('#owner-detail-form').submit();
            };
            var text = 'Project Detail untuk company pemilik project ini dibuat otomatis (kode dan nama client = kode dan nama project).';
            if (typeof Swal === 'undefined') {
                if (confirm('Buat Project Detail? ' + text)) {
                    submit();
                }
                return;
            }
            Swal.fire({
                title: 'Buat Project Detail?',
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, buat',
                cancelButtonText: 'Batal'
            }).then(function(result) {
                if (result.value == true) {
                    submit();
                }
            });
        }
    </script>
@endpush
