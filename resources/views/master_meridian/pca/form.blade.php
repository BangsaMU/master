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
        {{ $param ? 'Edit' : 'Create' }} PCA
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
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id" value="{{ $param->id }}">
                        @endif

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="pca_code">PCA Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="pca_code" id="pca_code" class="input w-full @error('pca_code') border-danger @enderror"
                                   value="{{ $param ? $param->pca_code : old('pca_code') }}" required>
                            @error('pca_code')
                                <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="pca_name">PCA Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="pca_name" id="pca_name" class="input w-full @error('pca_name') border-danger @enderror"
                                   value="{{ $param ? $param->pca_name : old('pca_name') }}" required>
                            @error('pca_name')
                                <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-border">
                            @if ($data['page']['readonly']==false)
                                <button type="submit" class="button button--primary font-semibold">Submit</button>
                            @endif
                            <a href="{{ Route::has('master.pca.index') ? route('master.pca.index') : '#' }}" class="button button--neutral button--outline">
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
