@extends('adminlte::page')

@section('title', @$data['page']['title'])

@section('content_header')
    <h1 class="m-0 text-dark">{{ isset($param->id) ? 'Edit' : 'Create' }} {{ $data['page']['title'] }}</h1>
@stop

@section('content')
{{-- add form citizenship --}}
    <div class="row">
        <div class="col-12">
            <div class="card card-outline card-primary">
                <div class="card-header font-weight-bold">
                    {{ $data['page']['title'] }} Form
                </div>
                <div class="card-body">
                    <form action="{{ $data['page']['store'] }}" method="POST" autocomplete="off">
                        @csrf

                        @if ($param)
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id"
                                id="id" value="{{ isset($param->id) ? $param->id : old('id') }}">
                        @endif


                        <div class="form-group row">
                            <div class="col">
                                <label for="employee_name">Nama Lengkap</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="form-control @error('employee_name') is-invalid @enderror" id="employee_name"
                                    placeholder="Nama Lengkap" name="employee_name"
                                    value="{{ @$param->employee_name ? $param->employee_name : old('employee_name') }}"
                                    style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase()">
                                @error('employee_name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col">
                                <label for="employee_phone">Employee phone</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="form-control @error('employee_phone') is-invalid @enderror" id="employee_phone"
                                    placeholder="Email" name="employee_phone"
                                    value="{{ isset($param->employee_phone) ? $param->employee_phone : old('employee_phone') }}">
                                @error('employee_phone')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col">
                                <label for="employee_email">Email</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="employee_email"
                                    class="form-control @error('employee_email') is-invalid @enderror" id="employee_email"
                                    placeholder="Email" name="employee_email"
                                    value="{{ isset($param->employee_email) ? $param->employee_email : old('employee_email') }}">
                                @error('employee_email')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col">
                                <label for="corporate_email">Email Corporate</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="corporate_email"
                                    class="form-control @error('corporate_email') is-invalid @enderror" id="corporate_email"
                                    placeholder="Email Corporate" name="corporate_email"
                                    value="{{ isset($param->corporate_email) ? $param->corporate_email : old('corporate_email') }}">
                                @error('corporate_email')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col">
                                <label for="citizenship">citizenship</label>
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }} autocomplete="off" class="form-control @error('citizenship') is-invalid @enderror"
                                    id="citizenship" placeholder="Posisi" name="citizenship">
                                        <option value=""  {{ @$param->citizenship==""||old('citizenship')==''? 'selected': '' }} >-</option>
                                        <option value="WNI" {{ @$param->citizenship=="WNI"||old('citizenship')=='WNI'? 'selected': '' }} >WNI</option>
                                        <option value="WNA" {{ @$param->citizenship=="WNA"||old('citizenship')=='WNA'? 'selected': '' }} >WNA</option>
                                </select>
                                @error('citizenship')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col">
                                <label for="country_code">Country code</label>
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }} autocomplete="off" class="form-control @error('country_code') is-invalid @enderror"
                                    id="country_code" placeholder="Posisi" name="country_code">

                                    @if (is_array($param->country_code))
                                        @foreach (@$param->country_code as $key_code=>$val_code)
                                            <option value="{{$key_code}}" {{ @$param->country_code==$key_code||old('country_code')? 'selected': '' }} >{{$val_code}}</option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('country_code')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col">
                                <label for="no_ktp">No KTP</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="form-control @error('no_ktp') is-invalid @enderror" id="no_ktp"
                                    placeholder="No KTP" name="no_ktp"
                                    value="{{ isset($param->no_ktp) ? $param->no_ktp : old('no_ktp') }}">
                                @error('no_ktp')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">

                            <div class="col">
                                <label for="status_id">Status</label>

                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="status_id"
                                    value="{{ old('status_id') !== null ? old('status_id') : @$param->status_id }}">

                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }}
                                    class="select2-status form-control @error('status_id') is-invalid @enderror"
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
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col">

                                <label for="job_position_id">(Dep.) - Posisi Jabatan</label>
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }} autocomplete="off" class="form-control @error('job_position_id') is-invalid @enderror"
                                    id="job_position_id" placeholder="Posisi" name="job_position_id">
                                    @if (@$param->job_position_id)
                                        <option value="{{ $param->job_position_id }}" selected>({{$param->department_name}}) {{$param->position_code}} - {{ $param->employee_job_title }}</option>
                                    @endif
                                </select>
                                @error('job_position_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror

                            </div>
                        </div>

                        <div class="form-group row">

                            <div class="col">
                                <label for="hire_id">Hire Lokasi</label>
                                @if (isset($param))
                                    <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="hire_id"
                                        value="{{ isset($param->hire_id) ? $param->hire_id : old('hire_id') }}">
                                @else
                                    <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="hire_id"
                                        value="{{ @$location_id }}">
                                @endif
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }}
                                    class="select2-hire form-control @error('hire_id') is-invalid @enderror" name="hire_id"
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
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col">
                                <label for="tanggal_join">Tanggal Join</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date"
                                    class="form-control @error('tanggal_join') is-invalid @enderror" id="tanggal_join"
                                    placeholder="Tanggal Join" name="tanggal_join"
                                    value="{{ isset($param->tanggal_join) ? $param->tanggal_join : old('tanggal_join') }}"
                                    {{-- @isset($param->tanggal_join) readonly @endisset --}}
                                >
                                @error('tanggal_join')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col">
                                <label for="tanggal_akhir_kerja">Tanggal Akhir Kerja</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date"
                                    class="form-control @error('tanggal_akhir_kerja') is-invalid @enderror"
                                    id="tanggal_akhir_kerja" placeholder="Last Working Date" name="tanggal_akhir_kerja"
                                    value="{{ isset($param->tanggal_akhir_kerja) ? $param->tanggal_akhir_kerja : old('tanggal_akhir_kerja') }}">
                                @error('tanggal_akhir_kerja')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col">
                                <label for="tanggal_akhir_kontrak">Tanggal Akhir Kontrak</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date"
                                    class="form-control @error('tanggal_akhir_kontrak') is-invalid @enderror" id="tanggal_akhir_kontrak"
                                    placeholder="Tanggal AKhir Kontrak" name="tanggal_akhir_kontrak"
                                    value="{{ isset($param->tanggal_akhir_kontrak) ? $param->tanggal_akhir_kontrak : old('tanggal_akhir_kontrak') }}">
                                @error('tanggal_akhir_kontrak')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-6">
                                <label for="inputWorkLocation">Lokasi Kerja</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden"
                                    name="work_location_id"
                                    value="{{ isset($param->work_location_id) ? $param->work_location_id : old('work_location_id') }}">
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }}
                                    class="form-control @error('work_location_id') is-invalid @enderror"
                                    name="work_location_id" id="inputWorkLocation">


                                    @if (isset($param->work_location))
                                        @foreach ($param->work_location as $loc)
                                            <option value="{{ $loc->id }}"
                                                @if ((isset($param->work_location_id) ? $param->work_location_id : old('work_location_id')) == $loc->id)   selected @endif>
                                                {{ $loc->loc_code . ' - ' . $loc->loc_name }}</option>
                                        @endforeach
                                    @endif


                                </select>
                                @error('role')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-6">
                                <label for="keterangan">Keterangan</label>
                                <textarea {{ $data['page']['readonly'] ? 'disabled' : '' }}
                                    class="form-control @error('keterangan') is-invalid @enderror" id="keterangan" placeholder="Keterangan"
                                    name="keterangan">{{ isset($param->keterangan) ? $param->keterangan : old('keterangan') }} </textarea>
                                @error('keterangan')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-6">
                                <label for="employee_blood_type">Golongan Darah</label>
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }} autocomplete="off"
                                    class="form-control " name="employee_blood_type" data-id=""
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
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>





                        </div>

                        <div class="form-group row">
                            @if (@$param)
                                <div class="col-6">
                                    <label for="no_id_karyawan">No ID Karyawan</label>
                                    <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                        class="form-control @error('no_id_karyawan') is-invalid @enderror"
                                        id="no_id_karyawan" placeholder="No KTP" name="no_id_karyawan"
                                        value="{{ isset($param->no_id_karyawan) ? $param->no_id_karyawan : old('no_id_karyawan') }}"
                                        {{-- readonly --}}
                                    >
                                    @error('no_id_karyawan')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            @endif
                        </div>

                        @if ($data['page']['readonly'] == false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif
                        <a href="{{ route('master.employee.index') }}" class="btn btn-default">
                            Back
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>

<div class="content">
    @if(@$data['page']['logs'])
        @include('master::master.logs_view.list', [
            'title' => 'Log Karyawan',
            'logs' => $data['page']['logs'],
        ])
    @endif
</div>

@stop

@push('js')
    @if (isset($data['page']['js']))
        @include($data['page']['js'])
    @endif

    <script>
        $(document).ready(function() {
            var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');


            $('#job_position_id').select2({
                width: '100%',
                placeholder: 'Please select Job Position',
                ajax: {
                    url: "{!! url('api/getmaster_job_positionbyparams?set[id]=id&set[text][ - ]=position_code&set[text][]=position_name') !!}",
                    type: "get",
                    dataType: 'json',
                    delay: 5,
                    data: function(params) {
                        return {
                            _token: CSRF_TOKEN,
                            search: params.term
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

            //read
            let list_karyawan_read_premission = '{{ auth()->user()->can('list_karyawan_read') }}';
            if (list_karyawan_read_premission) {
                // $("input, textarea").attr("readonly", true);
                // $("select").attr("disabled", true);
            }

            // edit
            if (id.length > 0) {
                let list_karyawan_update_premission = '{{ auth()->user()->can('list_karyawan_update') }}';
                if (list_karyawan_update_premission) {
                    // $("#status_id, #hire_id,  #tanggal_join, #no_id_karyawan").attr("readonly", true);
                    $("#nama, #email, #no_ktp, #posisi, #tanggal_akhir_kerja, #tanggal_akhir_kontrak, #email_corporate, #keterangan")
                        // .attr("readonly", false);
                    // $("#inputWorkLocation").attr("disabled", false);
                }
            } else {
                // create
                let list_karyawan_create_premission = '{{ auth()->user()->can('list_karyawan_create') }}';
                if (list_karyawan_create_premission && id.length == 0) {
                    // $("input, textarea").attr("readonly", false);
                    // $("select").attr("readonly", false);
                    // $("select").attr("disabled", false);
                    $("#hire_id, #status_id, #work_location_id, #job_position_id").attr("disabled", false);
                }
            }

            let admin_permission = '{{ auth()->user()->can('admin') }}';
            if (admin_permission) {
                // $("input, textarea").attr("readonly", false);
                // $("select").attr("readonly", false);
                // $("select").attr("disabled", false);
            }

            // $("#no_id_karyawan").attr("readonly", true);

            if (tanggal_akhir_kerja != null && tanggal_akhir_kerja != '') {
                if (!admin_permission) {
                    // $("input, textarea").attr("readonly", true);
                    // $("select").attr("disabled", true);
                    $("#btn-save").remove();
                }
            }

            $('.select2-status').select2();
            $('.select2-hire').select2();

        });
    </script>

@endpush
