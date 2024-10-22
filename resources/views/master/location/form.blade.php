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
                            <label for="loc_code">Location Code</label>
                            <input type="text" name="loc_code" id="loc_code" class="form-control"
                                   value="{{ $param ? $param->loc_code : old('loc_code') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="loc_name">Location Name</label>
                            <input type="text" name="loc_name" id="loc_name" class="form-control"
                                   value="{{ $param ? $param->loc_name : old('loc_name') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="group_type">Group</label>
                            <select name="group_type" id="group_type" class="form-control" required>
                                <option value="">Pilih Tipe Grup</option>
                                <option value="office" {{ ($param && $param->group_type == 'office') || old('group_type') == 'office' ? 'selected' : '' }}>Office</option>
                                <option value="warehouse" {{ ($param && $param->group_type == 'warehouse') || old('group_type') == 'warehouse' ? 'selected' : '' }}>Warehouse</option>
                                <option value="vendor" {{ ($param && $param->group_type == 'vendor') || old('group_type') == 'vendor' ? 'selected' : '' }}>Vendor</option>
                            </select>
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
