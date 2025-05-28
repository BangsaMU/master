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
                            <label for="loc_code">Location Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="loc_code"
                                id="loc_code" class="form-control @error('loc_code') is-invalid @enderror "
                                value="{{ $param ? $param->loc_code : old('loc_code') }}" required>
                            @error('loc_code')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="loc_name">Location Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="loc_name"
                                id="loc_name" class="form-control @error('loc_name') is-invalid @enderror "
                                value="{{ $param ? $param->loc_name : old('loc_name') }}" required>
                            @error('loc_name')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="group_type">Group</label>
                            <select {{ $data['page']['readonly'] ? 'readonly' : '' }} name="group_type" id="group_type"
                                class="form-control @error('group_type') is-invalid @enderror " required>
                                <option value="">Pilih Tipe Grup</option>

                                @foreach ($data['page']['list_group_type'] as $group_type )
                                    <option value="{{$group_type}}"
                                        {{ ($param && $param->group_type == $group_type) || old('group_type') == $group_type ? 'selected' : '' }}>
                                        {{$group_type}}
                                    </option>
                                @endforeach

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
