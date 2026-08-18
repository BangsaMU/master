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
        {{ $param ? 'Edit' : 'Create' }} Project2
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
                            <input type="hidden" name="id" value="{{ $param->id }}">
                        @endif

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="project_code">Project2 Code</label>
                            <input type="text" name="project_code" id="project_code" class="input w-full"
                                   value="{{ $param ? $param->project_code : old('project_code') }}" required>
                        </div>
                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="project_name">Project2 Name</label>
                            <input type="text" name="project_name" id="project_name" class="input w-full"
                                   value="{{ $param ? $param->project_name : old('project_name') }}" required>
                        </div>
                        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-border">
                            <button type="submit" class="button button--primary font-semibold">Submit</button>
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
