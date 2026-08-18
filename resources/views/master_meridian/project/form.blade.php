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
        {{ $param ? 'Edit' : 'Create' }} Project
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
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id"
                                value="{{ $param->id }}">
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="project_code">Project Code</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="input w-full @error('project_code') border-danger @enderror" id="project_code"
                                    placeholder="Project Code" name="project_code"
                                    value="{{ $param->project_code ?? old('project_code') }}">
                                @error('project_code')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="project_name">Project Name</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="input w-full @error('project_name') border-danger @enderror" id="project_name"
                                    placeholder="Project Name" name="project_name"
                                    value="{{ $param->project_name ?? old('project_name') }}">
                                @error('project_name')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="project_start_date">Project Start Date</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date"
                                    class="input w-full datepicker @error('project_start_date') border-danger @enderror"
                                    id="project_start_date" placeholder="Project Start" name="project_start_date"
                                    value="{{ $param->project_start_date ?? now() }}">
                                @error('project_start_date')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="project_complete_date">Project Complete Date</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date"
                                    class="input w-full datepicker @error('project_complete_date') border-danger @enderror"
                                    id="project_complete_date" placeholder="Project Complete" name="project_complete_date"
                                    value="{{ $param->project_complete_date ?? now() }}">
                                @error('project_complete_date')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground">Project Type</label>
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }}
                                    class="select w-full @error('internal_external') border-danger @enderror"
                                    name="internal_external">
                                    <option value="I" {{ @$param->internal_external == 'I' ? 'selected' : '' }}>
                                        Internal</option>
                                    <option value="E" {{ @$param->internal_external == 'E' ? 'selected' : '' }}>
                                        External</option>
                                </select>
                                @error('internal_external')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="project_remarks">Project Remark</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="input w-full @error('project_remarks') border-danger @enderror" id="project_remarks"
                                    placeholder="Project Remark" name="project_remarks"
                                    value="{{ $param->project_remarks ?? old('project_remarks') }}">
                                @error('project_remarks')
                                    <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-border">
                            @if ($data['page']['readonly'] == false)
                                <button type="submit" class="button button--primary font-semibold">Submit</button>
                            @endif
                            <a href="{{ Route::has('master.project.index') ? route('master.project.index') : '#' }}" class="button button--neutral button--outline">
                                Back
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @isset($view_form_list)
        <div class="grid grid-cols-12 gap-4 mt-4">
            <div class="col-span-12 lg:col-span-8">
                <div class="card">
                    <div class="card__body p-6 flex flex-col gap-4">
                        @foreach ($view_form_list as $keyL => $formL)
                            @foreach ($formL as $keyf => $form)
                                @include('master::layouts.dashboard.view_form', [
                                    'form' => $form,
                                    'formdata' => (object) $view_form_listDetail[$keyL],
                                ])
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endisset
@stop

@push('js')
    @if (isset($data['page']['js']))
        @include($data['page']['js'])
    @endif
@endpush
