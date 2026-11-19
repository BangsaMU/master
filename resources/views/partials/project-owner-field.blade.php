{{--
    Pemilik project di form Project (di DALAM <form>), data dari ProjectController::ownerFormData() lewat
    $data['page']['project_owner']:
    - create: centang "Pemilik project bukan <pemilik bawaan>" + pilihan company. Hanya tampil bila user boleh memilih
      (admin Master Data: semua company; session delegasi: company-nya saja, tercentang dari awal). Tidak dicentang =
      perilaku lama (pemilik bawaan).
    - edit/show: pemilik sekarang (hanya baca). Pindah pemilik ada di partial project-owner-transfer (form terpisah).
--}}
@php
    $po = $data['page']['project_owner'] ?? null;
    $poOtherField = \Bangsamu\Master\Services\ProjectOwnerDetail::FIELD_OTHER_OWNER;
    $poCompanyField = \Bangsamu\Master\Services\ProjectOwnerDetail::FIELD_OWNER_COMPANY;
@endphp
@if ($po && $po['mode'] === 'create' && $po['choices']->isNotEmpty())
    @php
        $poChecked = (bool) old($poOtherField, $po['checked_company'] !== null);
        $poSelected = (string) old($poCompanyField, $po['checked_company'] ?? '');
    @endphp
    <div class="form-group mb-3" data-project-owner>
        <input type="hidden" name="{{ $poOtherField }}" value="0">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="{{ $poOtherField }}" name="{{ $poOtherField }}" value="1" @checked($poChecked)>
            <label class="form-check-label" for="{{ $poOtherField }}">Pemilik project bukan {{ $po['default_label'] }}</label>
        </div>
        <div class="mt-2" data-project-owner-company @if (! $poChecked) style="display: none;" @endif>
            <label for="{{ $poCompanyField }}">Company pemilik</label>
            <select class="form-control form-select @error($poCompanyField) is-invalid @enderror" name="{{ $poCompanyField }}" id="{{ $poCompanyField }}">
                <option value="">-- pilih company --</option>
                @foreach ($po['choices'] as $poCompany)
                    <option value="{{ $poCompany->id }}" @selected($poSelected === (string) $poCompany->id)>
                        {{ $poCompany->company_code }}{{ filled($poCompany->company_name) ? ' - '.$poCompany->company_name : '' }}
                    </option>
                @endforeach
            </select>
            @error($poCompanyField)
                <span class="text-danger">{{ $message }}</span>
            @enderror
            <small class="form-text text-muted d-block">
                Project ini hanya dibuatkan baris Project Detail untuk company terpilih, tanpa baris {{ $po['default_label'] }}.
                Kode project tidak boleh sudah dipakai company itu di project lain. Pemilik bisa dipindah nanti lewat
                Edit Project → Pindah pemilik (admin Master Data).
            </small>
        </div>
    </div>
    <script>
        (function () {
            var box = document.getElementById(@json($poOtherField));
            var panel = box ? box.closest('[data-project-owner]').querySelector('[data-project-owner-company]') : null;
            if (!box || !panel) {
                return;
            }
            box.addEventListener('change', function () {
                panel.style.display = box.checked ? '' : 'none';
            });
        })();
    </script>
@elseif ($po && $po['mode'] === 'edit')
    <div class="form-group mb-3" data-project-owner>
        <label>Pemilik project</label>
        <div class="form-control-plaintext">
            <strong data-project-owner-label>{{ $po['owner_label'] }}</strong>
            @if ($po['other'])
                <span class="badge bg-info badge-info text-bg-info ms-1 ml-1">bukan {{ $po['default_label'] }}</span>
            @endif
        </div>
        <small class="form-text text-muted d-block">
            Pemilik hanya bisa diganti lewat Pindah pemilik (admin Master Data). Baris Project Detail company pemilik
            mengikuti kode/nama project ini dan dikunci di halaman Project Detail.
        </small>
    </div>
@endif
