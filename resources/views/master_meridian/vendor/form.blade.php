@php
    $theme = config('app.themes');
    if ($theme == '_meridian') {
        $themeLayout = view()->exists('layouts.meridian')
            ? 'layouts.meridian'
            : 'master::layouts.meridian';
    } elseif ($theme == '_tabler') {
        $themeLayout = view()->exists('layouts.tabler')
            ? 'layouts.tabler'
            : 'master::layouts.tabler';
    } else {
        $themeLayout = 'adminlte::page';
    }
@endphp
@extends($themeLayout)

@section('title', @$data['page']['title'])

@section('header')
    <h1 class="page__title font-bold text-2xl m-0">
        {{ $param ? 'Edit' : 'Create' }} Vendor
    </h1>
    <p class="page__description text-sm text-muted-foreground mt-1">
        Manage details for {{ $data['page']['title'] }}
    </p>
@endsection

@section('content')
    <form action="{{ $data['page']['store'] }}" method="POST" autocomplete="off">
        @csrf
        @if ($param)
            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id" value="{{ $param->id }}">
        @endif

        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12 lg:col-span-8">
                <div class="card">
                    <div class="card__header p-4 border-b border-border flex items-center justify-between">
                        <h3 class="card__title font-bold text-base m-0">{{ $data['page']['title'] }} Form</h3>
                    </div>
                    <div class="card__body p-6 flex flex-col gap-4">
                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="vendor_code">Vendor Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_code" id="vendor_code" class="input w-full @error('vendor_code') border-danger @enderror"
                                    value="{{ $param ? $param->vendor_code : old('vendor_code') }}" required>
                            @error('vendor_code') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="vendor_description">Vendor Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_description" id="vendor_description" class="input w-full @error('vendor_description') border-danger @enderror"
                                    value="{{ $param ? $param->vendor_description : old('vendor_description') }}" required>
                            @error('vendor_description') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="vendor_address">Vendor Address</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_address" id="vendor_address" class="input w-full @error('vendor_address') border-danger @enderror"
                                    value="{{ $param ? $param->vendor_address : old('vendor_address') }}" required>
                            @error('vendor_address') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="vendor_phone">Vendor Phone</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_phone" id="vendor_phone" class="input w-full @error('vendor_phone') border-danger @enderror"
                                        value="{{ $param ? @$param->vendor_phone : old('vendor_phone') }}">
                                @error('vendor_phone') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="vendor_fax">Vendor Fax</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_fax" id="vendor_fax" class="input w-full @error('vendor_fax') border-danger @enderror"
                                        value="{{ $param ? @$param->vendor_fax : old('vendor_fax') }}">
                                @error('vendor_fax') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="vendor_email">Vendor Email</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_email" id="vendor_email" class="input w-full @error('vendor_email') border-danger @enderror"
                                        value="{{ $param ? @$param->vendor_email : old('vendor_email') }}">
                                @error('vendor_email') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4">
                <div class="card">
                    <div class="card__header p-4 border-b border-border flex items-center justify-between">
                        <h3 class="card__title font-bold text-base m-0">Vendor Contact</h3>
                    </div>
                    <div class="card__body p-6 flex flex-col gap-4">
                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="vendor_contact_name">Contact Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_contact_name" id="vendor_contact_name" class="input w-full @error('vendor_contact_name') border-danger @enderror"
                                    value="{{ $param ? $param->vendor_contact_name : old('vendor_contact_name') }}">
                            @error('vendor_contact_name') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="vendor_contact_phone">Contact Phone</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_contact_phone" id="vendor_contact_phone" class="input w-full @error('vendor_contact_phone') border-danger @enderror"
                                    value="{{ $param ? $param->vendor_contact_phone : old('vendor_contact_phone') }}">
                            @error('vendor_contact_phone') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="vendor_contact_email">Contact Email</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_contact_email" id="vendor_contact_email" class="input w-full @error('vendor_contact_email') border-danger @enderror"
                                    value="{{ $param ? $param->vendor_contact_email : old('vendor_contact_email') }}">
                            @error('vendor_contact_email') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="vendor_contact_fax">Contact Fax</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="vendor_contact_fax" id="vendor_contact_fax" class="input w-full @error('vendor_contact_fax') border-danger @enderror"
                                    value="{{ $param ? $param->vendor_contact_fax : old('vendor_contact_fax') }}">
                            @error('vendor_contact_fax') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-border">
                            @if ($data['page']['readonly'] == false)
                                <button type="submit" class="button button--primary font-semibold">Submit</button>
                            @endif
                            <a href="{{ Route::has('master.vendor.index') ? route('master.vendor.index') : '#' }}" class="button button--neutral button--outline">
                                Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@stop

@push('js')
    @if (isset($data['page']['js']))
        @include($data['page']['js'])
    @endif
@endpush
