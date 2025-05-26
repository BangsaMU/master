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
                            <label class="form-label" for="priority_code">Priority Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="priority_code" id="priority_code" class="form-control @error('priority_code') is-invalid @enderror "
                                   value="{{ $param ? $param->priority_code : old('priority_code') }}" required>
                                   @error('priority_code')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="priority_name">Priority Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="priority_name" id="priority_name" class="form-control @error('priority_name') is-invalid @enderror "
                                   value="{{ $param ? $param->priority_name : old('priority_name') }}" required>
                                   @error('priority_name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        @if ($data['page']['readonly'] == false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif
                        <a href="{{route('master.priority.index')}}" class="btn btn-default">
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
