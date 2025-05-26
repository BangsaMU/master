@extends('layouts.tabler')

@section('title', @$data['page']['title'])

@section('header')
    <h2 class="page-title">
        {{ $param ? 'Edit' : 'Create' }}
    </h2>
    <div class="text-muted mt-1">
        Manage details for {{ $data['page']['title'] }}
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-12 col-sm-8">
            <div class="card">
                <div class="card-status-top bg-blue"></div>
                <div class="card-header font-weight-bold">
                    <span class="card-title">{{ $data['page']['title'] }} Form</span>
                </div>
                <div class="card-body">
                    <form action="{{ $data['page']['store'] }}" method="POST" autocomplete="off" class="space-y">
                        @csrf
                        @if ($param)
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="hidden" name="id" value="{{ $param->id }}">
                        @endif

                        <div class="form-group">
                            <label class="form-label" for="item_group_code">Item Group Code</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="item_group_code" id="item_group_code" class="form-control"
                                   value="{{ $param ? $param->item_group_code : old('item_group_code') }}">
                                   @error('item_group_code') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="item_group_name">Item Group Name</label>
                            <input {{ $data['page']['readonly'] ? 'readonly' : '' }} type="text" name="item_group_name" id="item_group_name" class="form-control"
                                   value="{{ $param ? $param->item_group_name : old('item_group_name') }}">
                                   @error('item_group_name') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="attributes">Item Group Attributes</label>
                            <div id="form-attributes">

                                @error('item_group_attributes') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        @if ($data['page']['readonly'] == false)
                            <button type="submit" class="btn btn-primary">Submit</button>
                        @endif
                        <a href="{{route('master.item-group.index')}}" class="btn btn-default">
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

    <script>
        var index_global = 0;

        @if(@$param->id || old())
            var paramData = @json($param) || old();
            console.log(paramData);

            if (paramData['item_group_attributes'] == '{}' || paramData['item_group_attributes'] == '' || paramData['item_group_attributes'] == null) {
                appendInput('','item_group_attributes', '');
            }else{
                var item_group_attributes = JSON.parse(paramData.item_group_attributes);
                console.log(item_group_attributes);

                $('#form-attributes').empty();

                var i = 0;
                for (var attr in item_group_attributes) {
                    console.log(attr);
                    appendInput(index_global, 'item_group_attributes', attr);
                    i++;
                }
            }

        @else
            appendInput('','item_group_attributes', '');
        @endif

        function appendInput(index, id, value){
            console.log(index, index_global);

            if (index == '') {
                index = index_global;
            }
            if (value != '' && value != null) {
                value = formatString(value);
            }
            console.log(index, id, value);


            $("#form-attributes").append(`
                <div class="row mb-2" id="row-input-${id}-${index}">
                    <div class="col">
                        <input {{ $data['page']['readonly'] ? 'readonly' : '' }} class="form-control" id="input-${id}" value="${value ?? ''}" name="${id}[]">
                    </div>
                </div>
            `);
            @if($data['page']['readonly']==false)
            if (index == 0) {
                $("#row-input-"+id+"-"+index).append(`
                    <div class="col-1 d-flex justify-content-end">
                        <button type="button" class="btn btn-primary btn-sm" id="btn-append-input-select2"
                            onclick="appendInput('','item_group_attributes', '')"
                        ><i class="ti ti-plus"></i></button>
                    </div>
                `);
            }else{
                $("#row-input-"+id+"-"+index).append(`
                    <div class="col-1 d-flex justify-content-end" id="div-btn-remove-input-${index}">
                        <button type="button" class="btn btn-danger btn-sm" id="btn-append-input-select2"
                            onclick="removeInput('${id}', ${index})"><i class="ti ti-minus"></i></button>
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
                .replace(/_/g, ' ')          // Replace underscores with spaces
                .split(' ')                  // Split into words
                .map(word => word.charAt(0).toUpperCase() + word.slice(1)) // Capitalize the first letter
                .join(' ');                  // Join the words back into a string
        }
    </script>
@endpush
