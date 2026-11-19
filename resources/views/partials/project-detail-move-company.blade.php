{{--
    Company baris Project Detail terhapus / tidak ada di master (Edit Project Detail, semua tema). Data dari
    ProjectDetailController::moveCompanyData() ($data['page']['move_company'], null = tidak tampil). Admin Master Data di
    hub: form Pindah company (POST master.project-detail.move-company -> ProjectOwnerDetail::moveCompany), pengecualian
    terbatas keputusan N2 (company aktif tetap tidak bisa diganti). User lain: peringatan saja.
--}}
@php
    $mc = $data['page']['move_company'] ?? null;
    $mcField = \Bangsamu\Master\Services\ProjectOwnerDetail::FIELD_MOVE_COMPANY;
    $mcConfirm = \Bangsamu\Master\Services\ProjectOwnerDetail::FIELD_MOVE_CONFIRM;
@endphp
@if ($mc)
    <div class="row">
        <div class="col-12 col-sm-8">
            <div class="card card-outline card-danger mt-3" id="project-detail-move-company">
                <div class="card-header font-weight-bold fw-bold">Pindah company</div>
                <div class="card-body">
                    <div class="alert alert-danger py-2 small mb-3" data-move-company-problem="{{ $mc['problem'] }}">
                        <strong>{{ $mc['label'] }}.</strong> Baris ini tidak terhitung di company mana pun, dan aplikasi
                        pemakai (mis. SPB) tidak bisa mencocokkannya dengan company yang aktif.
                    </div>

                    @if (! $mc['can_move'])
                        <p class="small mb-0" data-move-company-ask-admin>
                            Minta admin Master Data memindahkan company baris ini (Edit Project Detail → Pindah company).
                        </p>
                    @elseif ($mc['denial'])
                        <p class="small mb-0 text-danger" data-move-company-denial>{{ $mc['denial'] }}</p>
                    @elseif ($mc['choices']->isEmpty())
                        <p class="small mb-0" data-move-company-empty>
                            Tidak ada company tujuan: company aktif lain sudah punya baris di project ini, atau company itu
                            pemilik project. Bila baris ini dobel, hapus saja baris ini.
                        </p>
                    @else
                        <p class="small mb-2">
                            Hanya company yang diganti: id baris, project, Project Code Client, dan Project Name Client tetap.
                            Aplikasi yang memakai baris ini (mis. dokumen SPB) ikut menganggapnya milik company tujuan; cek
                            kesiapan SPB akan menandai dokumen lama yang dibuat untuk company sebelumnya.
                        </p>
                        <p class="small text-muted mb-3">
                            Tidak ditawarkan: company yang sudah punya baris di project ini (termasuk baris terhapus) dan
                            company pemilik project (baris pemilik lewat Buat Detail / Pindah pemilik di Project).
                        </p>
                        <form action="{{ $mc['url'] }}" method="POST" autocomplete="off">
                            @csrf
                            <div class="form-group mb-3">
                                <label for="{{ $mcField }}">Company tujuan</label>
                                <select class="form-control form-select @error($mcField) is-invalid @enderror" name="{{ $mcField }}" id="{{ $mcField }}" required>
                                    <option value="">-- pilih company --</option>
                                    @foreach ($mc['choices'] as $mcChoice)
                                        <option value="{{ $mcChoice->id }}" @selected((string) old($mcField) === (string) $mcChoice->id)>
                                            {{ $mcChoice->company_code }}{{ filled($mcChoice->company_name) ? ' - '.$mcChoice->company_name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input @error($mcConfirm) is-invalid @enderror" type="checkbox" name="{{ $mcConfirm }}" value="1" id="{{ $mcConfirm }}" required>
                                <label class="form-check-label" for="{{ $mcConfirm }}">
                                    Saya mengerti: baris ini dan semua yang merujuknya pindah ke company tujuan.
                                </label>
                                @error($mcConfirm)
                                    <span class="text-danger d-block">{{ $message }}</span>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-warning">Pindah company</button>
                        </form>
                    @endif

                    {{-- penolakan dari server (ProjectOwnerDetail::moveCompany) tampil di semua keadaan card --}}
                    @error($mcField)
                        <div class="text-danger small mt-2" data-move-company-error>{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
@endif
