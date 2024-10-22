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
                            <label for="item_code">Item Code</label>
                            <input type="text" name="item_code" id="item_code" class="form-control"
                                   value="{{ $param ? $param->item_code : old('item_code') }}" required placeholder="Input your Item Code">
                        </div>
                        <div class="form-group">
                            <label for="item_name">Item Name</label>
                            <input type="text" name="item_name" id="item_name" class="form-control"
                                   value="{{ $param ? $param->item_name : old('item_name') }}" required placeholder="Input your Item Name">
                        </div>
                        <div class="form-group">
                            <label for="uom">Unit of Measurement</label>
                            <select class="form-control @error('uom_id') is-invalid @enderror" name="uom_id" id="uom">
                                @if(isset($param->uom_id))  
                                    <option value="{{ $param->uom_id }}" selected>{{ $param->uom_name }}</option> 
                                @endif
                            </select>
                            @error('uom_id') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="pca">PCA</label>
                            <select class="form-control @error('pca_id') is-invalid @enderror" name="pca_id" id="pca">
                                @if(isset($param->pca_id))  
                                    <option value="{{ $param->pca_id }}" selected>{{ $param->pca_name }}</option> 
                                @endif
                            </select>
                            @error('pca_id') <span class="text-danger">{{$message}}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select class="form-control @error('category_id') is-invalid @enderror" name="category_id" id="category">
                                @if(isset($param->category_id))  
                                    <option value="{{ $param->category_id }}" selected>{{ $param->category_name }}</option> 
                                @endif
                            </select>
                            @error('category_id') <span class="text-danger">{{$message}}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="group">Item Group</label>
                            <select class="form-control @error('group_id') is-invalid @enderror" name="group_id" id="item_group">
                                @if(isset($param->group_id))  
                                    <option value="{{ $param->group_id }}" selected>{{ $param->item_group_name }}</option> 
                                @endif
                            </select>
                            @error('group_id') <span class="text-danger">{{$message}}</span> @enderror
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

    <script>
        const CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

        // Common Select2 Configuration
        function initializeSelect2(elementId, url, searchParam, placeholder) {
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
        }

        // Initialize Select2 Fields
        initializeSelect2('#uom', "{!! url('api/getmaster_uombyparams?set[id]=id&set[text]=uom_name') !!}", 'uom_name', 'Please select UoM');
        initializeSelect2('#pca', "{!! url('api/getmaster_pcabyparams?set[id]=id&set[text]=pca_name') !!}", 'pca_name', 'Please select PCA');
        initializeSelect2('#category', "{!! url('api/getmaster_categorybyparams?set[id]=id&set[text]=category_name') !!}", 'category_name', 'Please select Category');
        initializeSelect2('#item_group', "{!! url('api/getmaster_item_groupbyparams?set[id]=id&set[text]=item_group_name') !!}", 'item_group_name', 'Please select Item Group');

    </script>
@endpush
