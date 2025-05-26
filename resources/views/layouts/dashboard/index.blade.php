@extends((config('app.themes') == '_tabler' ? 'layouts.tabler' : 'adminlte::page'))

@section('title', 'Page List')

@section('content_header')
    @include('master::layouts.dashboard.navbar', ['data' => @$data])
@stop

@if (config('app.themes') == '_tabler')
    @section('header')
        @include('master::layouts.dashboard.navbar', ['data' => @$data])
    @endsection
@endif

@section('content')

    {{-- {!! bladeNotif() !!} --}}

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
                <div class="col-12 alert alert-danger alert-dismissible" role="alert">
                    @if (is_array(Session::get('error')))
                        @foreach (Session::get('error') as $error)
                            @if (is_array(Session::get('error')))
                                @foreach ($error as $keyerror => $valerror)
                                    {!! $valerror . '<br/>' !!}
                                @endforeach
                            @else
                                {!! $error . '<br/>' !!}
                            @endif
                        @endforeach
                    @else
                        {!! Session::get('error') . '<br/>' !!}
                    @endif


                    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                </div>
            </div>
        @endif
    @endif

    @if (Session::has('success'))
        @if (!empty(Session::get('success')))
            <div class="row">
                <div class="col-12 alert alert-success alert-dismissible" role="alert">
                    @if (is_array(Session::get('success')))
                        @foreach (Session::get('success') as $success)
                            @if (is_array(Session::get('success')))
                                @foreach ($success as $keySuccess => $valSuccess)
                                    {!! $valSuccess . '<br/>' !!}
                                @endforeach
                            @else
                                {!! $success . '<br/>' !!}
                            @endif
                        @endforeach
                    @else
                        {!! Session::get('success') . '<br/>' !!}
                    @endif
                    <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                </div>
            </div>
        @endif
    @endif

    <div class="row">
        <div class="col-12">
            @isset($data['page']['import'])
                @include('master::' . $data['page']['import']['layout'])
            @endisset
            @isset($data['page']['export'])
                @include('master::' . $data['page']['export']['layout'])
            @else
                @include('master::components.formmodal')
            @endisset
            <div class="card">
                <div class="card-body table-responsive">
                    <div class="d-flex mb-2">

                        {{-- @if (@$data['page']['new']['active'] == true && !isset($data['datatable']['btn']))
                            <a target="_self" href="{{ $data['page']['new']['url'] }}"
                            href="{{ route($data['page']['new'] , ['parent' => @$data['page']['parent']]) }}"
                                class="btn btn-sm mr-1 btn-primary">
                                Create
                            </a>
                        @endif --}}

                        {{-- @isset($formModal)
                            <button class="btn btn-sm mr-1 btn-info" data-toggle="modal"
                                data-target="#modal_search">Search</button>
                        @endisset --}}


                    </div>

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

                    <table id="{{ $data['page']['slug'] }}_tabel" class="table table-hover table-bordered table-striped"
                        style="width:100%">
                        <thead>
                            <tr class="header"></tr>
                            {{-- <tr class="cari d-none"></tr> --}}
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

@push('css')
    <style>
        table.dataTable th,
        table.dataTable td {
            white-space: nowrap;
        }

        table td {
            font-size: 10pt !important;
            padding: 0.5rem !important;
        }

        .btn-group-sm>.btn,
        .btn-sm {
            padding: 0.1rem .3rem !important;
        }
    </style>
@endpush

@push('js')
    <form action="" id="delete-form" method="POST" autocomplete="off">
        @method('delete')
        @csrf
    </form>


    <script>
        var paramsColumnDefs = [];

        function setParamsDefs(columnTarget) {
            paramsColumnDefs.push({
                orderable: false,
                targets: columnTarget
            });
        }

        var data,
            tableName = '#{{ $data['page']['slug'] }}_tabel',
            headerName = '#{{ $data['page']['slug'] }}_header',
            footerName = '#{{ $data['page']['slug'] }}_footer',
            columns,
            str,
            jqxhr = $.ajax({
                "url": "{!! $data['page']['list'] !!}",
                "dataType": "json",
                "type": "GET",
                "data": {
                    _token: "{{ csrf_token() }}",
                    sheet_name: "{{ $data['page']['sheet_name'] }}",
                    serverSide: "true",
                    @isset($search)
                        @foreach ($search as $keySearch => $valSearch)
                            "{{ $keySearch }}": "{{ $valSearch }}",
                        @endforeach
                    @endisset
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
                    if (colObj.data.includes('No') || colObj.data.includes('action')) {
                        setParamsDefs(k);
                    }

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
                    responsive: {{config('app.RESPONSIVE_TABLE','false')? 'true' : 'false'}},
                    // ordering: false,
                    processing: true,
                    serverSide: true,
                    autoWidth: false,
                    ajax: {
                        'type': 'GET',
                        "url": "{!! $data['page']['list'] !!}",
                        'data': {
                            _token: "{{ csrf_token() }}",
                            sheet_name: "{{ $data['page']['sheet_name'] }}",
                            serverSide: "true",
                            @isset($search)
                                @foreach ($search as $keySearch => $valSearch)
                                    "{{ $keySearch }}": "{{ $valSearch }}",
                                @endforeach
                            @endisset
                        },
                    },
                    "data": data.data,
                    "columns": data.columns,
                    columnDefs: paramsColumnDefs,
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

    <script>
        function notificationBeforeDelete(event, el) {
            event.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete!'
            }).then((result) => {
                if (result.value == true) {
                    $("#delete-form").attr('action', $(el).attr('href'));
                    $("#delete-form").submit();
                }
            });
        }

        function filterDatatable(cari) {
            $(tableName).DataTable().search(cari)
                .draw();
        }

        function notificationClearCache(event, el) {
            event.preventDefault();

            var url = $(el).attr('href');
            var canonical = $(el).attr('data-canonical');

            $.ajax({
                type: 'POST',
                url: url,
                data: {
                    _token: "{{ csrf_token() }}",
                    canonical: canonical
                },
                success: function(response) {
                    console.log(response);
                    if (response.success == true) {
                        swal.fire("Done!", response.message, "success");
                    }
                }
            });
        }
    </script>

    @if (is_array(@$data['page']['js_list']))
        @foreach ($data['page']['js_list'] as $load_js)
            @include('master::' . $load_js)
        @endforeach
    @endif

@endpush
