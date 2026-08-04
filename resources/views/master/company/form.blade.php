@extends('adminlte::page')

@section('title', @$data['page']['title'])

@section('content_header')
    <h1 class="m-0 text-dark">{{ $param ? 'Edit' : 'Create' }} {{ $data['page']['title'] }}</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card card-outline card-primary">
                <div class="card-header font-weight-bold">
                    {{ $data['page']['title'] }} Form
                </div>
                <div class="card-body">
                    <form action="{{ $data['page']['store'] }}" method="POST" autocomplete="off" enctype="multipart/form-data">
                        @csrf
                        @if ($param)
                            <input type="hidden" name="id" value="{{ $param->id }}">
                        @endif

                        <div class="form-group">
                            <label for="company_code">Company Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="company_code" id="company_code" class="form-control @error('company_code') is-invalid @enderror " value="{{ $param ? $param->company_code : old('company_code') }}" required>
                            @error('company_code') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="company_name">Company Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="company_name" id="company_name" class="form-control @error('company_name') is-invalid @enderror " value="{{ $param ? $param->company_name : old('company_name') }}" required>
                            @error('company_name') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="company_short">Company Short</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="company_short" id="company_short" class="form-control @error('company_short') is-invalid @enderror " value="{{ $param ? $param->company_short : old('company_short') }}">
                            @error('company_short') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="company_attention">Company Attention</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="company_attention" id="company_attention" class="form-control @error('company_attention') is-invalid @enderror " value="{{ $param ? $param->company_attention : old('company_attention') }}">
                            @error('company_attention') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="company_address">Company Address</label>
                            <textarea {{ $data['page']['readonly'] ? 'readonly' : '' }} name="company_address" id="company_address" class="form-control @error('company_address') is-invalid @enderror " rows="3">{{ $param ? $param->company_address : old('company_address') }}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="company_logo">Company Logo</label>
                            @if ($data['page']['readonly']==false)
                              <input {{ $data['page']['readonly'] ? 'readonly' : '' }} {{ $data['page']['readonly'] ? 'readonly' : '' }} type="file" name="company_logo" id="company_logo" class="form-control-file @error('company_logo') is-invalid @enderror " >
                              @error('company_logo') <span class="text-danger">{{ $message }}</span> @enderror
                            @endif
                        </div>
                        <div class="form-group preview-container @if ($param && $param->company_logo_url) d-block @else d-none @endif">
                            <label for="">Preview</label>
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
                    </form>
                </div>
            </div>
        </div>

        @if ($param)
        <div class="col-12 col-lg-4">
            <div class="card card-outline card-primary">
                <div class="card-header font-weight-bold">
                    Edit Template JSON
                </div>
                <div class="card-body">
                    <form action="{{ route('master.company.update_template_json', $param->id) }}" method="POST" autocomplete="off">
                        @csrf
                        <input type="hidden" name="template_json" id="template_json_input" value="{{ json_encode($templateData) }}">

                        @if (checkPermission('is_admin'))
                            <div class="form-group mb-3">
                                <label for="selected_key">Select App Code Key</label>
                                <div class="input-group">
                                    <select class="form-control" id="selected_key">
                                        @foreach (array_keys($templateData) as $key)
                                            <option value="{{ $key }}" {{ $key == $appCode ? 'selected' : '' }}>{{ $key }}</option>
                                        @endforeach
                                    </select>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="btn_add_key">Add Key</button>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="form-group mb-3">
                                <label>App Code Key</label>
                                <input type="text" class="form-control" value="{{ $appCode }}" readonly>
                                <input type="hidden" id="selected_key" value="{{ $appCode }}">
                            </div>
                        @endif

                        <div id="dynamic-form-fields">
                            <div class="form-group mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="mb-0">Form No</label>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn_add_form_no">
                                        + Add Form No
                                    </button>
                                </div>
                                <div id="form_no_container"></div>
                            </div>
                            <div class="form-group mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="mb-0">Rev No</label>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn_add_rev_no">
                                        + Add Rev No
                                    </button>
                                </div>
                                <div id="rev_no_container"></div>
                            </div>
                            <div class="form-group mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="mb-0">Issued Date</label>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn_add_issued_date">
                                        + Add Issued Date
                                    </button>
                                </div>
                                <div id="issued_date_container"></div>
                            </div>
                            <div class="form-group mb-3">
                                <label for="field_format_date">Format Date</label>
                                <input type="text" class="form-control" id="field_format_date" required>
                            </div>
                            <div class="form-group mb-3">
                                <label for="field_template_header">Template Header</label>
                                <input type="text" class="form-control" id="field_template_header" required>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Save Template</button>
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
                        if (fieldName === 'rev_no' && /^\d+$/.test(firstVal)) {
                            templateData[key][fieldName] = parseInt(firstVal);
                        } else {
                            templateData[key][fieldName] = firstVal;
                        }
                        updateHiddenInput();
                        return;
                    }
                }

                const obj = {};
                rows.forEach(row => {
                    const k = row.querySelector(`.${fieldName}-key`).value.trim();
                    const v = row.querySelector(`.${fieldName}-value`).value.trim();
                    if (k !== '') {
                        if (fieldName === 'rev_no' && /^\d+$/.test(v)) {
                            obj[k] = parseInt(v);
                        } else {
                            obj[k] = v;
                        }
                    }
                });
                templateData[key][fieldName] = obj;
                updateHiddenInput();
            }

            function createFieldRow(containerId, subKey = '', subVal = '', fieldName = 'form_no') {
                const container = document.getElementById(containerId);
                if (!container) return;

                let placeholderVal = 'Value (e.g. MEI-FLK-MTC-001)';
                if (fieldName === 'rev_no') {
                    placeholderVal = 'Rev No (e.g. 1 or 1-spb)';
                } else if (fieldName === 'issued_date') {
                    placeholderVal = 'Issued Date (e.g. 2026-07-09)';
                }

                const row = document.createElement('div');
                row.className = `input-group mb-2 ${fieldName}-row`;
                row.innerHTML = `
                    <input type="text" class="form-control ${fieldName}-key" style="max-width: 35%;" placeholder="Key (e.g. spb)" value="${subKey}">
                    <input type="text" class="form-control ${fieldName}-value" placeholder="${placeholderVal}" value="${subVal}">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-danger btn-remove-row" title="Remove">&times;</button>
                    </div>
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
                
                dynamicFields.forEach(fieldName => {
                    renderFieldContainer(fieldName, data[fieldName]);
                });

                document.getElementById('field_format_date').value = data.format_date || '';
                document.getElementById('field_template_header').value = data.template_header !== undefined ? data.template_header : 1;

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
        });
    </script>
    @endif
@endpush
