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
                            <label class="form-label" for="category_code">Category Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="category_code" id="category_code" class="form-control @error('category_code') is-invalid @enderror"
                                   value="{{ $param ? $param->category_code : old('category_code') }}" required>
                            @error('category_code') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="category_name">Category Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="category_name" id="category_name" class="form-control @error('category_name') is-invalid @enderror"
                                   value="{{ $param ? $param->category_name : old('category_name') }}" required>
                            @error('category_name') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="remark">Remark</label>
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
