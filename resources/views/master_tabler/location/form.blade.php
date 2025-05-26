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
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id"
                                value="{{ $param->id }}">
                        @endif

                        <div class="form-group">
                            <label class="form-label" for="loc_code">Location Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="loc_code"
                                id="loc_code" class="form-control @error('loc_code') is-invalid @enderror "
                                value="{{ $param ? $param->loc_code : old('loc_code') }}" required>
                            @error('loc_code')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="loc_name">Location Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="loc_name"
                                id="loc_name" class="form-control @error('loc_name') is-invalid @enderror "
                                value="{{ $param ? $param->loc_name : old('loc_name') }}" required>
                            @error('loc_name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="group_type">Group</label>
                            <select {{ $data['page']['readonly'] ? 'readonly' : '' }} name="group_type" id="group_type"
                                class="form-select @error('group_type') is-invalid @enderror " required>
                                <option value="">Pilih Tipe Grup</option>
                                <option value="office"
                                    {{ ($param && $param->group_type == 'office') || old('group_type') == 'office' ? 'selected' : '' }}>
                                    Office</option>
                                <option value="warehouse"
                                    {{ ($param && $param->group_type == 'warehouse') || old('group_type') == 'warehouse' ? 'selected' : '' }}>
                                    Warehouse</option>
                                <option value="vendor"
                                    {{ ($param && $param->group_type == 'vendor') || old('group_type') == 'vendor' ? 'selected' : '' }}>
                                    Vendor</option>
                            </select>
                            @error('group_type')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        @if ($data['page']['readonly'] == false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif
                        <a href="{{ route('master.location.index') }}" class="btn btn-default">
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
