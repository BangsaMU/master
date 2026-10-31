{{--
    Pemilik data (app_code + company_id) di form master. Parameter: $table, $param (baris / null), $readonly.
    Tidak tampil untuk tabel tanpa kolom app_code (tabel global).
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
@endphp
@if ($ownerScope->isScoped($ownerTable))
    <div class="form-group mb-3">
        <label class="form-label">Pemilik Data</label>
        @if ($ownerSelectable)
            <div class="row g-2">
                <div class="col-sm-6">
                    <select name="{{ \Bangsamu\Master\Services\MasterScope::OWNER_APP_FIELD }}" class="form-select" aria-label="Aplikasi pemilik">
                        @foreach ($ownerApps as $ownerApp)
                            <option value="{{ $ownerApp->app_code }}" @selected(old(\Bangsamu\Master\Services\MasterScope::OWNER_APP_FIELD, $ownerValue['app_code'] ?? $ownerScope->globalAppCode()) === $ownerApp->app_code)>
                                {{ $ownerApp->app_code }} · {{ $ownerApp->name }}{{ $ownerApp->app_code === $ownerScope->globalAppCode() ? ' (bersama)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if ($ownerHasCompany)
                    <div class="col-sm-6">
                        <select name="{{ \Bangsamu\Master\Services\MasterScope::OWNER_COMPANY_FIELD }}" class="form-select" aria-label="Company pemilik">
                            <option value="0">Semua company</option>
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
                <small class="text-muted">Memindahkan pemilik data hanya untuk admin.</small>
            @endif
        @elseif ($ownerAllCompanyChoice)
            <select name="{{ \Bangsamu\Master\Services\MasterScope::OWNER_COMPANY_FIELD }}" class="form-select" aria-label="Company pemilik">
                <option value="{{ $ownerValue['company_id'] }}">{{ $ownerScope->ownerLabel($ownerValue) }}</option>
                <option value="0" @selected((string) old(\Bangsamu\Master\Services\MasterScope::OWNER_COMPANY_FIELD) === '0')>{{ $ownerScope->ownerLabel(['app_code' => $ownerValue['app_code'] ?? null, 'company_id' => 0]) }}</option>
            </select>
        @else
            <input type="text" class="form-control" value="{{ $ownerScope->ownerLabel($ownerValue ?? []) }}" readonly disabled>
        @endif
        @error(\Bangsamu\Master\Services\MasterScope::OWNER_COMPANY_FIELD) <span class="text-danger">{{ $message }}</span> @enderror
    </div>
@endif
