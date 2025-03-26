@extends('adminlte::page')

@section('title', @$data['page']['title'])

@section('content_header')
    <h1 class="m-0 text-dark">{{ $param ? 'Edit' : 'Create' }} {{ $data['page']['title'] }}</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-12 col-sm-8">
            <div class="card card-outline card-primary">
                <div class="card-header font-weight-bold">
                    {{ $data['page']['title'] }} Form
                </div>
                <div class="card-body">
                    <form action="{{ $data['page']['store'] }}" method="POST" autocomplete="off">
                        @csrf
                        @if ($param)
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id"
                                value="{{ $param->id }}">
                        @endif



                        <div class="form-group row">
                            <div class="col">
                                <label>Project Code</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="form-control @error('project_code') is-invalid @enderror" id="project_code"
                                    placeholder="Project Code" name="project_code"
                                    value="{{ $param->project_code ?? old('project_code') }}">
                                @error('project_code')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col">
                                <label>Project Name</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="form-control @error('project_name') is-invalid @enderror" id="project_name"
                                    placeholder="Project Name" name="project_name"
                                    value="{{ $param->project_name ?? old('project_name') }}">
                                @error('project_name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-6 ">
                                <label>Project Start Date</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date"
                                    class="form-control datepicker @error('project_start_date') is-invalid @enderror"
                                    id="project_start_date" placeholder="Project Start" name="project_start_date"
                                    value="{{ $param->project_start_date ?? now() }}">
                                @error('project_start_date')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-6 ">
                                <label>Project Complete Date</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="date"
                                    class="form-control datepicker @error('project_complete_date') is-invalid @enderror"
                                    id="project_complete_date" placeholder="Project Complete" name="project_complete_date"
                                    value="{{ $param->project_complete_date ?? now() }}">
                                @error('project_complete_date')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-6">
                                <label>Project Type</label>
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
                        </div>


                        <div class="form-group row">
                            <div class="col">
                                <label>Project Remark</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text"
                                    class="form-control @error('project_remarks') is-invalid @enderror" id="project_remarks"
                                    placeholder="Project Code" name="project_remarks"
                                    value="{{ $param->project_remarks ?? old('project_remarks') }}">
                                @error('project_remarks')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        {{--
                        <div class="form-group">
                            <label for="project_code">Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="project_code"
                                id="project_code" class="form-control"
                                value="{{ $param ? $param->project_code : old('project_code') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="project_name">Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="project_name"
                                id="project_name" class="form-control"
                                value="{{ $param ? $param->project_name : old('project_name') }}" required>
                        </div> --}}
                        @if ($data['page']['readonly'] == false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif
                        <a href="{{ route('master.project.index') }}" class="btn btn-default">
                            Back
                        </a>
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
