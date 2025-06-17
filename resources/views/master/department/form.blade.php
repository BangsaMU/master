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

                        <div class="form-group">
                            <label for="department_code">Department Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="department_code"
                                id="department_code" class="form-control @error('department_code') is-invalid @enderror"
                                value="{{ $param ? $param->department_code : old('department_code') }}" required>
                            @error('department_code')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="department_name">Department Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="department_name"
                                id="department_name" class="form-control @error('department_name') is-invalid @enderror"
                                value="{{ $param ? $param->department_name : old('department_name') }}" required>
                            @error('department_name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        @if ($data['page']['readonly'] == false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif

                        @php
                            $segments = request()->segments();

                            $segments = array_filter($segments, function ($segment) {
                                return !is_numeric($segment) && $segment !== 'edit';
                            });

                            $base_url = url(implode('/',$segments));
                        @endphp
                        <a href="{{  @$data['page']['base_url']??$base_url }}" class="btn btn-default">
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
