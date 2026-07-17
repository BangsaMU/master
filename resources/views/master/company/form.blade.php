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
                                <label for="field_form_no">Form No</label>
                                <input type="text" class="form-control" id="field_form_no" required>
                            </div>
                            <div class="form-group mb-3">
                                <label for="field_rev_no">Rev No</label>
                                <input type="number" class="form-control" id="field_rev_no" required>
                            </div>
                            <div class="form-group mb-3">
                                <label for="field_issued_date">Issued Date</label>
                                <input type="date" class="form-control" id="field_issued_date" required>
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
            
            function loadKeyData(key) {
                if (!templateData[key]) {
                    templateData[key] = {
                        form_no: '',
                        rev_no: 1,
                        issued_date: '',
                        format_date: 'F,Y',
                        template_header: '1'
                    };
                }
                const data = templateData[key];
                document.getElementById('field_form_no').value = data.form_no || '';
                document.getElementById('field_rev_no').value = data.rev_no !== undefined ? data.rev_no : 1;
                document.getElementById('field_issued_date').value = data.issued_date || '';
                document.getElementById('field_format_date').value = data.format_date || '';
                document.getElementById('field_template_header').value = data.template_header || '1';
                
                updateHiddenInput();
            }
            
            function updateHiddenInput() {
                if (templateJsonInput) {
                    templateJsonInput.value = JSON.stringify(templateData);
                }
            }
            
            const fields = ['form_no', 'rev_no', 'issued_date', 'format_date', 'template_header'];
            fields.forEach(field => {
                const el = document.getElementById('field_' + field);
                if (el) {
                    const updateValue = function() {
                        const key = selectedKeySelect.value;
                        if (!templateData[key]) {
                            templateData[key] = {};
                        }
                        if (field === 'rev_no') {
                            templateData[key][field] = parseInt(el.value) || 0;
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
                    const newKey = prompt('Enter new App Code key (e.g. APP33):');
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
                            form_no: 'MEI-FLK-MTC-001',
                            rev_no: 1,
                            issued_date: '2026-07-09',
                            format_date: 'F,Y',
                            template_header: '1'
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
