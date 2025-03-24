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
                            <label for="category_code">Category Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="category_code" id="category_code" class="form-control"
                                   value="{{ $param ? $param->category_code : old('category_code') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="category_name">Category Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="category_name" id="category_name" class="form-control"
                                   value="{{ $param ? $param->category_name : old('category_name') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="remark">Remark</label>
                            <textarea {{ $data['page']['readonly'] ? 'readonly' : '' }} name="remark" id="remark" class="form-control">{{ $param ? $param->remark : old('remark') }}</textarea>
                        </div>
                        @if ($data['page']['readonly']==false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif
                        <a href="{{route('master.category.index')}}" class="btn btn-default">
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
