@extends('adminlte::page')

@section('title', @$data['page']['title'])

@section('content_header')
    <h1 class="m-0 text-dark">{{ $param ? 'Edit' : 'Create' }} {{ $data['page']['title'] }}</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-12 col-sm-8">
            <div class="card card-outline card-primary">
                <div class="card-header font-weight-bold fw-bold">
                    {{ $data['page']['title'] }} Form
                </div>
                <div class="card-body">
                    <form action="{{ $data['page']['store'] }}" method="POST" id="form-item-code">
                        @csrf
                        @if ($param)
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id" value="{{ $param->id }}">
                        @endif

                        <div class="form-group mb-3">
                            <label for="item_code">Item Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="item_code" id="item_code" class="form-control"
                                   value="{{ $param ? $param->item_code : old('item_code') }}" placeholder="Input your Item Code">
                            @error('item_code') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group mb-3">
                            <label for="item_name">Description</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="item_name" id="item_name" class="form-control"
                                   value="{{ $param ? $param->item_name : old('item_name') }}" placeholder="Input your Description">
                            @error('item_name') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="row row-cols-12 g-2">
                            <div>
                                <label class="form-label" for="remarks">remarks</label>
                                <textarea 
                                    {{ $data['page']['readonly'] ? 'readonly' : '' }} 
                                    name="remarks" 
                                    id="remarks" 
                                    class="form-control" 
                                    rows="3" 
                                    placeholder="input remarks here">{{ $param ? $param->remarks : old('remarks') }}</textarea>
                                @error('remarks')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="uom">Unit of Measurement</label>
                            <select {{ $data['page']['readonly'] ? 'disabled' : '' }} class="form-control @error('uom_id') is-invalid @enderror" name="uom_id" id="uom">
                                @if(isset($param->uom_id))
                                    <option value="{{ $param->uom_id }}" selected>{{ $param->uom_text ?? $param->uom_name }}</option>
                                @endif
                            </select>
                            @error('uom_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group mb-3">
                            <label for="pca">PCA</label>
                            <select {{ $data['page']['readonly'] ? 'disabled' : '' }} class="form-control @error('pca_id') is-invalid @enderror" name="pca_id" id="pca">
                                <option value=""></option>
                                @if(isset($param->pca_id))
                                    <option value="{{ $param->pca_id }}" selected>{{ $param->pca_text ?? $param->pca_name }}</option>
                                @endif
                            </select>
                            @error('pca_id') <span class="text-danger">{{$message}}</span> @enderror
                        </div>
                        <div class="form-group mb-3">
                            <label for="category">Category</label>
                            <select {{ $data['page']['readonly'] ? 'disabled' : '' }} class="form-control @error('category_id') is-invalid @enderror" name="category_id" id="category">
                                <option value=""></option>
                                @if(isset($param->category_id))
                                    <option value="{{ $param->category_id }}" selected>{{ $param->category_text ?? $param->category_name }}</option>
                                @endif
                            </select>
                            @error('category_id') <span class="text-danger">{{$message}}</span> @enderror
                        </div>
                        <div class="form-group mb-3">
                            <label for="group">Item Group</label>
                            <select {{ $data['page']['readonly'] ? 'disabled' : '' }} class="form-control @error('group_id') is-invalid @enderror" name="group_id" id="item_group">
                                @if(isset($param->group_id))
                                    <option value="{{ $param->group_id }}" selected>{{ $param->item_group_text ?? $param->item_group_name }}</option>
                                @endif
                            </select>
                            @error('group_id') <span class="text-danger">{{$message}}</span> @enderror
                        </div>
                        <div id="form-attributes"></div>

                        @include('master::partials.master-owner', ['table' => 'master_item_code', 'param' => $param, 'readonly' => $data['page']['readonly']])

                        @if ($data['page']['readonly'] == false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif
                        <a href="{{route('master.item-code.index')}}" class="btn btn-default">
                            Back
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Log perubahan data (termasuk dari import) --}}
    @if (isset($data['page']['logs']) && count($data['page']['logs']) > 0)
        @include('master::master.logs_view.list', ['title' => 'Log Item Code', 'logs' => $data['page']['logs']])
    @endif

    {{-- Kode huruf besar + kunci unik (readonly non-admin, konfirmasi admin) --}}
    @include('master::components.unique-key-guard', ['entity' => 'item-code', 'param' => $param ?? null])
@stop

@push('js')
    @if (isset($data['page']['js']))
        @include($data['page']['js'])
    @endif

    {{-- Tab ke dropdown langsung membuka kotak cari, Tab di kotak cari pindah ke field berikut --}}
    @include('master::partials.select2-keyboard')

    <script>
        // var (bukan const): layout aplikasi bisa sudah mendeklarasikan CSRF_TOKEN global; const = SyntaxError.
        var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

        // Common Select2 Configuration
        // searchParam boleh array kolom: dicari dengan OR (search[kolom][]=kata), mis. kode atau nama.
        function initializeSelect2(elementId, url, searchParam, placeholder, selectedValue, allowClear) {
            var searchColumns = [].concat(searchParam);

            $(elementId).select2({
                width: '100%',
                placeholder: placeholder,
                allowClear: allowClear === true,
                ajax: {
                    url: url,
                    type: "get",
                    dataType: 'json',
                    delay: 5,
                    data: function(params) {
                        var query = { _token: CSRF_TOKEN };
                        searchColumns.forEach(function (column) {
                            query["search["+column+"][]"] = params.term;
                        });
                        return query;
                    },
                    processResults: function(response) {
                        return { results: response };
                    },
                    cache: true
                }
            });

            if (selectedValue != '' && selectedValue != null) {
                $.ajax({
                    url: url + '&where[id]=' + encodeURIComponent(selectedValue),
                    success: function (result) {
                        result = result[0];
                        if (!result) {
                            return;
                        }

                        var data = {
                            id: result.id,
                            text: result.text
                        };

                        // opsi dari server (edit) cukup diganti teksnya; nilai old() sesudah validasi gagal ditambahkan lalu dipilih
                        // (dulu Option(..., false, false): PCA/Category kembali kosong walau old() terisi)
                        var $option = $(elementId).find('option').filter(function () { return String(this.value) === String(data.id); });
                        if ($option.length) {
                            $option.first().text(data.text).prop('selected', true);
                        } else {
                            $(elementId).append(new Option(data.text, data.id, true, true));
                        }
                        $(elementId).trigger('change');

                        var event = $.Event('select2:select', { params: { data: { id: data.id, text: data.text } } });
                        $(elementId).trigger(event);
                    }
                });
            }
        }

        // Initialize Select2 Fields with old values. Teks pilihan "kode | nama" (set[text][ | ]=kode&set[text][]=nama =
        // CONCAT kode,' | ',nama di api byparams), kata dicari di kode atau nama.
        initializeSelect2('#uom', "{!! url('api/getmaster_uombyparams?set[id]=id&set[text][%20%7C%20]=uom_code&set[text][]=uom_name') !!}", ['uom_code', 'uom_name'], 'Please select UoM', "{{ old('uom_id', isset($param->uom_id) ? $param->uom_id : '') }}");
        initializeSelect2('#pca', "{!! url('api/getmaster_pcabyparams?set[id]=id&set[text][%20%7C%20]=pca_code&set[text][]=pca_name') !!}", ['pca_code', 'pca_name'], 'Please select PCA', "{{ old('pca_id', isset($param->pca_id) ? $param->pca_id : '') }}", true);
        initializeSelect2('#category', "{!! url('api/getmaster_categorybyparams?set[id]=id&set[text][%20%7C%20]=category_code&set[text][]=category_name') !!}", ['category_code', 'category_name'], 'Please select Category', "{{ old('category_id', isset($param->category_id) ? $param->category_id : '') }}", true);
        initializeSelect2('#item_group', "{!! url('api/getmaster_item_groupbyparams?set[id]=id&set[text][%20%7C%20]=item_group_code&set[text][]=item_group_name') !!}", ['item_group_code', 'item_group_name'], 'Please select Item Group', "{{ old('group_id', isset($param->group_id) ? $param->group_id : '') }}");

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
                    console.log('response', response);

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
            console.log('oldValue', oldValue);
            console.log('data', data);

            var defaultValue = '';
            var attributes = data ? JSON.parse(data.attributes) : {};
            console.log('attributes', attributes);
            if (data && Object.keys(attributes).length > 0) {
                defaultValue = attributes[key] ?? '';
            } else if (oldValue.length !== 0 && oldValue['attributes'][index] != null) {
                // defaultValue = oldValue[key];
                defaultValue = oldValue['attributes'][index];
            }
            console.log('defaultValue', defaultValue);

            $('#form-attributes').append(`
                <div class="form-group mb-3">
                    <label for="${key}">${formatString(key)}</label>
                    <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="attributes[]" id="${key}" class="form-control" value="${defaultValue}" placeholder="Input your ${formatString(key)}">
                </div>
            `);
        }

        function formatString(str) {
            return str
                .replace(/_/g, ' ')          // Replace underscores with spaces
                .split(' ')                  // Split into words
                .map(word => word.charAt(0).toUpperCase() + word.slice(1)) // Capitalize the first letter
                .join(' ');                  // Join the words back into a string
        }
    </script>
@endpush
