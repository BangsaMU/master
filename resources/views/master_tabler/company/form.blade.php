@php
    if (config('app.themes') == '_tabler') {
        // Cek apakah view "layouts.tabler" ada
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
    <h2 class="page-title">
        {{ $param ? 'Edit' : 'Create' }}
    </h2>
    <div class="text-muted mt-1">
        Manage details for {{ $data['page']['title'] }}
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-status-top bg-blue"></div>
                <div class="card-header font-weight-bold">
                    <span class="card-title">{{ $data['page']['title'] }} Form</span>
                </div>
                <div class="card-body">
                    <form action="{{ $data['page']['store'] }}" method="POST" autocomplete="off" enctype="multipart/form-data" class="space-y">
                        @csrf
                        @if ($param)
                            <input type="hidden" name="id" value="{{ $param->id }}">
                        @endif

                        <div class="form-group">
                            <label class="form-label" for="company_code">Company Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="company_code" id="company_code" class="form-control @error('company_code') is-invalid @enderror " value="{{ $param ? $param->company_code : old('company_code') }}" required>
                            @error('company_code') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="company_name">Company Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="company_name" id="company_name" class="form-control @error('company_name') is-invalid @enderror " value="{{ $param ? $param->company_name : old('company_name') }}" required>
                            @error('company_name') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="company_short">Company Short</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="company_short" id="company_short" class="form-control @error('company_short') is-invalid @enderror " value="{{ $param ? $param->company_short : old('company_short') }}">
                            @error('company_short') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="company_attention">Company Attention</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="company_attention" id="company_attention" class="form-control @error('company_attention') is-invalid @enderror " value="{{ $param ? $param->company_attention : old('company_attention') }}">
                            @error('company_attention') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="company_address">Company Address</label>
                            <textarea {{ $data['page']['readonly'] ? 'readonly' : '' }} name="company_address" id="company_address" class="form-control @error('company_address') is-invalid @enderror " rows="3">{{ $param ? $param->company_address : old('company_address') }}</textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="company_logo">Company Logo</label>
                            @if ($data['page']['readonly']==false)
                              <input {{ $data['page']['readonly'] ? 'readonly' : '' }} {{ $data['page']['readonly'] ? 'readonly' : '' }} type="file" name="company_logo" id="company_logo" accept=".png,.jpg,.jpeg" class="form-control-file @error('company_logo') is-invalid @enderror " >
                              @error('company_logo') <span class="text-danger">{{ $message }}</span> @enderror
                            @endif
                        </div>
                        <div class="form-group preview-container @if ($param && $param->company_logo_url) d-block @else d-none @endif">
                            <label class="form-label" for="">Preview</label>
                            <div class="w-100">
                                <img id="image-preview" alt="Image Preview" style="max-width: 100%; max-height: 200px; border: 2px solid #007BFF; border-radius: 5px;"
                                     src="{{ $param ? $param->company_logo_url : '' }}"
                                     @if ($param && $param->company_logo_url)
                                         style="display: block;"
                                     @else
                                         style="display: none;"
                                     @endif>
                            </div>
                        </div>
                        @if ($data['page']['readonly']==false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif
                        <a href="{{route('master.company.index')}}" class="btn btn-default">
                            Back
                        </a>
                        @if (!empty($masterEditUrl))
                            <a href="{{ $masterEditUrl }}" target="_blank" rel="noopener" class="btn btn-warning">Edit di Master Data</a>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        @if ($param)
        @php
            $templateEditable = $templateEditable ?? false;
            $appNames = $appNames ?? [];
            $logoFields = $logoFields ?? \Bangsamu\Master\Support\CompanyTemplate::LOGO_FIELDS;
        @endphp
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-status-top bg-blue"></div>
                <div class="card-header font-weight-bold">
                    <span class="card-title">{{ $templateEditable ? 'Edit Template JSON' : 'Template JSON' }}</span>
                </div>
                <div class="card-body">
                    <form action="{{ route('master.company.update_template_json', $param->id) }}" method="POST" autocomplete="off" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="template_json" id="template_json_input" value="{{ json_encode($templateData) }}">
                        <input type="hidden" name="logo_pdf_key" id="logo_pdf_key" value="">

                        @if (!$templateEditable)
                            <div class="alert alert-info py-2 small">
                                Template dan logo header PDF dikelola di aplikasi Master Data (pusat); di sini hanya baca.
                                @if (!empty($masterEditUrl))
                                    <a href="{{ $masterEditUrl }}" target="_blank" rel="noopener" class="alert-link">Edit di Master Data</a>
                                @endif
                            </div>
                        @endif
                        @error('template_json') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        @error('logo_pdf_key') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        @foreach (array_keys($logoFields) as $logoField)
                            @error('logo_pdf_file.'.$logoField) <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        @endforeach

                        @if (checkPermission('is_admin'))
                            <div class="form-group mb-3">
                                <label class="form-label" for="selected_key">Select App Code Key</label>
                                <div class="input-group">
                                    <select class="form-select" id="selected_key">
                                        @foreach (array_keys($templateData) as $key)
                                            <option value="{{ $key }}" {{ $key == $appCode ? 'selected' : '' }}>{{ \Bangsamu\Master\Support\CompanyTemplate::appLabel((string) $key, $appNames) }}</option>
                                        @endforeach
                                    </select>
                                    @if ($templateEditable)
                                        <button type="button" class="btn btn-outline-secondary" id="btn_add_key">Add Key</button>
                                        <button type="button" class="btn btn-outline-info ms-1" id="btn_add_field">+ Add Field</button>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="form-group mb-3">
                                <label class="form-label">App Code Key</label>
                                <input type="text" class="form-control" value="{{ \Bangsamu\Master\Support\CompanyTemplate::appLabel((string) $appCode, $appNames) }}" readonly>
                                <input type="hidden" id="selected_key" value="{{ $appCode }}">
                            </div>
                        @endif

                        <div id="template-empty-note" class="text-muted small mb-3" style="display: none;">
                            Belum ada template untuk App Code Key ini.
                        </div>

                        <div id="dynamic-form-fields">
                            @foreach (['form_no' => 'Form No', 'rev_no' => 'Rev No', 'issued_date' => 'Issued Date'] as $templateField => $templateLabel)
                                <div class="form-group mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label mb-0">{{ $templateLabel }}</label>
                                        @if ($templateEditable)
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn_add_{{ $templateField }}">
                                                + Add {{ $templateLabel }}
                                            </button>
                                        @endif
                                    </div>
                                    <div id="{{ $templateField }}_container" class="space-y-2"></div>
                                </div>
                            @endforeach
                            <div class="form-group mb-3">
                                <label class="form-label" for="field_format_date">Format Date</label>
                                <input type="text" class="form-control" id="field_format_date" required>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label" for="field_template_header">Template Header</label>
                                <input type="text" class="form-control" id="field_template_header" required>
                            </div>
                        </div>

                        <div class="form-group mb-3" id="template-logo-pdf">
                            <label class="form-label mb-0">Logo Header PDF</label>
                            <small class="text-muted d-block mb-2">Per App Code Key. Kosong = aplikasi memakai logo default-nya. Logo utama = Company Logo.</small>
                            @foreach ($logoFields as $logoField => $logoLabel)
                                <div class="border rounded p-2 mb-2 template-logo" data-field="{{ $logoField }}">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span>{{ $logoLabel }}</span>
                                        @if ($templateEditable)
                                            <button type="button" class="btn btn-sm btn-outline-danger template-logo-remove" style="display: none;">Hapus</button>
                                        @endif
                                    </div>
                                    <img class="template-logo-preview" alt="{{ $logoLabel }}" style="display: none; max-height: 60px; max-width: 100%;">
                                    <small class="text-muted template-logo-empty">Belum diisi</small>
                                    @if ($templateEditable)
                                        <input type="file" name="logo_pdf_file[{{ $logoField }}]" class="form-control form-control-sm mt-1 template-logo-file" accept=".png,.jpg,.jpeg">
                                    @endif
                                </div>
                            @endforeach
                            @if ($templateEditable)
                                <small class="text-muted d-block">PNG/JPG maks. 2 MB, tersimpan saat Save Template.</small>
                            @endif
                        </div>

                        @if ($templateEditable)
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">Save Template</button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
        @endif
    </div>
@stop

@push('js')
    @if (isset($data['page']['js']))
        @include($data['page']['js'])
    @endif

    @if ($param)
        @include('master::partials.company-template-script', ['ui' => ['row' => 'input-group mb-2', 'keyInput' => 'form-control', 'valueInput' => 'form-control', 'removeButton' => 'btn btn-outline-danger', 'fieldGroup' => 'form-group mb-3', 'fieldHead' => 'd-flex justify-content-between align-items-center mb-2', 'label' => 'form-label mb-0', 'addButton' => 'btn btn-sm btn-outline-primary', 'container' => 'space-y-2', 'groupSelector' => '.form-group']])
    @endif
@endpush
