@extends('layouts.tabler')

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
        <div class="col-12 col-sm-8">
            <div class="card">
                <div class="card-status-top bg-blue"></div>
                <div class="card-header font-weight-bold">
                    <span class="card-title">{{ $data['page']['title'] }} Form</span>
                </div>
                <div class="card-body">
                    <form action="{{ $data['page']['store'] }}" method="POST" autocomplete="off" class="space-y">
                        @csrf
                        @if ($param)
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id" value="{{ $param->id }}">
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="project_id" value="{{ $param->project_id }}">
                        @endif

                        <div class="form-group">
                            <label class="form-label" for="project_id">Relate to Project</label>
                            <select @if(isset($param->project_id)) disabled @endif class="form-control @error('project_id') is-invalid @enderror" name="project_id" id="project_id">
                                @if(isset($param->project_id))
                                    <option value="{{ $param->project_id }}" selected>{{ $param->project_code . ' - ' . $param->project_name }}</option>
                                @endif
                            </select>
                            @error('project_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="project_code_client">Project Code Client</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="project_code_client" id="project_code_client" class="form-control @error('project_code_client') is-invalid @enderror"
                                   value="{{ $param ? $param->project_code_client : old('project_code_client') }}" required placeholder="Input your Project Code Client">

                            @error('project_code_client') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="project_name_client">Project Name Client</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="project_name_client" id="project_name_client" class="form-control @error('project_name_client') is-invalid @enderror"
                                   value="{{ $param ? $param->project_name_client : old('project_name_client') }}" required placeholder="Input your Project Name Client">

                            @error('project_name_client') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="company_id">Company</label>
                            <select {{ $data['page']['readonly'] ? 'disabled' : '' }} class="form-control @error('company_id') is-invalid @enderror" name="company_id" id="company_id">
                                @if(isset($param->company_id))
                                    <option value="{{ $param->company_id }}" selected>{{ $param->company_name }}</option>
                                @endif
                            </select>
                            @error('company_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        @if ($data['page']['readonly'] == false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif
                            <a href="{{route('master.project-detail.index')}}" class="btn btn-default">
                                Back
                            </a>
                    </form>
                </div>
            </div>
        </div>

        @if ($param)
        <div class="col-sm-4">
            <div class="card">
                <div class="card-status-top bg-blue"></div>
                <div class="card-header font-weight-bold">
                    Project Detail
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label" for="project_code">Project Code</label>
                        <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="project_code" id="project_code" class="form-control"
                            value="{{ $param ? $param->project_code : old('project_code') }}" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="project_name">Project Name</label>
                        <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="project_name" id="project_name" class="form-control"
                            value="{{ $param ? $param->project_name : old('project_name') }}" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="internal_external">Project Type</label>
                        <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                            class="form-control @error('internal_external') is-invalid @enderror"
                            value="{{ $param && $param->internal_external === 'I' ? 'Internal' : ($param && $param->internal_external === 'E' ? 'External' : old('internal_external')) }}" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="project_start_date">Start Date</label>
                        <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date" name="project_start_date" id="project_start_date" class="form-control"
                            value="{{ $param ? $param->project_start_date : old('project_start_date') }}" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="project_complete_date">Complete Date</label>
                        <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date" name="project_complete_date" id="project_complete_date" class="form-control"
                            value="{{ $param ? $param->project_complete_date : old('project_complete_date') }}" disabled>
                    </div>

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

    <script>
        const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

        // Common Select2 Configuration
        function initializeSelect2(elementId, url, searchParam, placeholder) {
            $(elementId).select2({
                width: '100%',
                placeholder: placeholder,
                ajax: {
                url: url,
                type: "get",
                dataType: 'json',
                delay: 5,
                data: function(params) {
                    let data = {
                        _token: CSRF_TOKEN
                    };

                    const searchParamsArray = searchParam.split(',');

                    // Mengisi parameter pencarian
                    searchParamsArray.forEach(param => {
                        data[`search[${param}][]`] = params.term;
                    });

                    return data;
                },
                processResults: function(response) {
                    return { results: response };
                },
                cache: true
                }
            });
        }

        // Initialize Select2 Fields
        initializeSelect2('#project_id', "{!! url('api/getmaster_projectbyparams?set[id]=id&set[text][ - ]=project_code&set[text][]=project_name') !!}", 'project_code,project_name', 'Please select Project');
        initializeSelect2('#company_id', "{!! url('api/getmaster_companybyparams?set[id]=id&set[text][ - ]=company_code&set[text][]=company_name') !!}", 'company_code,company_name', 'Please select Company');
    </script>
@endpush
