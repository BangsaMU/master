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
        {{ $param ? 'Edit' : 'Create' }} Item Group
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
                            <label class="field__label text-sm font-medium text-foreground" for="item_group_code">Item Group Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="item_group_code" id="item_group_code" class="input w-full @error('item_group_code') border-danger @enderror"
                                   value="{{ $param ? $param->item_group_code : old('item_group_code') }}">
                            @error('item_group_code') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="item_group_name">Item Group Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="item_group_name" id="item_group_name" class="input w-full @error('item_group_name') border-danger @enderror"
                                   value="{{ $param ? $param->item_group_name : old('item_group_name') }}">
                            @error('item_group_name') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="attributes">Item Group Attributes</label>
                            <div id="form-attributes" class="flex flex-col gap-2">
                                @error('item_group_attributes') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-border">
                            @if ($data['page']['readonly'] == false)
                                <button type="submit" class="button button--primary font-semibold">Submit</button>
                            @endif
                            <a href="{{ Route::has('master.item-group.index') ? route('master.item-group.index') : '#' }}" class="button button--neutral button--outline">
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

    <script>
        var index_global = 0;

        @if(@$param->id || old())
            var paramData = @json($param) || old();

            if (paramData['item_group_attributes'] == '{}' || paramData['item_group_attributes'] == '' || paramData['item_group_attributes'] == null) {
                appendInput('','item_group_attributes', '');
            } else {
                var item_group_attributes = JSON.parse(paramData.item_group_attributes);
                $('#form-attributes').empty();

                var i = 0;
                for (var attr in item_group_attributes) {
                    appendInput(index_global, 'item_group_attributes', attr);
                    i++;
                }
            }
        @else
            appendInput('','item_group_attributes', '');
        @endif

        function appendInput(index, id, value){
            if (index == '') {
                index = index_global;
            }
            if (value != '' && value != null) {
                value = formatString(value);
            }

            $("#form-attributes").append(`
                <div class="flex items-center gap-2 mb-2" id="row-input-${id}-${index}">
                    <div class="flex-1">
                        <input {{ $data['page']['readonly'] ? 'readonly' : '' }} class="input w-full" id="input-${id}" value="${value ?? ''}" name="${id}[]">
                    </div>
                </div>
            `);

            @if($data['page']['readonly']==false)
            if (index == 0) {
                $("#row-input-"+id+"-"+index).append(`
                    <div class="flex items-center justify-end">
                        <button type="button" class="button button--primary button--sm button--icon-only" id="btn-append-input-select2"
                            onclick="appendInput('','item_group_attributes', '')"
                        ><i class="fas fa-plus"></i></button>
                    </div>
                `);
            } else {
                $("#row-input-"+id+"-"+index).append(`
                    <div class="flex items-center justify-end" id="div-btn-remove-input-${index}">
                        <button type="button" class="button button--danger button--ghost button--icon-only button--sm" id="btn-append-input-select2"
                            onclick="removeInput('${id}', ${index})"><i class="fas fa-minus"></i></button>
                    </div>
                `);
            }
            @endif

            index_global++;
        }

        function removeInput(id, index){
            $('#row-input-'+id+'-'+index).remove();
        }

        function formatString(str) {
            return str
                .replace(/_/g, ' ')
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        }
    </script>
@endpush
