@php
    $theme = config('app.themes');
    if ($theme == '_meridian') {
        $themeLayout = view()->exists('layouts.meridian')
            ? 'layouts.meridian'
            : 'master::layouts.meridian';
    } elseif ($theme == '_tabler') {
        $themeLayout = view()->exists('layouts.tabler')
            ? 'layouts.tabler'
            : 'master::layouts.tabler';
    } else {
        $themeLayout = 'adminlte::page';
    }
@endphp
@extends($themeLayout)

@section('title', @$data['page']['title'])

@section('header')
    <h1 class="page__title font-bold text-2xl m-0">
        {{ isset($param->id) ? 'Edit' : 'Create' }} {{ $data['page']['title'] }}
    </h1>
    <p class="page__description text-sm text-muted-foreground mt-1">
        Manage employee details and location assignments.
    </p>
@endsection

@section('content')
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card__header p-4 border-b border-border flex items-center justify-between">
                    <h3 class="card__title font-bold text-base m-0">{{ $data['page']['title'] }} Form</h3>
                </div>
                <div class="card__body p-6">
                    <form action="{{ $data['page']['store'] }}" method="POST" autocomplete="off" class="flex flex-col gap-4">
                        @csrf

                        @if ($param)
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id"
                                id="id" value="{{ isset($param->id) ? $param->id : old('id') }}">
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="employee_name">Nama Lengkap</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="input w-full @error('employee_name') border-danger @enderror" id="employee_name"
                                    placeholder="Nama Lengkap" name="employee_name"
                                    value="{{ @$param->employee_name ? $param->employee_name : old('employee_name') }}"
                                    style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase()">
                                @error('employee_name')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="employee_email">Email</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="email"
                                    class="input w-full @error('employee_email') border-danger @enderror" id="employee_email"
                                    placeholder="Email" name="employee_email"
                                    value="{{ isset($param->employee_email) ? $param->employee_email : old('employee_email') }}">
                                @error('employee_email')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="corporate_email">Email Corporate</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="email"
                                    class="input w-full @error('corporate_email') border-danger @enderror" id="corporate_email"
                                    placeholder="Email Corporate" name="corporate_email"
                                    value="{{ isset($param->corporate_email) ? $param->corporate_email : old('corporate_email') }}">
                                @error('corporate_email')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="no_ktp">No KTP</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="input w-full @error('no_ktp') border-danger @enderror" id="no_ktp"
                                    placeholder="No KTP" name="no_ktp"
                                    value="{{ isset($param->no_ktp) ? $param->no_ktp : old('no_ktp') }}">
                                @error('no_ktp')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="status_id">Status</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="status_id"
                                    value="{{ old('status_id') !== null ? old('status_id') : @$param->status_id }}">

                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }}
                                    class="select select2-status w-full @error('status_id') border-danger @enderror"
                                    name="status_id" id="status_id">
                                    <option value="" selected>Pilih Status</option>
                                    @isset($param->status)
                                        @foreach ($param->status as $st)
                                            <option value="{{ $st->id }}"
                                                @if ((old('status_id') !== null ? old('status_id') : @$param->status_id) == $st->id) selected @endif>
                                                {{ $st->status }}</option>
                                        @endforeach
                                    @endisset
                                </select>
                                @error('status_id')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="employee_job_title">Posisi (Jabatan)</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="input w-full @error('employee_job_title') border-danger @enderror"
                                    id="employee_job_title" placeholder="Posisi" name="employee_job_title"
                                    value="{{ isset($param->employee_job_title) ? $param->employee_job_title : old('employee_job_title') }}"
                                    style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase()">
                                @error('employee_job_title')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="hire_id">Hire Lokasi</label>
                                @if (isset($param))
                                    <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="hire_id"
                                        value="{{ isset($param->hire_id) ? $param->hire_id : old('hire_id') }}">
                                @else
                                    <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="hire_id"
                                        value="{{ @$location_id }}">
                                @endif
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }}
                                    class="select select2-hire w-full @error('hire_id') border-danger @enderror" name="hire_id"
                                    id="hire_id">

                                    <option value="" selected>Pilih Hire Lokasi</option>

                                    @if (isset($param->hire_loc))
                                        @foreach ($param->hire_loc as $loc)
                                            <option value="{{ $loc->id }}"
                                                @if ((isset($param->hire_id) ? $param->hire_id : old('hire_id')) == $loc->id) echo selected @endif>
                                                {{ $loc->loc_code . ' - ' . $loc->loc_name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('hire_id')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="tanggal_join">Tanggal Join</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date"
                                    class="input w-full @error('tanggal_join') border-danger @enderror" id="tanggal_join"
                                    placeholder="Tanggal Join" name="tanggal_join"
                                    value="{{ isset($param->tanggal_join) ? $param->tanggal_join : old('tanggal_join') }}"
                                    @isset($param->tanggal_join) readonly @endisset>
                                @error('tanggal_join')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="tanggal_akhir_kerja">Tanggal Akhir Kerja</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date"
                                    class="input w-full @error('tanggal_akhir_kerja') border-danger @enderror"
                                    id="tanggal_akhir_kerja" placeholder="Last Working Date" name="tanggal_akhir_kerja"
                                    value="{{ isset($param->tanggal_akhir_kerja) ? $param->tanggal_akhir_kerja : old('tanggal_akhir_kerja') }}">
                                @error('tanggal_akhir_kerja')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="tanggal_akhir_kontrak">Tanggal Akhir Kontrak</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date"
                                    class="input w-full @error('tanggal_akhir_kontrak') border-danger @enderror" id="tanggal_akhir_kontrak"
                                    placeholder="Tanggal Akhir Kontrak" name="tanggal_akhir_kontrak"
                                    value="{{ isset($param->tanggal_akhir_kontrak) ? $param->tanggal_akhir_kontrak : old('tanggal_akhir_kontrak') }}">
                                @error('tanggal_akhir_kontrak')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="inputWorkLocation">Lokasi Kerja</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden"
                                    name="work_location_id"
                                    value="{{ isset($param->work_location_id) ? $param->work_location_id : old('work_location_id') }}">
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }}
                                    class="select w-full @error('work_location_id') border-danger @enderror"
                                    name="work_location_id" id="inputWorkLocation">
                                    @if (isset($param->work_location))
                                        @foreach ($param->work_location as $loc)
                                            <option value="{{ $loc->id }}"
                                                @if ((isset($param->work_location_id) ? $param->work_location_id : old('work_location_id')) == $loc->id) selected @endif>
                                                {{ $loc->loc_code . ' - ' . $loc->loc_name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('work_location_id')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="keterangan">Keterangan</label>
                                <textarea {{ $data['page']['readonly'] ? 'disabled' : '' }}
                                    class="textarea w-full @error('keterangan') border-danger @enderror" id="keterangan" placeholder="Keterangan"
                                    name="keterangan" rows="3">{{ isset($param->keterangan) ? $param->keterangan : old('keterangan') }}</textarea>
                                @error('keterangan')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="input_employee_blood_type">Golongan Darah</label>
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }} autocomplete="off"
                                    class="select w-full" name="employee_blood_type"
                                    id="input_employee_blood_type">
                                    <option @if ((@$param->employee_blood_type ? $param->employee_blood_type : '-') == '-') selected="" @endif value="-"> - </option>
                                    <option @if ((@$param->employee_blood_type ? $param->employee_blood_type : '-') == 'A') selected="" @endif value="A"> A </option>
                                    <option @if ((@$param->employee_blood_type ? $param->employee_blood_type : '-') == 'A+') selected="" @endif value="A+"> A+ </option>
                                    <option @if ((@$param->employee_blood_type ? $param->employee_blood_type : '-') == 'A-') selected="" @endif value="A-"> A- </option>
                                    <option @if ((@$param->employee_blood_type ? $param->employee_blood_type : '-') == 'B') selected="" @endif value="B"> B </option>
                                    <option @if ((@$param->employee_blood_type ? $param->employee_blood_type : '-') == 'B+') selected="" @endif value="B+"> B+ </option>
                                    <option @if ((@$param->employee_blood_type ? $param->employee_blood_type : '-') == 'B-') selected="" @endif value="B-"> B- </option>
                                    <option @if ((@$param->employee_blood_type ? $param->employee_blood_type : '-') == 'O') selected="" @endif value="O"> O </option>
                                    <option @if ((@$param->employee_blood_type ? $param->employee_blood_type : '-') == 'O+') selected="" @endif value="O+"> O+ </option>
                                    <option @if ((@$param->employee_blood_type ? $param->employee_blood_type : '-') == 'O-') selected="" @endif value="O-"> O- </option>
                                    <option @if ((@$param->employee_blood_type ? $param->employee_blood_type : '-') == 'AB') selected="" @endif value="AB"> AB </option>
                                    <option @if ((@$param->employee_blood_type ? $param->employee_blood_type : '-') == 'AB+') selected="" @endif value="AB+"> AB+ </option>
                                    <option @if ((@$param->employee_blood_type ? $param->employee_blood_type : '-') == 'AB-') selected="" @endif value="AB-"> AB- </option>
                                </select>
                                @error('employee_blood_type')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            @if (@$param)
                                <div class="field flex flex-col gap-1">
                                    <label class="field__label text-sm font-medium text-foreground" for="no_id_karyawan">No ID Karyawan</label>
                                    <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                        class="input w-full @error('no_id_karyawan') border-danger @enderror"
                                        id="no_id_karyawan" placeholder="No KTP" name="no_id_karyawan"
                                        value="{{ isset($param->no_id_karyawan) ? $param->no_id_karyawan : old('no_id_karyawan') }}"
                                        readonly>
                                    @error('no_id_karyawan')
                                        <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-border">
                            @if ($data['page']['readonly'] == false)
                                <button type="submit" id="btn-save" class="button button--primary font-semibold">Submit</button>
                            @endif
                            <a href="{{ Route::has('master.employee.index') ? route('master.employee.index') : '#' }}" class="button button--neutral button--outline">
                                Back
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@stop

@push('js')
    @if (isset($data['page']['js']))
        @include($data['page']['js'])
    @endif

    <script>
        $(document).ready(function() {
            var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

            $('#inputWorkLocation').select2({
                width: '100%',
                placeholder: 'Please select Lokasi Kerja',
                ajax: {
                    url: "{{ url('api/getmaster_locationbyparams') }}",
                    type: "get",
                    dataType: 'json',
                    delay: 5,
                    data: function(params) {
                        return {
                            _token: CSRF_TOKEN,
                            "set[field][]":"loc_code",
                            "set[text]":"loc_name",
                            "search[loc_name]":params.term
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

            let id = $("input#id").val();
            let tanggal_akhir_kerja = $("input#tanggal_akhir_kerja").val();

            let list_karyawan_read_premission = '{{ optional(auth()->user())->can('list_karyawan_read') }}';
            if (list_karyawan_read_premission) {
                $("input, textarea").attr("readonly", true);
                $("select").attr("disabled", true);
            }

            if (id && id.length > 0) {
                let list_karyawan_update_premission = '{{ optional(auth()->user())->can('list_karyawan_update') }}';
                if (list_karyawan_update_premission) {
                    $("#status_id, #hire_id,  #tanggal_join, #no_id_karyawan").attr("readonly", true);
                    $("#nama, #email, #no_ktp, #posisi, #tanggal_akhir_kerja, #tanggal_akhir_kontrak, #email_corporate, #keterangan")
                        .attr("readonly", false);
                    $("#inputWorkLocation").attr("disabled", false);
                }
            } else {
                let list_karyawan_create_premission = '{{ optional(auth()->user())->can('list_karyawan_create') }}';
                if (list_karyawan_create_premission) {
                    $("input, textarea").attr("readonly", false);
                    $("select").attr("readonly", false);
                    $("select").attr("disabled", false);
                }
            }

            let admin_permission = '{{ optional(auth()->user())->can('admin') }}';
            if (admin_permission) {
                $("input, textarea").attr("readonly", false);
                $("select").attr("readonly", false);
                $("select").attr("disabled", false);
            }

            $("#no_id_karyawan").attr("readonly", true);

            if (tanggal_akhir_kerja != null && tanggal_akhir_kerja != '') {
                if (!admin_permission) {
                    $("input, textarea").attr("readonly", true);
                    $("select").attr("disabled", true);
                    $("#btn-save").remove();
                }
            }

            $('.select2-status').select2();
            $('.select2-hire').select2();
        });
    </script>
@endpush
