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
        {{ $param ? 'Edit' : 'Create' }} Company
    </h1>
    <p class="page__description text-sm text-muted-foreground mt-1">
        Manage details for {{ $data['page']['title'] }}
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
                    <form action="{{ $data['page']['store'] }}" method="POST" autocomplete="off" enctype="multipart/form-data" class="flex flex-col gap-4">
                        @csrf
                        @if ($param)
                            <input type="hidden" name="id" value="{{ $param->id }}">
                        @endif

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="company_code">Company Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="company_code" id="company_code" class="input w-full @error('company_code') border-danger @enderror" value="{{ $param ? $param->company_code : old('company_code') }}" required>
                            @error('company_code') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="company_name">Company Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="company_name" id="company_name" class="input w-full @error('company_name') border-danger @enderror" value="{{ $param ? $param->company_name : old('company_name') }}" required>
                            @error('company_name') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="company_short">Company Short</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="company_short" id="company_short" class="input w-full @error('company_short') border-danger @enderror" value="{{ $param ? $param->company_short : old('company_short') }}">
                            @error('company_short') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="company_attention">Company Attention</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="company_attention" id="company_attention" class="input w-full @error('company_attention') border-danger @enderror" value="{{ $param ? $param->company_attention : old('company_attention') }}">
                            @error('company_attention') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="company_address">Company Address</label>
                            <textarea {{ $data['page']['readonly'] ? 'readonly' : '' }} name="company_address" id="company_address" class="textarea w-full @error('company_address') border-danger @enderror" rows="3">{{ $param ? $param->company_address : old('company_address') }}</textarea>
                            @error('company_address') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="company_logo">Company Logo</label>
                            @if ($data['page']['readonly']==false)
                              <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="file" name="company_logo" id="company_logo" class="input w-full @error('company_logo') border-danger @enderror">
                              @error('company_logo') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                            @endif
                        </div>

                        <div class="field flex flex-col gap-1 preview-container @if ($param && $param->company_logo_url) block @else hidden @endif">
                            <label class="field__label text-sm font-medium text-foreground">Preview</label>
                            <div class="w-full p-2 border border-border rounded-md bg-surface-2 flex items-center justify-center">
                                <img id="image-preview" alt="Image Preview" class="max-w-full max-h-48 rounded border border-border"
                                     src="{{ $param ? $param->company_logo_url : '' }}"
                                     @if ($param && $param->company_logo_url)
                                         style="display: block;"
                                     @else
                                         style="display: none;"
                                     @endif>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-border">
                            @if ($data['page']['readonly']==false)
                                <button type="submit" class="button button--primary font-semibold">Submit</button>
                            @endif
                            <a href="{{ Route::has('master.company.index') ? route('master.company.index') : '#' }}" class="button button--neutral button--outline">
                                Back
                            </a>
                            @if (!empty($masterEditUrl))
                                <a href="{{ $masterEditUrl }}" target="_blank" rel="noopener" class="button button--warning">Edit di Master Data</a>
                            @endif
                        </div>
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
        <div class="col-span-12 lg:col-span-4">
            <div class="card">
                <div class="card__header p-4 border-b border-border flex items-center justify-between">
                    <h3 class="card__title font-bold text-base m-0">{{ $templateEditable ? 'Edit Template JSON' : 'Template JSON' }}</h3>
                </div>
                <div class="card__body p-6">
                    <form action="{{ route('master.company.update_template_json', $param->id) }}" method="POST" autocomplete="off" enctype="multipart/form-data" class="flex flex-col gap-4">
                        @csrf
                        <input type="hidden" name="template_json" id="template_json_input" value="{{ json_encode($templateData) }}">
                        <input type="hidden" name="logo_pdf_key" id="logo_pdf_key" value="">

                        @if (!$templateEditable)
                            <div class="p-3 rounded-md border border-border bg-surface-2 text-sm">
                                Template dan logo header PDF dikelola di aplikasi Master Data (pusat); di sini hanya baca.
                                @if (!empty($masterEditUrl))
                                    <a href="{{ $masterEditUrl }}" target="_blank" rel="noopener" class="font-semibold underline">Edit di Master Data</a>
                                @endif
                            </div>
                        @endif
                        @error('template_json') <div class="text-danger text-sm">{{ $message }}</div> @enderror
                        @error('logo_pdf_key') <div class="text-danger text-sm">{{ $message }}</div> @enderror
                        @foreach (array_keys($logoFields) as $logoField)
                            @error('logo_pdf_file.'.$logoField) <div class="text-danger text-sm">{{ $message }}</div> @enderror
                        @endforeach

                        @if (checkPermission('is_admin'))
                            <div class="field flex flex-col gap-1 mb-3">
                                <label class="field__label text-sm font-medium text-foreground" for="selected_key">Select App Code Key</label>
                                <div class="input-group">
                                    <select class="select w-full" id="selected_key">
                                        @foreach (array_keys($templateData) as $key)
                                            <option value="{{ $key }}" {{ $key == $appCode ? 'selected' : '' }}>{{ \Bangsamu\Master\Support\CompanyTemplate::appLabel((string) $key, $appNames) }}</option>
                                        @endforeach
                                    </select>
                                    @if ($templateEditable)
                                        <button type="button" class="button button--neutral button--outline button--sm ms-2" id="btn_add_key">Add Key</button>
                                        <button type="button" class="button button--info button--outline button--sm ms-1" id="btn_add_field">+ Add Field</button>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="field flex flex-col gap-1 mb-3">
                                <label class="field__label text-sm font-medium text-foreground">App Code Key</label>
                                <input type="text" class="input w-full" value="{{ \Bangsamu\Master\Support\CompanyTemplate::appLabel((string) $appCode, $appNames) }}" readonly>
                                <input type="hidden" id="selected_key" value="{{ $appCode }}">
                            </div>
                        @endif

                        <div id="template-empty-note" class="text-sm text-muted" style="display: none;">
                            Belum ada template untuk App Code Key ini.
                        </div>

                        <div id="dynamic-form-fields" class="flex flex-col gap-4">
                            @foreach (['form_no' => 'Form No', 'rev_no' => 'Rev No', 'issued_date' => 'Issued Date'] as $templateField => $templateLabel)
                                <div class="field flex flex-col gap-1">
                                    <div class="flex justify-between items-center mb-1">
                                        <label class="field__label text-sm font-medium text-foreground m-0">{{ $templateLabel }}</label>
                                        @if ($templateEditable)
                                            <button type="button" class="button button--sm button--neutral button--outline text-xs" id="btn_add_{{ $templateField }}">
                                                + Add {{ $templateLabel }}
                                            </button>
                                        @endif
                                    </div>
                                    <div id="{{ $templateField }}_container" class="flex flex-col gap-2"></div>
                                </div>
                            @endforeach
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="field_format_date">Format Date</label>
                                <input type="text" class="input w-full" id="field_format_date" required>
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="field_template_header">Template Header</label>
                                <input type="text" class="input w-full" id="field_template_header" required>
                            </div>
                        </div>

                        <div class="field flex flex-col gap-2" id="template-logo-pdf">
                            <label class="field__label text-sm font-medium text-foreground m-0">Logo Header PDF</label>
                            <small class="text-xs text-muted">Per App Code Key. Kosong = aplikasi memakai logo default-nya. Logo utama = Company Logo.</small>
                            @foreach ($logoFields as $logoField => $logoLabel)
                                <div class="p-2 rounded-md border border-border flex flex-col gap-1 template-logo" data-field="{{ $logoField }}">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm">{{ $logoLabel }}</span>
                                        @if ($templateEditable)
                                            <button type="button" class="button button--danger button--outline button--sm template-logo-remove" style="display: none;">Hapus</button>
                                        @endif
                                    </div>
                                    <img class="template-logo-preview" alt="{{ $logoLabel }}" style="display: none; max-height: 60px; max-width: 100%;">
                                    <small class="text-xs text-muted template-logo-empty">Belum diisi</small>
                                    @if ($templateEditable)
                                        <input type="file" name="logo_pdf_file[{{ $logoField }}]" class="input w-full template-logo-file" accept=".png,.jpg,.jpeg">
                                    @endif
                                </div>
                            @endforeach
                            @if ($templateEditable)
                                <small class="text-xs text-muted">PNG/JPG maks. 2 MB, tersimpan saat Save Template.</small>
                            @endif
                        </div>

                        @if ($templateEditable)
                            <div class="mt-4 pt-3 border-t border-border">
                                <button type="submit" class="button button--primary font-semibold w-full">Save Template</button>
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
        @include('master::partials.company-template-script', ['ui' => ['row' => 'flex items-center gap-2 mb-2', 'keyInput' => 'input', 'valueInput' => 'input flex-1', 'removeButton' => 'button button--danger button--outline button--sm', 'fieldGroup' => 'field flex flex-col gap-1', 'fieldHead' => 'flex justify-between items-center mb-1', 'label' => 'field__label text-sm font-medium text-foreground m-0', 'addButton' => 'button button--sm button--neutral button--outline text-xs', 'container' => 'flex flex-col gap-2', 'groupSelector' => '.field']])
    @endif
@endpush
