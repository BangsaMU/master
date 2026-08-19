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
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($param)
        <div class="col-span-12 lg:col-span-4">
            <div class="card">
                <div class="card__header p-4 border-b border-border flex items-center justify-between">
                    <h3 class="card__title font-bold text-base m-0">Edit Template JSON</h3>
                </div>
                <div class="card__body p-6">
                    <form action="{{ route('master.company.update_template_json', $param->id) }}" method="POST" autocomplete="off" class="flex flex-col gap-4">
                        @csrf
                        <input type="hidden" name="template_json" id="template_json_input" value="{{ json_encode($templateData) }}">

                        @if (checkPermission('is_admin'))
                            <div class="field flex flex-col gap-1 mb-3">
                                <label class="field__label text-sm font-medium text-foreground" for="selected_key">Select App Code Key</label>
                                <div class="input-group">
                                    <select class="select w-full" id="selected_key">
                                        @foreach (array_keys($templateData) as $key)
                                            <option value="{{ $key }}" {{ $key == $appCode ? 'selected' : '' }}>{{ $key }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="button button--neutral button--outline button--sm ms-2" id="btn_add_key">Add Key</button>
                                    <button type="button" class="button button--info button--outline button--sm ms-1" id="btn_add_field">+ Add Field</button>
                                </div>
                            </div>
                        @else
                            <div class="field flex flex-col gap-1 mb-3">
                                <label class="field__label text-sm font-medium text-foreground">App Code Key</label>
                                <input type="text" class="input w-full" value="{{ $appCode }}" readonly>
                                <input type="hidden" id="selected_key" value="{{ $appCode }}">
                            </div>
                        @endif

                        <div id="dynamic-form-fields" class="flex flex-col gap-4">
                            <div class="field flex flex-col gap-1">
                                <div class="flex justify-between items-center mb-1">
                                    <label class="field__label text-sm font-medium text-foreground m-0">Form No</label>
                                    <button type="button" class="button button--sm button--neutral button--outline text-xs" id="btn_add_form_no">
                                        + Add Form No
                                    </button>
                                </div>
                                <div id="form_no_container" class="flex flex-col gap-2"></div>
                            </div>
                            <div class="field flex flex-col gap-1">
                                <div class="flex justify-between items-center mb-1">
                                    <label class="field__label text-sm font-medium text-foreground m-0">Rev No</label>
                                    <button type="button" class="button button--sm button--neutral button--outline text-xs" id="btn_add_rev_no">
                                        + Add Rev No
                                    </button>
                                </div>
                                <div id="rev_no_container" class="flex flex-col gap-2"></div>
                            </div>
                            <div class="field flex flex-col gap-1">
                                <div class="flex justify-between items-center mb-1">
                                    <label class="field__label text-sm font-medium text-foreground m-0">Issued Date</label>
                                    <button type="button" class="button button--sm button--neutral button--outline text-xs" id="btn_add_issued_date">
                                        + Add Issued Date
                                    </button>
                                </div>
                                <div id="issued_date_container" class="flex flex-col gap-2"></div>
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="field_format_date">Format Date</label>
                                <input type="text" class="input w-full" id="field_format_date" required>
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="field_template_header">Template Header</label>
                                <input type="text" class="input w-full" id="field_template_header" required>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-border">
                            <button type="submit" class="button button--primary font-semibold w-full">Save Template</button>
                        </div>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const templateData = @json($templateData);
            const selectedKeySelect = document.getElementById('selected_key');
            const templateJsonInput = document.getElementById('template_json_input');
            const dynamicFields = ['form_no', 'rev_no', 'issued_date'];

            function updateHiddenInput() {
                if (templateJsonInput) {
                    templateJsonInput.value = JSON.stringify(templateData);
                }
            }

            function syncFieldData(fieldName) {
                const key = selectedKeySelect.value;
                if (!key) return;
                if (!templateData[key]) {
                    templateData[key] = {};
                }

                const container = document.getElementById(`${fieldName}_container`);
                if (!container) return;

                const rows = container.querySelectorAll(`.${fieldName}-row`);
                
                if (rows.length === 0) {
                    templateData[key][fieldName] = "";
                    updateHiddenInput();
                    return;
                }

                if (rows.length === 1) {
                    const firstKey = rows[0].querySelector(`.${fieldName}-key`).value.trim();
                    const firstVal = rows[0].querySelector(`.${fieldName}-value`).value.trim();
                    if (firstKey === '') {
                        templateData[key][fieldName] = firstVal;
                        updateHiddenInput();
                        return;
                    }
                }

                const obj = {};
                rows.forEach(row => {
                    const k = row.querySelector(`.${fieldName}-key`).value.trim();
                    const v = row.querySelector(`.${fieldName}-value`).value.trim();
                    if (k !== '') {
                        obj[k] = v;
                    }
                });
                templateData[key][fieldName] = obj;
                updateHiddenInput();
            }

            function createFieldRow(containerId, subKey = '', subVal = '', fieldName = 'form_no') {
                const container = document.getElementById(containerId);
                if (!container) return;

                let labelText = fieldName.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                let placeholderVal = `${labelText} (e.g. value)`;

                const row = document.createElement('div');
                row.className = `flex items-center gap-2 mb-2 ${fieldName}-row`;
                row.innerHTML = `
                    <input type="text" class="input ${fieldName}-key" style="max-width: 35%;" placeholder="Key (e.g. spb)" value="${subKey}">
                    <input type="text" class="input flex-1 ${fieldName}-value" placeholder="${placeholderVal}" value="${subVal}">
                    <button type="button" class="button button--danger button--outline button--sm btn-remove-row" title="Remove">&times;</button>
                `;

                const keyInput = row.querySelector(`.${fieldName}-key`);
                const valInput = row.querySelector(`.${fieldName}-value`);
                const btnRemove = row.querySelector('.btn-remove-row');

                keyInput.addEventListener('input', () => syncFieldData(fieldName));
                valInput.addEventListener('input', () => syncFieldData(fieldName));
                btnRemove.addEventListener('click', function() {
                    row.remove();
                    syncFieldData(fieldName);
                });

                container.appendChild(row);
            }

            function renderFieldContainer(fieldName, fieldValue) {
                const containerId = `${fieldName}_container`;
                const container = document.getElementById(containerId);
                if (!container) return;
                container.innerHTML = '';

                if (typeof fieldValue === 'string' || typeof fieldValue === 'number') {
                    createFieldRow(containerId, '', fieldValue, fieldName);
                } else if (typeof fieldValue === 'object' && fieldValue !== null && !Array.isArray(fieldValue)) {
                    const keys = Object.keys(fieldValue);
                    if (keys.length === 0) {
                        createFieldRow(containerId, '', '', fieldName);
                    } else {
                        keys.forEach(k => {
                            createFieldRow(containerId, k, fieldValue[k], fieldName);
                        });
                    }
                } else {
                    createFieldRow(containerId, '', '', fieldName);
                }
            }

            function ensureFieldContainer(fieldName) {
                let container = document.getElementById(`${fieldName}_container`);
                if (container) return container;

                const dynamicFieldsWrapper = document.getElementById('dynamic-form-fields');
                if (!dynamicFieldsWrapper) return null;

                const labelText = fieldName.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

                const fieldGroup = document.createElement('div');
                fieldGroup.className = 'field flex flex-col gap-1 custom-dynamic-field-group';
                fieldGroup.id = `field_group_${fieldName}`;

                fieldGroup.innerHTML = `
                    <div class="flex justify-between items-center mb-1">
                        <label class="field__label text-sm font-medium text-foreground m-0">${labelText}</label>
                        <button type="button" class="button button--sm button--neutral button--outline text-xs" id="btn_add_${fieldName}">
                            + Add ${labelText}
                        </button>
                    </div>
                    <div id="${fieldName}_container" class="flex flex-col gap-2"></div>
                `;

                const formatDateEl = document.getElementById('field_format_date');
                const formatDateGroup = formatDateEl ? formatDateEl.closest('.field') : null;

                if (formatDateGroup && formatDateGroup.parentNode === dynamicFieldsWrapper) {
                    dynamicFieldsWrapper.insertBefore(fieldGroup, formatDateGroup);
                } else {
                    dynamicFieldsWrapper.appendChild(fieldGroup);
                }

                const btnAdd = fieldGroup.querySelector(`#btn_add_${fieldName}`);
                if (btnAdd) {
                    btnAdd.addEventListener('click', function() {
                        createFieldRow(`${fieldName}_container`, '', '', fieldName);
                        syncFieldData(fieldName);
                    });
                }

                return document.getElementById(`${fieldName}_container`);
            }

            function loadKeyData(key) {
                if (!templateData[key]) {
                    templateData[key] = {
                        form_no: {
                            spb: 'MEI-FLK-SPB-001',
                            spj: 'MEI-FLK-SPJ-001',
                            spa: 'MEI-FLK-SPA-001'
                        },
                        rev_no: {
                            spb: '1-spb',
                            spj: '1-spj',
                            spa: '1-spa'
                        },
                        issued_date: {
                            spb: '2026-07-09',
                            spj: '2026-07-10',
                            spa: '2026-07-11'
                        },
                        format_date: 'F,Y',
                        template_header: 1
                    };
                }
                const data = templateData[key];
                
                document.querySelectorAll('.custom-dynamic-field-group').forEach(el => el.remove());

                const allKeys = Object.keys(data);
                allKeys.forEach(fieldName => {
                    if (fieldName === 'format_date') {
                        const el = document.getElementById('field_format_date');
                        if (el) el.value = data.format_date || '';
                    } else if (fieldName === 'template_header') {
                        const el = document.getElementById('field_template_header');
                        if (el) el.value = data.template_header !== undefined ? data.template_header : 1;
                    } else {
                        ensureFieldContainer(fieldName);
                        renderFieldContainer(fieldName, data[fieldName]);
                    }
                });

                updateHiddenInput();
            }

            dynamicFields.forEach(fieldName => {
                const btnAdd = document.getElementById(`btn_add_${fieldName}`);
                if (btnAdd) {
                    btnAdd.addEventListener('click', function() {
                        createFieldRow(`${fieldName}_container`, '', '', fieldName);
                        syncFieldData(fieldName);
                    });
                }
            });

            const scalarFields = ['format_date', 'template_header'];
            scalarFields.forEach(field => {
                const el = document.getElementById('field_' + field);
                if (el) {
                    const updateValue = function() {
                        const key = selectedKeySelect.value;
                        if (!templateData[key]) {
                            templateData[key] = {};
                        }
                        if (field === 'template_header') {
                            templateData[key][field] = /^\d+$/.test(el.value.trim()) ? parseInt(el.value.trim()) : el.value.trim();
                        } else {
                            templateData[key][field] = el.value;
                        }
                        updateHiddenInput();
                    };
                    el.addEventListener('input', updateValue);
                    el.addEventListener('change', updateValue);
                }
            });

            if (selectedKeySelect) {
                selectedKeySelect.addEventListener('change', function() {
                    loadKeyData(this.value);
                });

                loadKeyData(selectedKeySelect.value);
            }

            const btnAddKey = document.getElementById('btn_add_key');
            if (btnAddKey) {
                btnAddKey.addEventListener('click', function() {
                    const newKey = prompt('Enter new App Code key (e.g. APP09):');
                    if (newKey) {
                        const sanitizedKey = newKey.trim().toUpperCase();
                        if (sanitizedKey === '') return;

                        if (templateData[sanitizedKey]) {
                            alert('Key already exists!');
                            selectedKeySelect.value = sanitizedKey;
                            loadKeyData(sanitizedKey);
                            return;
                        }

                        templateData[sanitizedKey] = {
                            form_no: {
                                spb: 'MEI-FLK-SPB-001',
                                spj: 'MEI-FLK-SPJ-001',
                                spa: 'MEI-FLK-SPA-001'
                            },
                            rev_no: {
                                spb: '1-spb',
                                spj: '1-spj',
                                spa: '1-spa'
                            },
                            issued_date: {
                                spb: '2026-07-09',
                                spj: '2026-07-10',
                                spa: '2026-07-11'
                            },
                            format_date: 'F,Y',
                            template_header: 1
                        };

                        const opt = document.createElement('option');
                        opt.value = sanitizedKey;
                        opt.innerHTML = sanitizedKey;
                        selectedKeySelect.appendChild(opt);

                        selectedKeySelect.value = sanitizedKey;
                        loadKeyData(sanitizedKey);
                    }
                });
            }

            const btnAddField = document.getElementById('btn_add_field');
            if (btnAddField) {
                btnAddField.addEventListener('click', function() {
                    const newFieldName = prompt('Enter new field name (e.g. header, footer, remarks):');
                    if (newFieldName) {
                        const sanitizedField = newFieldName.trim().toLowerCase().replace(/\s+/g, '_');
                        if (sanitizedField === '') return;

                        const key = selectedKeySelect.value;
                        if (!templateData[key]) {
                            templateData[key] = {};
                        }

                        if (templateData[key][sanitizedField] !== undefined) {
                            alert('Field already exists!');
                            return;
                        }

                        templateData[key][sanitizedField] = "";
                        loadKeyData(key);
                    }
                });
            }
        });
    </script>
    @endif
@endpush
