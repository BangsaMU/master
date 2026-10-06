@php
    if (config('app.themes') == '_tabler') {
        $themeLayout = view()->exists('layouts.tabler')
            ? 'layouts.tabler'
            : 'master::layouts.tabler';
    } else {
        $themeLayout = 'adminlte::page';
    }
    $pageTitle = ($title ?? 'Create') . ' ' . (@$data['page']['sheet_name'] ?? 'Employee');
@endphp

@extends($themeLayout)

@section('title', $pageTitle)

@section('header')
    <div class="row align-items-center">
        <div class="col">
            <h2 class="page-title">{{ $title ?? 'Create' }} Employee</h2>
            <div class="text-secondary mt-1">Form {{ strtolower($title ?? 'Create') }} data employee internal</div>
        </div>
        <div class="col-auto ms-auto d-print-none">
            <a href="{{ route('master.employee.index') }}" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-left me-1"></i> Kembali ke Daftar
            </a>
        </div>
    </div>
@stop

@section('content_header')
    <h1 class="m-0 text-dark">{{ $title ?? 'Create' }} Employee</h1>
@stop

@section('content')
    <div class="container-xl">
        {{-- Session Messages --}}
        @if (Session::has('error'))
            @php $sessErrors = Session::get('error'); @endphp
            @if (!empty($sessErrors))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <div class="d-flex">
                        <div>
                            @if (is_array($sessErrors))
                                @foreach ($sessErrors as $err)
                                    <div>{{ is_array($err) ? ($err['message'] ?? implode(', ', $err)) : $err }}</div>
                                @endforeach
                            @else
                                <div>{{ $sessErrors }}</div>
                            @endif
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
        @endif

        @if (Session::has('success'))
            @php $sessSuccess = Session::get('success'); @endphp
            @if (!empty($sessSuccess))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <div class="d-flex">
                        <div>
                            @if (is_array($sessSuccess))
                                @foreach ($sessSuccess as $succ)
                                    <div>{{ is_array($succ) ? ($succ['message'] ?? implode(', ', $succ)) : $succ }}</div>
                                @endforeach
                            @else
                                <div>{{ $sessSuccess }}</div>
                            @endif
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <div class="fw-bold mb-1">Terdapat kesalahan pada input form:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form action="{{ $data['page']['store'] }}" method="POST" enctype="multipart/form-data" autocomplete="off" id="form-employee">
            @csrf

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ $title ?? 'Create' }} Employee</h3>
                </div>

                <div class="card-body">
                    <input type="hidden" name="id" id="id" value="{{ @$param->id ? $param->id : old('id') }}">

                    {{-- Row 1: Nama Lengkap, Email, Corporate Email --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label required" for="employee_name">Nama Lengkap</label>
                            <input type="text"
                                class="form-control @error('employee_name') is-invalid @enderror"
                                id="employee_name"
                                placeholder="Nama Lengkap"
                                name="employee_name"
                                value="{{ old('employee_name', @$param->employee_name) }}"
                                style="text-transform:uppercase"
                                oninput="this.value = this.value.toUpperCase()"
                                required>
                            @error('employee_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="employee_email">Email</label>
                            <input type="email"
                                class="form-control @error('employee_email') is-invalid @enderror"
                                id="employee_email"
                                placeholder="Email"
                                name="employee_email"
                                value="{{ old('employee_email', @$param->employee_email) }}">
                            @error('employee_email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="corporate_email">Email Corporate</label>
                            <input type="email"
                                class="form-control @error('corporate_email') is-invalid @enderror"
                                id="corporate_email"
                                placeholder="Email Corporate"
                                name="corporate_email"
                                value="{{ old('corporate_email', @$param->corporate_email) }}">
                            @error('corporate_email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Row 2: Citizenship, Country code, No KTP, Gender, Golongan Darah --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-2">
                            <label class="form-label required" for="citizenship">Citizenship</label>
                            <select class="form-select @error('citizenship') is-invalid @enderror"
                                id="citizenship"
                                name="citizenship"
                                required>
                                <option value="" {{ (old('citizenship', @$param->citizenship) == '') ? 'selected' : '' }}>-</option>
                                <option value="WNI" {{ (old('citizenship', @$param->citizenship) == 'WNI') ? 'selected' : '' }}>WNI</option>
                                <option value="WNA" {{ (old('citizenship', @$param->citizenship) == 'WNA') ? 'selected' : '' }}>WNA</option>
                            </select>
                            @error('citizenship')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label" for="country_code">Country code</label>
                            @php
                                $countryList = $param->list_country_code ?? (is_array($param->country_code) ? $param->country_code : []);
                                $selectedCountry = old('country_code', is_string(@$param->country_code) ? $param->country_code : (@$param->country_code_selected ?? ''));
                            @endphp
                            <select class="form-select @error('country_code') is-invalid @enderror"
                                id="country_code"
                                name="country_code">
                                @if (!empty($countryList))
                                    @foreach ($countryList as $key_code => $val_code)
                                        <option value="{{ $key_code }}" {{ ($selectedCountry == $key_code) ? 'selected' : '' }}>
                                            {{ $val_code }}
                                        </option>
                                    @endforeach
                                @else
                                    <option value="" selected>-</option>
                                @endif
                            </select>
                            @error('country_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label required" for="no_ktp">No KTP</label>
                            <input type="text"
                                class="form-control @error('no_ktp') is-invalid @enderror"
                                id="no_ktp"
                                placeholder="No KTP"
                                name="no_ktp"
                                value="{{ old('no_ktp', @$param->no_ktp) }}"
                                required>
                            @error('no_ktp')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-2">
                            <label class="form-label" for="gender">Gender</label>
                            <select class="form-select @error('gender') is-invalid @enderror"
                                id="gender"
                                name="gender">
                                @php
                                    $genderList = isset($param->gender) && is_array($param->gender)
                                        ? $param->gender
                                        : ['' => '-', 'laki-laki' => 'Laki-Laki', 'perempuan' => 'Perempuan'];
                                @endphp
                                @foreach ($genderList as $key_g => $val_g)
                                    <option value="{{ $key_g }}" {{ (old('gender', @$param->gender_selected ?? @$param->gender) == $key_g) ? 'selected' : '' }}>
                                        {{ $val_g }}
                                    </option>
                                @endforeach
                            </select>
                            @error('gender')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-2">
                            <label class="form-label" for="input_employee_blood_type">Golongan Darah</label>
                            @php
                                $currentBlood = old('employee_blood_type', @$param->employee_blood_type ?? '-');
                            @endphp
                            <select class="form-select @error('employee_blood_type') is-invalid @enderror"
                                name="employee_blood_type"
                                id="input_employee_blood_type">
                                @foreach (['-', 'A', 'A+', 'A-', 'B', 'B+', 'B-', 'O', 'O+', 'O-', 'AB', 'AB+', 'AB-'] as $bType)
                                    <option value="{{ $bType }}" {{ $currentBlood == $bType ? 'selected' : '' }}>
                                        {{ $bType }}
                                    </option>
                                @endforeach
                            </select>
                            @error('employee_blood_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Row 3: Status, Posisi Jabatan, Job List --}}
                    <div class="row g-3 mb-3" id="row_status_position">
                        <div class="col-12 col-md-6" id="col_status">
                            <label class="form-label required" for="status_id">Status</label>
                            @if (isset($param->id))
                                <input type="hidden" name="status_id" value="{{ $param->status_id }}">
                            @endif
                            <select class="select2-status form-select @error('status_id') is-invalid @enderror"
                                name="status_id"
                                id="status_id"
                                required>
                                <option value="">Pilih Status</option>
                                @if (isset($param->status))
                                    @foreach ($param->status as $st)
                                        @php
                                            $stVal = is_object($st) ? $st->id : ($st['id'] ?? $st);
                                            $stKode = is_object($st) ? ($st->kode ?? '') : ($st['kode'] ?? '');
                                            $stText = is_object($st)
                                                ? (isset($st->kode) ? $st->kode . ' - ' . $st->status : ($st->status_label ?? $st->status))
                                                : ($st['status'] ?? $st);
                                            $isSelected = old('status_id', @$param->status_id) == $stVal;
                                        @endphp
                                        <option value="{{ $stVal }}" data-kode="{{ $stKode }}" {{ $isSelected ? 'selected' : '' }}>
                                            {{ $stText }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            @error('status_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6" id="col_job_position">
                            <label class="form-label" for="inputJobPosition">(Dep.) - Posisi Jabatan</label>
                            <select class="form-select @error('job_position_id') is-invalid @enderror"
                                id="inputJobPosition"
                                name="job_position_id">
                                @if (@$param && @$param->job_position_id)
                                    <option value="{{ $param->job_position_id }}" selected>
                                        ({{ $param->department_name ?? '-' }}) {{ $param->position_code ?? '-' }} - {{ $param->employee_job_title }}
                                    </option>
                                @endif
                            </select>
                            @error('job_position_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-4" id="col_job_list" style="display: none;">
                            <label class="form-label" for="job_list">Job List</label>
                            <select name="job_list[]" id="job_list" class="form-select job_list_select2-tags" multiple="multiple" style="width: 100%;">
                                @php
                                    $jobListValues = [];
                                    $rawJobList = old('job_list', @$param->job_list);
                                    if (!empty($rawJobList)) {
                                        $jobListValues = is_array($rawJobList) ? $rawJobList : explode(',', $rawJobList);
                                    }
                                @endphp
                                @foreach ($jobListValues as $jlItem)
                                    @php $jlItem = trim($jlItem); @endphp
                                    @if ($jlItem !== '')
                                        <option value="{{ $jlItem }}" selected>{{ $jlItem }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('job_list')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Row 4: Hire Lokasi, Lokasi Kerja, Company --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="hire_id">Hire Lokasi</label>
                            @if (isset($param->id))
                                <input type="hidden" name="hire_id" value="{{ $param->hire_id }}">
                            @else
                                <input type="hidden" name="hire_id" value="{{ @$location_id }}">
                            @endif
                            <select class="select2-hire form-select @error('hire_id') is-invalid @enderror"
                                name="hire_id"
                                id="hire_id"
                                disabled>
                                <option value="">Pilih Hire Lokasi</option>
                                @if (isset($param->hire_loc))
                                    @foreach ($param->hire_loc as $loc)
                                        @php
                                            $locVal = is_object($loc) ? $loc->id : ($loc['id'] ?? $loc);
                                            $locCode = is_object($loc) ? ($loc->loc_code ?? '') : ($loc['loc_code'] ?? '');
                                            $locName = is_object($loc) ? ($loc->loc_name ?? '') : ($loc['loc_name'] ?? '');
                                            $selectedHire = isset($param->id)
                                                ? (old('hire_id', @$param->hire_id) == $locVal)
                                                : (old('hire_id', @$location_id) == $locVal);
                                        @endphp
                                        <option value="{{ $locVal }}" {{ $selectedHire ? 'selected' : '' }}>
                                            {{ $locCode ? $locCode . ' - ' : '' }}{{ $locName }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            @error('hire_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="inputWorkLocation">Lokasi Kerja</label>
                            <input type="hidden"
                                name="work_location_id"
                                id="hidden_work_location_id"
                                value="{{ old('work_location_id', @$param->work_location_id) }}">
                            <select class="form-select @error('work_location_id') is-invalid @enderror"
                                name="work_location_id"
                                id="inputWorkLocation">
                                @if (old('work_location_id', @$param->work_location_id))
                                    <option value="{{ old('work_location_id', @$param->work_location_id) }}" selected>
                                        {{ @$param->work_location_name ?? 'Lokasi Terpilih' }}
                                    </option>
                                @endif
                            </select>
                            @error('work_location_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="inputCompany">Company</label>
                            <select class="form-select @error('company_id') is-invalid @enderror"
                                name="company_id"
                                id="inputCompany">
                                <option value="{{ old('company_id', @$param->company_id ?? 1) }}" selected>
                                    {{ @$param->company_name ?? 'PT Meindo Elang Indah' }}
                                </option>
                            </select>
                            @error('company_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Row 5: Tanggal Lahir, Tanggal Join, Tanggal Akhir Kontrak, Tanggal Akhir Kerja --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="employee_dob">Tanggal Lahir</label>
                            <input type="date"
                                class="form-control @error('employee_dob') is-invalid @enderror"
                                id="employee_dob"
                                name="employee_dob"
                                value="{{ old('employee_dob', @$param->employee_dob) }}"
                                {{ isset($param->id) ? 'readonly' : '' }}>
                            @error('employee_dob')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label" for="tanggal_join">Tanggal Join</label>
                            <input type="date"
                                class="form-control @error('tanggal_join') is-invalid @enderror"
                                id="tanggal_join"
                                name="tanggal_join"
                                value="{{ old('tanggal_join', @$param->tanggal_join) }}"
                                {{ isset($param->id) ? 'readonly' : '' }}>
                            @error('tanggal_join')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label" for="tanggal_akhir_kontrak">Tanggal Akhir Kontrak</label>
                            <input type="date"
                                class="form-control @error('tanggal_akhir_kontrak') is-invalid @enderror"
                                id="tanggal_akhir_kontrak"
                                name="tanggal_akhir_kontrak"
                                value="{{ old('tanggal_akhir_kontrak', @$param->tanggal_akhir_kontrak) }}">
                            @error('tanggal_akhir_kontrak')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label" for="tanggal_akhir_kerja">Tanggal Akhir Kerja</label>
                            <input type="date"
                                class="form-control @error('tanggal_akhir_kerja') is-invalid @enderror"
                                id="tanggal_akhir_kerja"
                                name="tanggal_akhir_kerja"
                                value="{{ old('tanggal_akhir_kerja', @$param->tanggal_akhir_kerja) }}">
                            @error('tanggal_akhir_kerja')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Row 6: Employee Phone, Emergency Phone --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="employee_phone">Employee Phone</label>
                            <input type="text"
                                class="form-control @error('employee_phone') is-invalid @enderror"
                                id="employee_phone"
                                placeholder="Employee Phone"
                                name="employee_phone"
                                value="{{ old('employee_phone', @$param->employee_phone) }}"
                                style="text-transform:uppercase"
                                oninput="this.value = this.value.toUpperCase()">
                            @error('employee_phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label" for="emergency_phone">Emergency Phone</label>
                            <input type="text"
                                class="form-control @error('emergency_phone') is-invalid @enderror"
                                id="emergency_phone"
                                placeholder="Emergency Phone"
                                name="emergency_phone"
                                value="{{ old('emergency_phone', @$param->emergency_phone) }}"
                                style="text-transform:uppercase"
                                oninput="this.value = this.value.toUpperCase()">
                            @error('emergency_phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Row 7: Keterangan --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label" for="keterangan">Keterangan</label>
                            <textarea class="form-control @error('keterangan') is-invalid @enderror"
                                id="keterangan"
                                placeholder="Keterangan"
                                rows="3"
                                name="keterangan">{{ old('keterangan', @$param->keterangan) }}</textarea>
                            @error('keterangan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Row 8: No ID Karyawan (hanya tampil saat update jika sudah memiliki data) --}}
                    @if (isset($param->id) && $param->id)
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="no_id_karyawan">No ID Karyawan</label>
                                <input type="text"
                                    class="form-control @error('no_id_karyawan') is-invalid @enderror"
                                    id="no_id_karyawan"
                                    name="no_id_karyawan"
                                    value="{{ old('no_id_karyawan', @$param->no_id_karyawan) }}"
                                    readonly>
                                @error('no_id_karyawan')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    @endif
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="{{ route('master.employee.index') }}" class="btn btn-secondary">
                        <i class="ti ti-x me-1"></i> Cancel
                    </a>

                    @php
                        $canSave = !isset($param->id)
                            || checkPermission('list_karyawan_update')
                            || checkPermission('admin')
                            || checkPermission('is_admin')
                            || !($data['page']['readonly'] ?? false);
                    @endphp

                    @if ($canSave)
                        <button type="submit" class="btn btn-primary" id="btn-save">
                            <i class="ti ti-device-floppy me-1"></i> Save
                        </button>
                    @endif
                </div>
            </div>
        </form>
    </div>
@stop

@push('css')
    <style>
        .select2-container .select2-selection--single {
            height: calc(2.25rem + 2px) !important;
            padding: 0.375rem 0.75rem;
            border-color: #dce1e7;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding-left: 0;
            color: inherit;
        }
        .select2-container--default.select2-container--disabled .select2-selection--single {
            background-color: #f1f5f9;
            cursor: not-allowed;
        }
        .select2-container--default .select2-selection--multiple {
            min-height: calc(2.25rem + 2px);
            border-color: #dce1e7;
            padding-bottom: 3px !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            display: flex !important;
            flex-wrap: wrap !important;
            white-space: normal !important;
            gap: 4px;
            padding: 2px 4px !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            margin-top: 2px !important;
            margin-bottom: 2px !important;
            font-size: 0.85rem;
        }
    </style>
@endpush

@push('js')
    <script>
        $(document).ready(function() {
            var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

            // Select2 Company (Master Vendor)
            $('#inputCompany').select2({
                width: '100%',
                placeholder: 'Please select Company',
                ajax: {
                    url: "{!! url('api/getmaster_vendorbyparams?id=1&set[field][]=vendor_code&set[text]=vendor_description&_token=' . Bangsamu\LibraryClay\Controllers\LibraryClayController::api_token(null)) !!}",
                    type: "get",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            "search[vendor_code][|]": params.term,
                            "search[vendor_description][|]": params.term,
                        };
                    },
                    processResults: function(response) {
                        return { results: response };
                    },
                    cache: true
                }
            });

            // Select2 Lokasi Kerja
            $('#inputWorkLocation').select2({
                width: '100%',
                placeholder: 'Please select Lokasi Kerja',
                ajax: {
                    url: "{{ route('getlocationbyparams') }}",
                    type: "get",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            _token: CSRF_TOKEN,
                            search: params.term
                        };
                    },
                    processResults: function(response) {
                        return { results: response };
                    },
                    cache: true
                }
            });

            $('#inputWorkLocation').on('change select2:select', function(e) {
                var selectedVal = $(this).val();
                $('#hidden_work_location_id').val(selectedVal);
            });

            // Select2 Job Position
            $('#inputJobPosition').select2({
                width: '100%',
                placeholder: 'Please select Job Position',
                ajax: {
                    url: "{{ route('getjobpositionbyparams') }}",
                    type: "get",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            _token: CSRF_TOKEN,
                            search: params.term
                        };
                    },
                    processResults: function(response) {
                        return { results: response };
                    },
                    cache: true
                }
            });

            // Select2 Job List (Multiple Tags)
            var $jobList = $('.job_list_select2-tags');
            $jobList.select2({
                tags: true,
                tokenSeparators: [',', ';'],
                placeholder: 'Cari atau ketik job position (tekan enter / pisahkan koma)',
                ajax: {
                    url: "{{ route('getjobPositionlistbyparams') }}",
                    type: "GET",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            search: params.term,
                            q: params.term,
                            _token: CSRF_TOKEN
                        };
                    },
                    processResults: function(response) {
                        return {
                            results: response
                        };
                    },
                    cache: true
                }
            });

            // Inisialisasi select2 status & hire & country code
            $('.select2-status').select2({ width: '100%' });
            $('.select2-hire').select2({ width: '100%' });
            $('#country_code').select2({ width: '100%' });

            // Toggle Job List visibility saat Status 0 / Hire
            function toggleJobList() {
                var selectedOpt = $('#status_id option:selected');
                var statusKode = selectedOpt.data('kode');
                var statusVal = $('#status_id').val();
                var statusText = selectedOpt.text().trim().toUpperCase();

                var isHire = (statusKode === '0' || statusKode === 0 || statusVal === '0' || statusText.indexOf('0 - HIRE') !== -1 || statusText.indexOf('HIRE') !== -1);

                if (isHire) {
                    $('#col_job_list').show();
                    $('#col_status').removeClass('col-md-6').addClass('col-md-4');
                    $('#col_job_position').removeClass('col-md-6').addClass('col-md-4');
                } else {
                    $('#col_job_list').hide();
                    $('#col_status').removeClass('col-md-4').addClass('col-md-6');
                    $('#col_job_position').removeClass('col-md-4').addClass('col-md-6');
                }
            }

            $('#status_id').on('change', function() {
                toggleJobList();
            });

            toggleJobList();

            // Otomatisasi WNI -> IDN country code
            $('#citizenship').on('change', function() {
                if ($(this).val() === 'WNI') {
                    if ($('#country_code option[value="IDN"]').length > 0) {
                        $('#country_code').val('IDN').trigger('change');
                    }
                }
            });

            let id = $("input#id").val();
            let tanggal_akhir_kerja = $("input#tanggal_akhir_kerja").val();

            // Read-only permission check
            let list_karyawan_read_permission = '{{ checkPermission('list_karyawan_read') ? "1" : "" }}';
            if (list_karyawan_read_permission && !'{{ checkPermission('admin') || checkPermission('is_admin') || checkPermission('list_karyawan_update') ? "1" : "" }}') {
                $("input, textarea").attr("readonly", true);
                $("select").attr("disabled", true);
                $("#btn-save").remove();
            }

            // Edit logic
            if (id && id.length > 0) {
                let list_karyawan_update_permission = '{{ (checkPermission('list_karyawan_update') || checkPermission('admin') || checkPermission('is_admin')) ? "1" : "" }}';
                if (list_karyawan_update_permission) {
                    if (!'{{ checkPermission('admin') || checkPermission('is_admin') ? "1" : "" }}') {
                        $("#status_id, #hire_id, #tanggal_join, #no_id_karyawan, #employee_dob").attr("readonly", true);
                    }
                }
            }

            // Admin permission: full access
            let admin_permission = '{{ (checkPermission('admin') || checkPermission('is_admin')) ? "1" : "" }}';
            if (admin_permission) {
                $("#no_id_karyawan").attr("readonly", true);
            }

            // Non-aktif jika tanggal akhir kerja sudah terisi dan bukan admin
            if (tanggal_akhir_kerja && tanggal_akhir_kerja !== '') {
                if (!admin_permission) {
                    $("input, textarea").attr("readonly", true);
                    $("select").attr("disabled", true);
                    $("#btn-save").remove();
                }
            }
        });
    </script>
@endpush

