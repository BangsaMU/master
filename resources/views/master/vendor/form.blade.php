@extends('adminlte::page')

@section('title', @$data['page']['title'])

@section('content_header')
    <h1 class="m-0 text-dark">{{ $param ? 'Edit' : 'Create' }} {{ $data['page']['title'] }}</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-12 col-sm-8">
            <form action="{{ $data['page']['store'] }}" method="POST">
                @csrf
                @if ($param)
                    <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id" value="{{ $param->id }}">
                @endif
                <div class="card card-outline card-primary">
                    <div class="card-header font-weight-bold">
                        {{ $data['page']['title'] }} Form
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="vendor_code">Vendor Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_code" id="vendor_code" class="form-control"
                                    value="{{ $param ? $param->vendor_code : old('vendor_code') }}" required>
                            @error('vendor_code') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="vendor_description">Vendor Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_description" id="vendor_description" class="form-control"
                                    value="{{ $param ? $param->vendor_description : old('vendor_description') }}" required>
                            @error('vendor_description') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="vendor_address">Vendor Address</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_address" id="vendor_address" class="form-control"
                                    value="{{ $param ? $param->vendor_address : old('vendor_address') }}" required>
                            @error('vendor_address') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="vendor_phone">Vendor Phone</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_phone" id="vendor_phone" class="form-control"
                                    value="{{ $param ? $param->vendor_phone : old('vendor_phone') }}">
                            @error('vendor_phone') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="vendor_fax">Vendor Fax</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_fax" id="vendor_fax" class="form-control"
                                    value="{{ $param ? $param->vendor_fax : old('vendor_fax') }}">
                            @error('vendor_fax') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="vendor_email">Vendor Email</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_email" id="vendor_email" class="form-control"
                                    value="{{ $param ? $param->vendor_email : old('vendor_email') }}">
                            @error('vendor_email') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                <div class="card card-outline card-primary">
                    <div class="card-header font-weight-bold">
                        Vendor Contact
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="vendor_contact_name">Vendor Contact Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_contact_name" id="vendor_contact_name" class="form-control"
                                    value="{{ $param ? $param->vendor_contact_name : old('vendor_contact_name') }}">
                            @error('vendor_contact_name') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="vendor_contact_phone">Vendor Contact Phone</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_contact_phone" id="vendor_contact_phone" class="form-control"
                                    value="{{ $param ? $param->vendor_contact_phone : old('vendor_contact_phone') }}">
                            @error('vendor_contact_phone') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="vendor_contact_email">Vendor Contact Email</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_contact_email" id="vendor_contact_email" class="form-control"
                                    value="{{ $param ? $param->vendor_contact_email : old('vendor_contact_email') }}">
                            @error('vendor_contact_email') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="vendor_contact_fax">Vendor Contact Fax</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_contact_fax" id="vendor_contact_fax" class="form-control"
                                    value="{{ $param ? $param->vendor_contact_fax : old('vendor_contact_fax') }}">
                            @error('vendor_contact_fax') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        @if ($data['page']['readonly'] == false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif
                        <a href="{{route('master.vendor.index')}}" class="btn btn-default">
                            Back
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
@stop

@push('js')
    @if (isset($data['page']['js']))
        @include($data['page']['js'])
    @endif
@endpush
