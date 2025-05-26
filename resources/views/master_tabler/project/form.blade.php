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
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id"
                                value="{{ $param->id }}">
                        @endif

                        <div class="row row-cols-2 g-2">
                            <div>
                                <label class="form-label">Project Code</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="form-control @error('project_code') is-invalid @enderror" id="project_code"
                                    placeholder="Project Code" name="project_code"
                                    value="{{ $param->project_code ?? old('project_code') }}">
                                @error('project_code')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Project Name</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="form-control @error('project_name') is-invalid @enderror" id="project_name"
                                    placeholder="Project Name" name="project_name"
                                    value="{{ $param->project_name ?? old('project_name') }}">
                                @error('project_name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="row row-cols-2 g-2">
                            <div class="col-6 ">
                                <label class="form-label">Project Start Date</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date"
                                    class="form-control datepicker @error('project_start_date') is-invalid @enderror"
                                    id="project_start_date" placeholder="Project Start" name="project_start_date"
                                    value="{{ $param->project_start_date ?? now() }}">
                                @error('project_start_date')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-6 ">
                                <label class="form-label">Project Complete Date</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date"
                                    class="form-control datepicker @error('project_complete_date') is-invalid @enderror"
                                    id="project_complete_date" placeholder="Project Complete" name="project_complete_date"
                                    value="{{ $param->project_complete_date ?? now() }}">
                                @error('project_complete_date')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="row row-cols-2 g-2">
                            <div class="col-6">
                                <label class="form-label">Project Type</label>
                                <select {{ $data['page']['readonly'] ? 'readonly' : '' }}
                                    class="form-control @error('internal_external') is-invalid @enderror"
                                    name="internal_external">
                                    <option value="I" {{ @$param->internal_external == 'I' ? 'selected' : '' }}>
                                        Internal</option>
                                    <option value="E" {{ @$param->internal_external == 'E' ? 'selected' : '' }}>
                                        External</option>
                                </select>
                                @error('internal_external')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="form-label">Project Remark</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="form-control @error('project_remarks') is-invalid @enderror" id="project_remarks"
                                    placeholder="Project Code" name="project_remarks"
                                    value="{{ $param->project_remarks ?? old('project_remarks') }}">
                                @error('project_remarks')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="text-end">
                            <a href="{{ route('master.project.index') }}" class="btn btn-default">
                                Back
                            </a>
                            @if ($data['page']['readonly'] == false)
                                <button type="submit" class="btn btn-primary">Submit</button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @isset($view_form_list)
        <div class="card-body row">
            @foreach ($view_form_list as $keyL => $formL)
                {{-- {{dd($formdata,$view_form_listDetail[0])}} --}}
                {{-- {{dd($formL)}} --}}
                @foreach ($formL as $keyf => $form)
                    @include('master::layouts.dashboard.view_form', [
                        'form' => $form,
                        'formdata' => (object) $view_form_listDetail[$keyL],
                    ])
                @endforeach
                <div class="col-12"></div>
            @endforeach
        </div>
    @endisset

@stop

@push('js')
    @if (isset($data['page']['js']))
        @include($data['page']['js'])
    @endif
@endpush
