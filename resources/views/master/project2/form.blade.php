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
                            <input type="hidden" name="id" value="{{ $param->id }}">
                        @endif

                        <div class="form-group">
                            <label for="project_code">Project2 Code</label>
                            <input type="text" name="project_code" id="project_code" class="form-control"
                                   value="{{ $param ? $param->project_code : old('project_code') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="project_name">Project2 Name</label>
                            <input type="text" name="project_name" id="project_name" class="form-control"
                                   value="{{ $param ? $param->project_name : old('project_name') }}" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@stop

@push('js')
    @if (isset($data['page']['js']))
        @include($data['page']['js'])
    @endif
@endpush
