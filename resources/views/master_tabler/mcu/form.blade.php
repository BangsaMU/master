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
                        @endif

                        <div class="form-group">
                            <label class="form-label" for="project_code">Vessel Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="project_code" id="project_code" class="form-control @error('project_code') is-invalid @enderror "
                                   value="{{ $param ? $param->project_code : old('project_code') }}" required>
                                   @error('project_code')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="project_name">Vessel Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="project_name" id="project_name" class="form-control @error('project_name') is-invalid @enderror "
                                   value="{{ $param ? $param->project_name : old('project_name') }}" required>
                                   @error('project_name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        @if ($data['page']['readonly'] == false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif
                        <a href="{{ route('master.mcu.index') }}" class="btn btn-default">
                            Back
                        </a>
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
