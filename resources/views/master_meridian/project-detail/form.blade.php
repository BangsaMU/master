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
        {{ $param ? 'Edit' : 'Create' }} Project Detail
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
                    <form action="{{ $data['page']['store'] }}" method="POST" autocomplete="off" class="flex flex-col gap-4">
                        @csrf
                        @if ($param)
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id" value="{{ $param->id }}">
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="project_id" value="{{ $param->project_id }}">
                        @endif

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="project_id">Relate to Project</label>
                            <select @if(isset($param->project_id)) disabled @endif class="select w-full @error('project_id') border-danger @enderror" name="project_id" id="project_id">
                                @if(isset($param->project_id))
                                    <option value="{{ $param->project_id }}" selected>{{ $param->project_code . ' - ' . $param->project_name }}</option>
                                @endif
                            </select>
                            @error('project_id') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="project_code_client">Project Code Client</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="project_code_client" id="project_code_client" class="input w-full @error('project_code_client') border-danger @enderror"
                                   value="{{ $param ? $param->project_code_client : old('project_code_client') }}" required placeholder="Input your Project Code Client">
                            @error('project_code_client') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="project_name_client">Project Name Client</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="project_name_client" id="project_name_client" class="input w-full @error('project_name_client') border-danger @enderror"
                                   value="{{ $param ? $param->project_name_client : old('project_name_client') }}" required placeholder="Input your Project Name Client">
                            @error('project_name_client') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="company_id">Company</label>
                            <select {{ $data['page']['readonly'] ? 'disabled' : '' }} class="select w-full @error('company_id') border-danger @enderror" name="company_id" id="company_id">
                                @if(isset($param->company_id))
                                    <option value="{{ $param->company_id }}" selected>{{ $param->company_name }}</option>
                                @endif
                            </select>
                            @error('company_id') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-border">
                            @if ($data['page']['readonly'] == false)
                                <button type="submit" class="button button--primary font-semibold">Submit</button>
                            @endif
                            <a href="{{ Route::has('master.project-detail.index') ? route('master.project-detail.index') : '#' }}" class="button button--neutral button--outline">
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
                    <h3 class="card__title font-bold text-base m-0">Project Detail Summary</h3>
                </div>
                <div class="card__body p-6 flex flex-col gap-4">
                    <div class="field flex flex-col gap-1">
                        <label class="field__label text-sm font-medium text-foreground" for="project_code_disp">Project Code</label>
                        <input type="text" id="project_code_disp" class="input w-full"
                            value="{{ $param ? $param->project_code : old('project_code') }}" disabled>
                    </div>
                    <div class="field flex flex-col gap-1">
                        <label class="field__label text-sm font-medium text-foreground" for="project_name_disp">Project Name</label>
                        <input type="text" id="project_name_disp" class="input w-full"
                            value="{{ $param ? $param->project_name : old('project_name') }}" disabled>
                    </div>
                    <div class="field flex flex-col gap-1">
                        <label class="field__label text-sm font-medium text-foreground" for="internal_external">Project Type</label>
                        <input type="text" id="internal_external"
                            class="input w-full"
                            value="{{ $param && $param->internal_external === 'I' ? 'Internal' : ($param && $param->internal_external === 'E' ? 'External' : old('internal_external')) }}" disabled>
                    </div>
                    <div class="field flex flex-col gap-1">
                        <label class="field__label text-sm font-medium text-foreground" for="project_start_date">Start Date</label>
                        <input type="date" id="project_start_date" class="input w-full"
                            value="{{ $param ? $param->project_start_date : old('project_start_date') }}" disabled>
                    </div>
                    <div class="field flex flex-col gap-1">
                        <label class="field__label text-sm font-medium text-foreground" for="project_complete_date">Complete Date</label>
                        <input type="date" id="project_complete_date" class="input w-full"
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

        initializeSelect2('#project_id', "{!! url('api/getmaster_projectbyparams?set[id]=id&set[text][ - ]=project_code&set[text][]=project_name') !!}", 'project_code,project_name', 'Please select Project');
        initializeSelect2('#company_id', "{!! url('api/getmaster_companybyparams?set[id]=id&set[text][ - ]=company_code&set[text][]=company_name') !!}", 'company_code,company_name', 'Please select Company');
    </script>
@endpush
