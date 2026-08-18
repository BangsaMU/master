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
        {{ $param ? 'Edit' : 'Create' }} Item Code
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
                    <form action="{{ $data['page']['store'] }}" method="POST" id="form-item-code" class="flex flex-col gap-4">
                        @csrf
                        @if ($param)
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id" value="{{ $param->id }}">
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="item_code">Item Code</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="item_code" id="item_code" class="input w-full @error('item_code') border-danger @enderror"
                                        value="{{ $param ? $param->item_code : old('item_code') }}" placeholder="Input your Item Code">
                                @error('item_code') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="item_name">Item Name</label>
                                <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="item_name" id="item_name" class="input w-full @error('item_name') border-danger @enderror"
                                        value="{{ $param ? $param->item_name : old('item_name') }}" placeholder="Input Item Name">
                                @error('item_name') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="field flex flex-col gap-1">
                            <label class="field__label text-sm font-medium text-foreground" for="remarks">Remarks</label>
                            <textarea 
                                {{ $data['page']['readonly'] ? 'readonly' : '' }} 
                                name="remarks" 
                                id="remarks" 
                                class="textarea w-full @error('remarks') border-danger @enderror" 
                                rows="3" 
                                placeholder="Input remarks here">{{ $param ? $param->remarks : old('remarks') }}</textarea>
                            @error('remarks')
                                <span class="field__error text-xs text-danger mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="uom">Unit of Measurement</label>
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }} class="select w-full @error('uom_id') border-danger @enderror" name="uom_id" id="uom">
                                    @if(isset($param->uom_id))
                                        <option value="{{ $param->uom_id }}" selected>{{ $param->uom_name }}</option>
                                    @endif
                                </select>
                                @error('uom_id') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="pca">PCA</label>
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }} class="select w-full @error('pca_id') border-danger @enderror" name="pca_id" id="pca">
                                    @if(isset($param->pca_id))
                                        <option value="{{ $param->pca_id }}" selected>{{ $param->pca_name }}</option>
                                    @endif
                                </select>
                                @error('pca_id') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="category">Category</label>
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }} class="select w-full @error('category_id') border-danger @enderror" name="category_id" id="category">
                                    @if(isset($param->category_id))
                                        <option value="{{ $param->category_id }}" selected>{{ $param->category_name }}</option>
                                    @endif
                                </select>
                                @error('category_id') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                            </div>
                            <div class="field flex flex-col gap-1">
                                <label class="field__label text-sm font-medium text-foreground" for="item_group">Item Group</label>
                                <select {{ $data['page']['readonly'] ? 'disabled' : '' }} class="select w-full @error('group_id') border-danger @enderror" name="group_id" id="item_group">
                                    @if(isset($param->group_id))
                                        <option value="{{ $param->group_id }}" selected>{{ $param->item_group_name }}</option>
                                    @endif
                                </select>
                                @error('group_id') <span class="field__error text-xs text-danger mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div id="form-attributes" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>

                        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-border">
                            @if ($data['page']['readonly'] == false)
                                <button type="submit" class="button button--primary font-semibold">Submit</button>
                            @endif
                            <a href="{{ Route::has('master.item-code.index') ? route('master.item-code.index') : '#' }}" class="button button--neutral button--outline">
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
        const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

        // Common Select2 Configuration
        function initializeSelect2(elementId, url, searchParam, placeholder, selectedValue) {
            $(elementId).select2({
                width: '100%',
                placeholder: placeholder,
                ajax: {
                    url: url,
                    type: "get",
                    dataType: 'json',
                    delay: 5,
                    data: function(params) {
                        return {
                            _token: CSRF_TOKEN,
                            ["search["+searchParam+"]"]: params.term
                        };
                    },
                    processResults: function(response) {
                        return { results: response };
                    },
                    cache: true
                }
            });

            if (selectedValue != '' && selectedValue != null) {
                $.ajax({
                    url: url + '&where[id]=' + selectedValue,
                    success: function (result) {
                        result = result[0];

                        var data = {
                            id: result.id,
                            text: result.text
                        };

                        var newOption = new Option(data.text, data.id, false, false);
                        $(elementId).append(newOption).trigger('change');

                        var event = $.Event('select2:select', { params: { data: { id: data.id, text: data.text } } });
                        $(elementId).trigger(event);
                    }
                });
            }
        }

        // Initialize Select2 Fields with old values
        initializeSelect2('#uom', "{!! url('api/getmaster_uombyparams?set[id]=id&set[text]=uom_name') !!}", 'uom_name', 'Please select UoM', "{{ old('uom_id', isset($param->uom_id) ? $param->uom_id : '') }}");
        initializeSelect2('#pca', "{!! url('api/getmaster_pcabyparams?set[id]=id&set[text]=pca_name') !!}", 'pca_name', 'Please select PCA', "{{ old('pca_id', isset($param->pca_id) ? $param->pca_id : '') }}");
        initializeSelect2('#category', "{!! url('api/getmaster_categorybyparams?set[id]=id&set[text]=category_name') !!}", 'category_name', 'Please select Category', "{{ old('category_id', isset($param->category_id) ? $param->category_id : '') }}");
        initializeSelect2('#item_group', "{!! url('api/getmaster_item_groupbyparams?set[id]=id&set[text]=item_group_name') !!}", 'item_group_name', 'Please select Item Group', "{{ old('group_id', isset($param->group_id) ? $param->group_id : '') }}");

        // Handle attributes input logic
        $('#item_group').on('select2:select', function(e) {
            var selectedData = e.params.data;
            $('#form-attributes').empty();

            $.ajax({
                url: "{!! url('api/getmaster_item_groupbyparams?set[field][]=item_group_attributes&where[id]="+selectedData.id+"') !!}",
                type: "GET",
                dataType: 'json',
                data: {
                    _token: CSRF_TOKEN,
                },
                success: function(response) {
                    response = response[0];
                    var jsonObject = JSON.parse(response.item_group_attributes);

                    var i = 0;
                    for (var key in jsonObject) {
                        addInput(key, i);
                        i++;
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                }
            });
        });

        function addInput(key, index){
            var oldValue = @json(old());
            var data = @json($param);

            var defaultValue = '';
            var attributes = data ? JSON.parse(data.attributes) : {};
            if (data && Object.keys(attributes).length > 0) {
                defaultValue = attributes[key] ?? '';
            } else if (oldValue.length !== 0 && oldValue['attributes'][index] != null) {
                defaultValue = oldValue['attributes'][index];
            }

            $('#form-attributes').append(`
                <div class="field flex flex-col gap-1">
                    <label class="field__label text-sm font-medium text-foreground" for="${key}">${formatString(key)}</label>
                    <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="attributes[]" id="${key}" class="input w-full" value="${defaultValue}" placeholder="Input your ${formatString(key)}">
                </div>
            `);
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
