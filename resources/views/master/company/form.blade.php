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
                    <form action="{{ $data['page']['store'] }}" method="POST">
                        @csrf
                        @if ($param)
                            <input type="hidden" name="id" value="{{ $param->id }}">
                        @endif

                        <div class="form-group">
                            <label for="company_code">Company Code</label>
                            <input type="text" name="company_code" id="company_code" class="form-control"
                                   value="{{ $param ? $param->company_code : old('company_code') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="company_name">Company Name</label>
                            <input type="text" name="company_name" id="company_name" class="form-control"
                                   value="{{ $param ? $param->company_name : old('company_name') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="company_short">Company Short</label>
                            <input type="text" name="company_short" id="company_short" class="form-control"
                                   value="{{ $param ? $param->company_short : old('company_short') }}">
                        </div>
                        <div class="form-group">
                            <label for="company_attention">Company Attention</label>
                            <input type="text" name="company_attention" id="company_attention" class="form-control"
                                   value="{{ $param ? $param->company_attention : old('company_attention') }}">
                        </div>
                        <div class="form-group">
                            <label for="company_address">Company Address</label>
                            <textarea name="company_address" id="company_address" class="form-control" rows="3">{{ $param ? $param->company_address : old('company_address') }}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="company_logo">Company Logo</label>
                            <input type="file" name="company_logo" id="company_logo" class="form-control-file">
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
