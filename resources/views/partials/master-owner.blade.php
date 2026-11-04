{{--
    Pemilik data (app_code + company_id) di form master. Parameter: $table, $param (baris / null), $readonly.
    Tidak tampil untuk tabel tanpa kolom app_code (tabel global).
    is_admin yang memindahkan pemilik baris lama wajib konfirmasi (dialog + MasterScope::CONFIRM_OWNER_FIELD, server
    juga menolak tanpa konfirmasi).
--}}
@php
    $ownerScope = app(\Bangsamu\Master\Services\MasterScope::class);
    $ownerAccess = app(\Bangsamu\Master\Services\MasterAccess::class);
    $ownerTable = $table ?? '';
    $ownerHasCompany = $ownerScope->hasCompany($ownerTable);
    $ownerDelegation = $ownerScope->delegation();
    $ownerCurrent = $param ? $ownerScope->ownerOf($param) : null;
    $ownerNew = $param ? null : array_merge(['company_id' => 0], $ownerScope->ownerForNew($ownerTable));
    // tim master-data (hub tanpa delegasi) memilih pemilik data baru; is_admin boleh memindahkan pemilik baris lama
    $ownerSelectable = ! ($readonly ?? false) && (($param === null && $ownerScope->isHub() && $ownerDelegation === null) || ($param !== null && $ownerAccess->isAdmin()));
    // pilihan company 0 (semua company) untuk baris baru: delegasi all_co / admin aplikasi
    $ownerAllCompanyChoice = ! ($readonly ?? false) && $param === null && ! $ownerSelectable && $ownerHasCompany
        && ($ownerDelegation ? ! empty($ownerDelegation['all_co']) : ! $ownerScope->isHub() && $ownerAccess->canManageAllCompanies());
    $ownerApps = $ownerSelectable && \Illuminate\Support\Facades\Schema::hasTable('master_apps')
        ? \Illuminate\Support\Facades\DB::table('master_apps')->whereNull('deleted_at')->orderBy('app_code')->get(['app_code', 'name'])
        : collect();
    $ownerCompanies = ($ownerSelectable || $ownerAllCompanyChoice) && $ownerHasCompany && \Illuminate\Support\Facades\Schema::hasTable('master_company')
        ? \Illuminate\Support\Facades\DB::table('master_company')->whereNull('deleted_at')->orderBy('company_code')->get(['id', 'company_code', 'company_name'])
        : collect();
    $ownerValue = $ownerCurrent ?? $ownerNew;
    if (! $ownerHasCompany) {
        unset($ownerValue['company_id']);
    }
    // pemilik lama yang tidak (lagi) terdaftar tetap jadi pilihan terpilih; tanpa ini browser memilih opsi pertama dan
    // menyimpan form memindahkan pemilik tanpa disadari
    $ownerAppMissing = $ownerSelectable && $ownerCurrent !== null && filled($ownerCurrent['app_code'])
        && ! $ownerApps->contains(fn ($app) => strtoupper((string) $app->app_code) === strtoupper((string) $ownerCurrent['app_code']));
    $ownerCompanyMissing = $ownerSelectable && $ownerCurrent !== null && $ownerHasCompany && (int) $ownerCurrent['company_id'] !== 0
        && ! $ownerCompanies->contains(fn ($company) => (int) $company->id === (int) $ownerCurrent['company_id']);
    $ownerConfirm = $ownerSelectable && $param !== null;
    $ownerConfirmConfig = [
        'app_field' => \Bangsamu\Master\Services\MasterScope::OWNER_APP_FIELD,
        'company_field' => \Bangsamu\Master\Services\MasterScope::OWNER_COMPANY_FIELD,
        'confirm_field' => \Bangsamu\Master\Services\MasterScope::CONFIRM_OWNER_FIELD,
        'original_app' => strtoupper((string) ($ownerCurrent['app_code'] ?? '')),
        'original_company' => (string) (int) ($ownerCurrent['company_id'] ?? 0),
        'global_app' => strtoupper($ownerScope->globalAppCode()),
    ];
@endphp
@if ($ownerScope->isScoped($ownerTable))
    <div class="form-group mb-3" data-master-owner>
        <label class="form-label">Pemilik Data</label>
        @if ($ownerSelectable)
            <div class="row g-2">
                <div class="col-sm-6">
                    <select name="{{ \Bangsamu\Master\Services\MasterScope::OWNER_APP_FIELD }}" class="form-select @error(\Bangsamu\Master\Services\MasterScope::OWNER_APP_FIELD) is-invalid @enderror" aria-label="Aplikasi pemilik">
                        @if ($ownerAppMissing)
                            <option value="{{ $ownerCurrent['app_code'] }}" selected>{{ $ownerCurrent['app_code'] }} · (tidak terdaftar di master_apps)</option>
                        @endif
                        @foreach ($ownerApps as $ownerApp)
                            <option value="{{ $ownerApp->app_code }}" @selected(old(\Bangsamu\Master\Services\MasterScope::OWNER_APP_FIELD, $ownerValue['app_code'] ?? $ownerScope->globalAppCode()) === $ownerApp->app_code)>
                                {{ $ownerApp->app_code }} · {{ $ownerApp->name }}{{ $ownerApp->app_code === $ownerScope->globalAppCode() ? ' (bersama)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if ($ownerHasCompany)
                    <div class="col-sm-6">
                        <select name="{{ \Bangsamu\Master\Services\MasterScope::OWNER_COMPANY_FIELD }}" class="form-select @error(\Bangsamu\Master\Services\MasterScope::OWNER_COMPANY_FIELD) is-invalid @enderror" aria-label="Company pemilik">
                            <option value="0">Semua company</option>
                            @if ($ownerCompanyMissing)
                                <option value="{{ (int) $ownerCurrent['company_id'] }}" selected>Company {{ (int) $ownerCurrent['company_id'] }} · (tidak terdaftar di master_company)</option>
                            @endif
                            @foreach ($ownerCompanies as $ownerCompany)
                                <option value="{{ $ownerCompany->id }}" @selected((int) old(\Bangsamu\Master\Services\MasterScope::OWNER_COMPANY_FIELD, $ownerValue['company_id'] ?? 0) === (int) $ownerCompany->id)>
                                    {{ strtoupper(trim((string) $ownerCompany->company_code)) }} · {{ $ownerCompany->company_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
            @if ($param)
                <small class="text-muted">Memindahkan pemilik data hanya untuk admin dan wajib dikonfirmasi.</small>
            @endif
        @elseif ($ownerAllCompanyChoice)
            <select name="{{ \Bangsamu\Master\Services\MasterScope::OWNER_COMPANY_FIELD }}" class="form-select" aria-label="Company pemilik">
                <option value="{{ $ownerValue['company_id'] }}">{{ $ownerScope->ownerLabel($ownerValue) }}</option>
                <option value="0" @selected((string) old(\Bangsamu\Master\Services\MasterScope::OWNER_COMPANY_FIELD) === '0')>{{ $ownerScope->ownerLabel(['app_code' => $ownerValue['app_code'] ?? null, 'company_id' => 0]) }}</option>
            </select>
        @else
            <input type="text" class="form-control" value="{{ $ownerScope->ownerLabel($ownerValue ?? []) }}" readonly disabled>
        @endif
        @error(\Bangsamu\Master\Services\MasterScope::OWNER_APP_FIELD) <span class="text-danger d-block">{{ $message }}</span> @enderror
        @error(\Bangsamu\Master\Services\MasterScope::OWNER_COMPANY_FIELD) <span class="text-danger d-block">{{ $message }}</span> @enderror
        @if ($ownerConfirm)
            <input type="hidden" name="{{ \Bangsamu\Master\Services\MasterScope::CONFIRM_OWNER_FIELD }}" value="0">
        @endif
    </div>
    @if ($ownerConfirm)
        @include('master::components.confirm-dialog')
        <script>
            (function () {
                var cfg = @json($ownerConfirmConfig);
                var box = document.querySelector('[data-master-owner]');
                var form = box && box.closest('form');
                if (!form) return;
                var pick = function (name) { return box.querySelector('[name="' + name + '"]'); };
                var appSelect = pick(cfg.app_field);
                var companySelect = pick(cfg.company_field);
                var flag = pick(cfg.confirm_field);
                var textOf = function (select, value) {
                    var opt = select && Array.prototype.find.call(select.options, function (o) { return o.value.toUpperCase() === String(value).toUpperCase(); });
                    return opt ? opt.text.replace(/\s+/g, ' ').trim() : value;
                };

                // pemindahan yang sudah dikonfirmasi; dialog kunci unik di form yang sama mengirim ulang form
                var confirmedSig = null;

                form.addEventListener('submit', function (e) {
                    var app = appSelect ? (appSelect.value || '').toUpperCase() : cfg.original_app;
                    var company = companySelect ? String(parseInt(companySelect.value || '0', 10)) : cfg.original_company;
                    var changes = [];
                    if (app !== cfg.original_app) {
                        changes.push({ label: 'Aplikasi', before: textOf(appSelect, cfg.original_app), after: textOf(appSelect, app) });
                    }
                    if (companySelect && company !== cfg.original_company) {
                        changes.push({ label: 'Company', before: textOf(companySelect, cfg.original_company), after: textOf(companySelect, company) });
                    }
                    if (changes.length === 0) {
                        flag.value = '0';
                        return;
                    }
                    var sig = JSON.stringify(changes);
                    if (sig === confirmedSig) {
                        flag.value = '1';
                        return;
                    }

                    flag.value = '0';
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    var submitter = e.submitter || null;

                    var notes = [];
                    if (cfg.original_app === cfg.global_app && app !== cfg.global_app) {
                        notes.push('Data bersama (' + cfg.global_app + ') ini tidak lagi dipakai bersama: aplikasi lain yang menyaring data bersama tidak melihatnya lagi di daftar dan pilihan mereka.');
                    }
                    if (app === cfg.global_app && cfg.original_app !== cfg.global_app) {
                        notes.push('Data menjadi data bersama: hanya tim Master Data yang bisa mengubahnya, aplikasi pemilik lama tidak lagi.');
                    } else {
                        notes.push('Hak ubah lewat tombol "Edit di Master Data" pindah ke pemilik baru; pemilik lama tidak bisa mengubahnya lagi.');
                    }
                    notes.push('Perubahan ini tercatat di log data.');

                    window.masterConfirmDialog({
                        title: 'Pindahkan pemilik data?',
                        subtitle: 'Pemilik menentukan aplikasi dan company mana yang memakai serta boleh mengubah data ini.',
                        okText: 'Ya, pindahkan pemilik',
                        changes: changes,
                        notes: notes
                    }, function () {
                        flag.value = '1';
                        confirmedSig = sig;
                        if (typeof form.requestSubmit === 'function') {
                            submitter ? form.requestSubmit(submitter) : form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    });
                }, true);
            })();
        </script>
    @endif
@endif
