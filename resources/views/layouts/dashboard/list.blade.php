@php
    $theme = config('app.themes');
    if ($theme == '_meridian') {
        $themeLayout = view()->exists('layouts.meridian')
            ? 'layouts.meridian'
            : 'master::layouts.meridian';
    } elseif ($theme == '_tabler') {
        // Cek apakah view "layouts.tabler" ada
        $themeLayout = view()->exists('layouts.tabler')
            ? 'layouts.tabler'
            : 'master::layouts.tabler';
    } else {
        $themeLayout = 'adminlte::page';
    }
@endphp
@extends($themeLayout)

@section('title', $data['page']['title'])

@section('content_header')
    @include('layouts.dashboard.navbar', ['data' => @$data])
@stop

@section('content')
    @if(config('app.themes') == '_meridian')
        @if (session('error_message'))
            <div class="alert alert--danger alert-danger alert-dismissible mb-4 flex items-center justify-between" role="alert">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-lg"></i>
                    <div>
                        @if (is_array(Session::get('error_message')))
                            @foreach (Session::get('error_message') as $error)
                                {!! $error . '<br/>' !!}
                            @endforeach
                        @else
                            {!! Session::get('error_message') . '<br/>' !!}
                        @endif
                    </div>
                </div>
                <button type="button" class="close button button--ghost button--neutral button--icon-only button--sm" data-dismiss="alert" aria-label="close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        @endif

        @if (session('success_message'))
            <div class="alert alert--success alert-success alert-dismissible mb-4 flex items-center justify-between" role="alert">
                <div class="flex items-center gap-3">
                    <i class="fas fa-check-circle text-lg"></i>
                    <div>
                        @if (is_array(Session::get('success_message')))
                            @foreach (Session::get('success_message') as $error)
                                {!! $error . '<br/>' !!}
                            @endforeach
                        @else
                            {!! Session::get('success_message') . '<br/>' !!}
                        @endif
                    </div>
                </div>
                <button type="button" class="close button button--ghost button--neutral button--icon-only button--sm" data-dismiss="alert" aria-label="close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        @endif

        @if (Session::has('error') && !empty(Session::get('error')))
            <div class="alert alert--danger alert-danger alert-dismissible mb-4 flex items-center justify-between" role="alert">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-lg"></i>
                    <div>
                        @php
                            foreach (Session::get('error') as $error):
                                echo (is_array($error) ? ($error['message'] ?? implode('<br/>', $error)) : $error) . '<br/>';
                            endforeach;
                        @endphp
                    </div>
                </div>
                <button type="button" class="close button button--ghost button--neutral button--icon-only button--sm" data-dismiss="alert" aria-label="close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        @endif

        @if (Session::has('success') && !empty(Session::get('success')))
            <div class="alert alert--success alert-success alert-dismissible mb-4 flex items-center justify-between" role="alert">
                <div class="flex items-center gap-3">
                    <i class="fas fa-check-circle text-lg"></i>
                    <div>
                        @php
                            foreach (Session::get('success') as $success):
                                echo (is_array($success) ? ($success['message'] ?? implode('<br/>', $success)) : $success) . '<br/>';
                            endforeach;
                        @endphp
                    </div>
                </div>
                <button type="button" class="close button button--ghost button--neutral button--icon-only button--sm" data-dismiss="alert" aria-label="close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        @endif
    @else
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
    @endif

    @include('layouts.dashboard.comment', ['data' => @$data])

    @if(config('app.themes') == '_meridian')
        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12">
                @isset($data['page']['import'])
                    @include($data['page']['import']['layout'])
                @endisset

                <div class="card">
                    <div class="card__body p-6">
                        @isset($data['datatable']['btn'])
                            <div class="flex items-center gap-2 mb-4">
                                @foreach ($data['datatable']['btn'] as $key => $item)
                                    @php
                                        $btnClass = 'button button--sm';
                                        $iconClass = $item['icon'] ?? '';
                                        if (str_contains($iconClass, 'btn-primary')) {
                                            $btnClass .= ' button--primary';
                                        } elseif (str_contains($iconClass, 'btn-warning')) {
                                            $btnClass .= ' button--warning';
                                        } elseif (str_contains($iconClass, 'btn-danger')) {
                                            $btnClass .= ' button--danger';
                                        } elseif (str_contains($iconClass, 'btn-info')) {
                                            $btnClass .= ' button--info';
                                        } elseif (str_contains($iconClass, 'btn-secondary') || str_contains($iconClass, 'btn-default')) {
                                            $btnClass .= ' button--neutral button--outline';
                                        } else {
                                            $btnClass .= ' ' . $iconClass;
                                        }
                                    @endphp
                                    <a id="{{ $item['id'] }}"
                                        @if(($item['id'] ?? '') === 'importitem' || str_contains(($item['act'] ?? ''), 'importFn'))
                                            data-stisla-dialog-trigger="importItemDiv"
                                        @endif
                                        @isset($item['url']) href="{!! $item['url'] !!}" @endisset
                                        @isset($item['act']) onclick="{!! $item['act'] !!}" @endisset
                                        class="{{ $btnClass }}">
                                        {{ $item['title'] }}
                                    </a>
                                @endforeach
                            </div>
                        @endisset

                        <div class="table-responsive">
                            <table id="{{ $data['page']['tabel'] }}_tabel" class="table table-hover"
                                style="width:100%">
                                <thead>
                                    <tr class="header"></tr>
                                    <tr class="cari hidden"></tr>
                                </thead>
                                <tbody>
                                </tbody>
                                <tfoot id="{{ $data['page']['slug'] }}_footer">
                                    <tr class="header"></tr>
                                    <tr class="cari hidden"></tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-12">
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
    @endif
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
