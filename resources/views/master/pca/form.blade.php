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
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id" value="{{ $param->id }}">
                        @endif

                        <div class="form-group">
                            <label for="pca_code">PCA Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="pca_code" id="pca_code" class="form-control @error('pca_code') is-invalid @enderror"
                                   value="{{ $param ? $param->pca_code : old('pca_code') }}" required>
                                   @error('pca_code')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="pca_name">PCA Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="pca_name" id="pca_name" class="form-control @error('pca_name') is-invalid @enderror"
                                   value="{{ $param ? $param->pca_name : old('pca_name') }}" required>
                                   @error('pca_name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        @if ($data['page']['readonly']==false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif
                        <a href="{{ route('master.pca.index') }}" class="btn btn-default">
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
