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
        {{ $param ? 'Edit' : 'Create' }} Location
    </h1>
    <p class="page__description text-sm text-muted-foreground mt-1">
        Manage details for {{ $data['page']['title'] }}
    </p>
@endsection

@section('content')
    <div class="grid grid-cols-12 gap-4">
        <div class="col-span-12 lg:col-span-8">
            <div class="card">
                <div class="card__header p-4 border-b border-border flex items-center justify-between">
                    <h3 class="card__title font-bold text-base m-0">{{ $data['page']['title'] }} Form</h3>
                </div>
                <div class="card__body p-6">
                    <form action="{{ $data['page']['store'] }}" method="POST" autocomplete="off" class="flex flex-col gap-4">
                        @csrf
                        @if ($param)
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id"
                                value="{{ $param->id }}">
                        @endif

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="loc_code">Location Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="loc_code"
                                id="loc_code" class="input w-full @error('loc_code') border-danger @enderror"
                                value="{{ $param ? $param->loc_code : old('loc_code') }}" required>
                            @error('loc_code')
                                <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="loc_name">Location Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="loc_name"
                                id="loc_name" class="input w-full @error('loc_name') border-danger @enderror"
                                value="{{ $param ? $param->loc_name : old('loc_name') }}" required>
                            @error('loc_name')
                                <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="group_type">Group</label>
                            <select {{ $data['page']['readonly'] ? 'disabled' : '' }} name="group_type" id="group_type"
                                class="select w-full @error('group_type') border-danger @enderror" required>
                                <option value="">Pilih Tipe Grup.</option>
                                @foreach ($data['page']['list_group_type'] as $list_group_type)
                                    <option value="{{ $list_group_type }}"
                                        {{ ($param && $param->group_type == $list_group_type) || old('group_type') == $list_group_type ? 'selected' : '' }}>
                                        {{ ucfirst($list_group_type) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('group_type')
                                <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-border">
                            @if ($data['page']['readonly'] == false)
                                <button type="submit" class="button button--primary font-semibold">Submit</button>
                            @endif
                            <a href="{{ Route::has('master.location.index') ? route('master.location.index') : '#' }}" class="button button--neutral button--outline">
                                Back
                            </a>
                        </div>
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
