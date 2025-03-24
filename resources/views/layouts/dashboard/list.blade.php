@extends('adminlte::page')
@section('title', $data['page']['title'])

@section('content_header')
    @include('layouts.dashboard.navbar', ['data' => @$data])
@stop

@section('content')
    @if (session('error_message'))
        <div class="row">
            <div class="col-12 alert alert-danger alert-dismissible" role="alert">
                @if (is_array(Session::get('error_message')))
                    @foreach (Session::get('error_message') as $error)
                        {!! $error . '<br/>' !!}
                    @endforeach
                @else
                    {!! Session::get('error_message') . '<br/>' !!}
                @endif

                <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
            </div>
        </div>
    @endif

    @if (session('success_message'))
        <div class="row">
            <div class="col-12 alert alert-success alert-dismissible" role="alert">
                @if (is_array(Session::get('success_message')))
                    @foreach (Session::get('success_message') as $error)
                        {!! $error . '<br/>' !!}
                    @endforeach
                @else
                    {!! Session::get('success_message') . '<br/>' !!}
                @endif

                <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
            </div>
        </div>
    @endif

    @if (Session::has('error'))
        @if (!empty(Session::get('error')))
            <div class="row">
                <div class="col-xs-12 alert alert-danger alert-dismissible" role="alert">
                    @php
                        foreach (Session::get('error') as $error):
                            echo $error['message'] . '<br/>';
                        endforeach;
                    @endphp
                    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                </div>
            </div>
        @endif
    @endif

    @if (Session::has('success'))
        @if (!empty(Session::get('success')))
            <div class="row">
                <div class="col-xs-12 alert alert-success alert-dismissible" role="alert">
                    @php
                        foreach (Session::get('success') as $success):
                            echo $success['message'] . '<br/>';
                        endforeach;
                    @endphp
                    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                </div>
            </div>
        @endif
    @endif

    @include('layouts.dashboard.comment', ['data' => @$data])

    <div class="row">
        <div class="col-12">
            {{-- card Import Item --}}
            @isset($data['page']['import'])
                @include($data['page']['import']['layout'])
            @endisset

            <div class="card card-outline card-primary ">
                <div class="card-body table-responsive">

                    @isset($data['datatable']['btn'])
                        @foreach ($data['datatable']['btn'] as $key => $item)
                            <a id="{{ $item['id'] }}"
                                @isset($item['url'])
                                    href="{!! $item['url'] !!}"
                                @endisset
                                @isset($item['act'])
                                    onclick="{!! $item['act'] !!}"
                                @endisset
                                class="btn btn-sm {{ $item['icon'] }} mb-3">
                                {{ $item['title'] }}
                            </a>
                        @endforeach
                    @endisset

                    <table id="{{ $data['page']['tabel'] }}_tabel" class="table table-hover table-bordered table-striped"
                        style="width:100%">
                        <thead>
                            <tr class="header"></tr>
                            <tr class="cari d-none"></tr>
                        </thead>
                        <tbody>
                        </tbody>
                        <tfoot id="{{ $data['page']['slug'] }}_footer">
                            <tr class="header"></tr>
                            <tr class="cari d-none"></tr>
                        </tfoot>
                    </table>

                </div>
            </div>
        </div>
    </div>
@stop

@push('js')
    <form action="" id="delete-form" method="POST" autocomplete="off">
        @method('delete')
        @csrf
    </form>

    <script>
        var data,
            tableName = '#{{ $data['page']['slug'] }}_tabel',
            headerName = '#{{ $data['page']['slug'] }}_header',
            footerName = '#{{ $data['page']['slug'] }}_footer',
            columns,
            str,
            jqxhr = $.ajax({
                "url": "{{ route($data['ajax']['url_prefix'] . '.json') }}",
                "dataType": "json",
                "type": "POST",
                "data": {
                    _token: "{{ csrf_token() }}",
                    sheet_name: "{{ $data['page']['sheet_name'] }}",
                    serverSide: "true",
                    id: {{ $data['page']['id'] }},
                }
            })
            .done(function(data) {
                // console.log("data::",data);
                // var data = jqxhr.responseJSON;

                // console.log("data::",data,data.columns);
                // alert(11);

                // Iterate each column and print table headers for Datatables
                $.each(data.columns, function(k, colObj) {
                    // console.log("each::",k, colObj);
                    str = '<th>' + colObj.name + '</th>';
                    str2 = '<th><input type="text" placeholder="Search ' + colObj.name + '" /></th>';
                    $(str).appendTo(tableName + '>thead>tr.header');
                    $(str2).appendTo(tableName + '>thead>tr.cari');

                    $(str).appendTo(tableName + '>tfoot>tr.header');
                    $(str2).appendTo(tableName + '>tfoot>tr.cari');
                });

                // Add some Render transformations to Columns
                // Not a good practice to add any of this in API/ Json side
                data.columns[0].render = function(data, type, row) {
                    // alert(data);
                    return '<b>' + data + '</b>';
                    // return data;
                }
                // Add some Render transformations to Columns
                // Not a good practice to add any of this in API/ Json side
                data.columns[0].render = function(data, type, row) {
                    return '<b>' + data + '</b>';
                }



                var idx = $(tableName).dataTable({
                    // order: [[0, 'desc']],
                    ordering: false,
                    processing: true,
                    serverSide: true,
                    ajax: {
                        'type': 'POST',
                        'url': '{{ route($data['ajax']['url_prefix'] . '.json') }}',
                        'data': {
                            _token: "{{ csrf_token() }}",
                            sheet_name: "{{ $data['page']['sheet_name'] }}",
                            serverSide: "true",
                            id: {{ $data['page']['id'] }},
                        },
                    },
                    "data": data.data,
                    "columns": data.columns,
                    "fnInitComplete": function() {
                        // Event handler to be fired when rendering is complete (Turn off Loading gif for example)
                        console.log('Datatable rendering complete'); // Apply the search
                        this.api().columns().every(function() {
                            var that = this;

                            $('input', this.footer()).on('keyup change clear', function() {
                                if (that.search() !== this.value) {
                                    that
                                        .search(htmlEntities(this.value))
                                        .draw();
                                }
                            });
                        });
                    }
                });
            })
            .fail(function(jqXHR, exception) {
                var msg = '';
                if (jqXHR.status === 0) {
                    msg = 'Not connect.\n Verify Network.';
                } else if (jqXHR.status == 404) {
                    msg = 'Requested page not found. [404]';
                } else if (jqXHR.status == 500) {
                    msg = 'Internal Server Error [500].';
                } else if (exception === 'parsererror') {
                    msg = 'Requested JSON parse failed.';
                } else if (exception === 'timeout') {
                    msg = 'Time out error.';
                } else if (exception === 'abort') {
                    msg = 'Ajax request aborted.';
                } else {
                    msg = 'Uncaught Error.\n' + jqXHR.responseText;
                }
                console.log(msg);
            });
    </script>

    @if (isset($data['page']['js']))
        @include($data['page']['js'])
    @endif
@endpush
