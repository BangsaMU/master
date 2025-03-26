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
                            <label for="uom_code">UOM Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="uom_code"
                                id="uom_code" class="form-control @error('uom_code') is-invalid @enderror "
                                value="{{ $param ? $param->uom_code : old('uom_code') }}" required>
                            @error('uom_code')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="uom_name">UOM Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="uom_name"
                                id="uom_name" class="form-control @error('uom_name') is-invalid @enderror "
                                value="{{ $param ? $param->uom_name : old('uom_name') }}" required>
                            @error('uom_name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        @if ($data['page']['readonly']==false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif
                        <a href="{{ route('master.uom.index') }}" class="btn btn-default">
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
