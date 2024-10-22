@extends('adminlte::page')

@section('title', @$data['page']['title'])

@section('content_header')
    @include('layouts.dashboard.navbar', ['data' => @$data])
@stop

@section('content')
    @if (session('success_message'))
        <div class="alert alert-success">
            {{ session('success_message') }}
        </div>
    @endif
    <div class="@if ($agent->isMobile()) @else d-flex @endif">
        {{-- <h3 class="text-dark">List of Attachments</h3> --}}
        <ul class="nav nav-pills ml-auto mb-2">
            @if (isset($data['sub-menu']['nav']))
                @foreach ($data['sub-menu']['nav'] as $key => $item)
                    <li class="nav-item">
                        <a class="nav-link text-sm {{ $filetype == 'document' ? 'active' : '' }}"
                            href="{{ @$data['route']['attachments'] . '/' . $item['type'] }}"><i
                                class="fa fa-sm fa-fw fa-{{ $item['icon'] }} mr-1"></i>{{ $item['title'] }}
                        </a>
                    </li>
                @endforeach
            @endif
        </ul>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h4 class="card-title" style="font-weight:bold;">{{ @$data['tab-menu']['title'] }}</h4>
                </div>
                <div class="card-body table-responsive">

                    <div class="d-flex mb-2">

                        @if (@$formdata->status == 'draft')
                            {{-- @if (strpos('A|draft', @$formdata->status) || @$formdata->status_id > 0) --}}
                            <a id="upload_attachment" href="#" data-toggle="modal"
                                data-target="#UPLOAD-ATTACHMENT-MODAL" class="btn btn-sm btn-primary mr-1">
                                Upload
                            </a>
                        @endif

                        @isset($formModal)
                            <a class="btn btn-sm btn-info  mr-1" data-toggle="modal" data-target="#modal_search">Search</a>
                            @include('components.formmodal')
                        @endisset
                        {{-- @endif --}}
                        {{-- <a href="#" class="btn btn-sm btn-primary mr-1" onclick="showSearch();">Search</a> --}}
                    </div>

                    @isset($data['page']['slug'])
                        <table id="{{ $data['page']['slug'] }}_tabel" class="table table-hover table-bordered table-striped"
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
                    @endisset

                </div>
            </div>
        </div>
    </div>

    {{-- @include($data['modal']['view_path']) --}}

    <div class="modal fade" id="UPLOAD-ATTACHMENT-MODAL" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Attachment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="attachment" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-1">
                            <label for="attachment">Attachment</label>
                            <div class="attachments">
                                <input type="text" hidden name="slug" value="{{ $slug }}">
                                <input type="text" hidden name="post_id" value="{{ $id }}">
                                <input accept="{{ @$data['attachment']['file'][$filetype] }}" type="file" class="form-control attachment-file" name="attachment[]" multiple>
                                <input accept="{{ @$data['attachment']['file'][$filetype] }}" type="file" class="form-control attachment-file" name="attachment[]" multiple>
                                <input accept="{{ @$data['attachment']['file'][$filetype] }}" type="file" class="form-control attachment-file" name="attachment[]" multiple>
                                <input accept="{{ @$data['attachment']['file'][$filetype] }}" type="file" class="form-control attachment-file" name="attachment[]" multiple>
                            </div>
                            <a href="#" class="add-input mt-1">Add New File</a>
                            @error('attachment')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </form>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="attachmentsubmit">Save changes</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ADD-GALLERY-ATTACHMENT-MODAL" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add from Gallery</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="addfromgallery" action="" method="post" enctype="multipart/form-data">
                    <input type="text" hidden name="filetype" value="{{ $filetype }}">
                    <input type="text" hidden name="user_id" value="{{ Auth::user()->id }}">
                    <input type="text" hidden name="asset_id" value="{{ @$tabel->id }}">
                    <input type="text" hidden name="asset_number" value="{{ @$data['master']['asset_number'] }}">
                    <input type="text" hidden name="group" value="{{ @$data['master']['group'] }}">
                </form>
                <div class="modal-body" id="render">

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="addgallerysubmit">Save changes</button>
                </div>
            </div>
        </div>
    </div>
@stop

@push('js')
    <script>
        $('#datatable tfoot th').each(function() {
            var title = $(this).text();
            $(this).html('<input type="text" placeholder="Search ' + title + '" />');
        });


        function showSearch() {
            $("#tfoot").show();
            $('html, body').animate({
                scrollTop: $(document).height()
            }, 1500);

            return false;
        }

        function htmlEntities(str) {
            return String(str).replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/!/g, '&#33;');
        }

        function actionGAttachmentDelete(file_id) {
            event.preventDefault();
            Swal.fire({
                title: 'Delete Gallery Attachment',
                text: "Are you sure want to delete this?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Delete'
            }).then((result) => {
                if (result.value == true) {
                    $.ajax({
                        url: "{{ route('module.attachment.destroy') }}/" + file_id,
                        data: {
                            file_id: file_id,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        type: "delete",
                        dataType: "json",
                        success: function(response) {
                            if (response[0].success == true) {
                                swal.fire("Done!", response[0].message, "success");

                                $(tableName).DataTable().ajax.reload();

                            } else {
                                swal.fire("Error!", 'error');
                            }
                        },
                        error: function() {
                            swal.fire("Error!", "Delete tidak berhasil!", 'error');
                        }
                    })
                }
            });
        }
    </script>
    <script>
        var imgFromGallery = [];

        $('#ADD-GALLERY-ATTACHMENT-MODAL').on('shown.bs.modal', function(e) {
            $.ajax({
                url: "#", // Replace with your server-side script to fetch image data
                type: 'GET',
                data: {
                    group: "{{ @$data['master']['group'] }}",
                    prefix: "{{ @$data['master']['url_prefix'] }}",
                    filetype: "{{ $filetype }}",
                    _token: CSRF_TOKEN
                },
                dataType: 'json',
                success: function(data) {
                    $('#render').html(data.render);
                    imgCheckbox(data.halaman);
                },
                error: function(xhr, status, error) {
                    console.log('Error:', error);
                }
            });
        })

        $('#ADD-GALLERY-ATTACHMENT-MODAL').on('hidden.bs.modal', function() {
            imgFromGallery = [];
        })

        function loadMoreData(halaman) {
            $.ajax({
                url: "#", // Replace with your server-side script to fetch image data
                type: 'GET',
                data: {
                    page: halaman,
                    group: "{{ @$data['master']['group'] }}",
                    prefix: "{{ @$data['master']['url_prefix'] }}",
                    filetype: "{{ $filetype }}",
                    _token: CSRF_TOKEN
                },
                beforeSend: function() {
                    $('#loadmorediv').remove();
                },
                success: function(data) {
                    $('#render').append(data.render);
                    imgCheckbox(data.halaman);
                }
            });
        }

        $('#attachment .add-input').click(function() {
            $('#attachment .attachments').append(
                '<input type="file" class="form-control attachment-file" name="attachment[]" multiple>');
        });

        $('#attachmentsubmit').click(function(e) {
            e.preventDefault();

            var form = document.getElementById("attachment");
            var url = "{{ route('module.attachment.store.json') }}";
            var formData = new FormData(form);

            $.each($(".attachment-file"), function(i, obj) {
                $.each(obj.files, function(j, file) {
                    formData.append('Attachment[' + i + ']', file);
                });
            });

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: "POST",
                url: url,
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log("#attachmentsubmit.response::", response);
                    if (response.success == true) {
                        swal.fire("Done!", response.message, "success");

                        $("#UPLOAD-ATTACHMENT-MODAL").modal('hide');
                        $(tableName).DataTable().ajax.reload();

                        // setInterval('window.location.reload()', 1000);
                    } else {
                        swal.fire("Error!", 'error');
                    }
                },
                error: function(xhr, status, error) {
                    res = xhr.responseJSON.errors;
                    list_error = '';
                    Object.entries(res).forEach((entry) => {
                        const [key, value] = entry;
                        list_error += value + "<br>";
                    });

                    swal.fire(xhr.responseJSON.message, list_error);
                }
            });
            return false;
        });

        function imgCheckbox(halaman) {
            $(".hal-" + halaman).imgCheckbox({
                'graySelected': false,
                'checkMarkPosition': 'top-right',
                'canDeselect': true,
                onclick: function(el) {
                    var isChecked = el.hasClass("imgChked");
                    imgEl = el.children()[0]; // the img element
                    name = imgEl.getAttribute("data-name");

                    if (isChecked == true) {
                        imgFromGallery.push(name);
                    } else {
                        imgFromGallery.splice($.inArray(name, imgFromGallery), 1);
                    }
                }
            });
        }

        $('#addgallerysubmit').click(function(e) {
            e.preventDefault();

            var form = document.getElementById("addfromgallery");
            var url = "#";

            var formData = new FormData(form);
            formData.append("gallery_id", imgFromGallery);

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: "POST",
                url: url,
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log("response::", response);
                    if (response[0].success == true) {
                        swal.fire("Done!", response[0].message, "success");
                        setInterval(location.reload(), 1000);
                    } else {
                        swal.fire("Error!", 'error');
                    }
                },
                error: function(xhr, status, error) {
                    res = xhr.responseJSON.errors;
                    list_error = '';
                    Object.entries(res).forEach((entry) => {
                        const [key, value] = entry;
                        list_error += value + "<br>";
                    });

                    swal.fire(xhr.responseJSON.message, list_error);
                }
            });
            return false;
        });

        @if (strpos('A|submit|close', @strtolower($formdata->status)))
            $('#upload_attachment').hide(); // Disable all the buttons
            let columnIdx = 5; //colom action
            let column = table.column(columnIdx);
            column.visible(!column.visible());
        @endif

        Fancybox.bind('[data-fancybox]', {
            // Your custom options for a specific gallery
        });
    </script>


    <script>
        @isset($data['page']['slug'])

            var data,
                tableName = '#{{ $data['page']['slug'] }}_tabel',
                headerName = '#{{ $data['page']['slug'] }}_header',
                footerName = '#{{ $data['page']['slug'] }}_footer',
                columns,
                str,
                jqxhr = $.ajax({
                    "url": "{{ route($data['ajax']['url_prefix'] . '.show', ['slug' => $slug, 'post_id' => $id]) }}",
                    "dataType": "json",
                    "type": "POST",
                    "data": {
                        _token: "{{ csrf_token() }}",
                        sheet_name: "{{ $data['page']['sheet_name'] }}",
                        serverSide: "true",
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

                    var idx = $(tableName).DataTable({
                        // order: [[0, 'desc']],
                        ordering: false,
                        processing: true,
                        serverSide: true,
                        rowReorder: {
                            dataSrc: 'No'
                        },
                        ajax: {
                            'type': 'POST',
                            'url': '{{ route($data['ajax']['url_prefix'] . '.show', ['slug' => $slug, 'post_id' => $id]) }}',
                            'data': {
                                _token: "{{ csrf_token() }}",
                                sheet_name: "{{ $data['page']['sheet_name'] }}",
                                serverSide: "true",
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

                    idx.on('row-reorder', function(e, diff, edit) {
                        for (var i = 0, ien = diff.length; i < ien; i++) {
                            var id = idx.row(diff[i].node).data()['id'];
                            var newData = diff[i].newData;
                            updateRowData(id, newData);
                        }
                        $(tableName).DataTable().ajax.reload();
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
        @endisset
    </script>
    <script>
        function updatePinImage(event, el) {
            var url = "{{ route('module.attachment.store.json') }}";
            var formData = new FormData();
            var id = $(el).attr('data-id');
            formData.append('id', id);
            formData.append('pin', 1);

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: "POST",
                url: url,
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log("#attachmentsubmit.response::", response);
                    if (response.success == true) {
                        swal.fire("Done!", response.message, "success");

                        $(tableName).DataTable().ajax.reload();
                    } else {
                        swal.fire("Error!", 'error');
                    }
                },
                error: function(xhr, status, error) {
                    res = xhr.responseJSON.errors;
                    list_error = '';
                    Object.entries(res).forEach((entry) => {
                        const [key, value] = entry;
                        list_error += value + "<br>";
                    });

                    swal.fire(xhr.responseJSON.message, list_error);
                }
            });
            return false;

        }

        function updateRowData(id, newData) {
            console.log(id, newData);
            var url = "{{ route('module.attachment.store.json') }}";
            var formData = new FormData();
            formData.append('id', id);
            formData.append('sort', newData);

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: "POST",
                url: url,
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    // console.log("#attachmentsubmit.response::", response);
                    // if (response.success == true) {
                    //     swal.fire("Done!", response.message, "success");
                    // } else {
                    //     swal.fire("Error!", 'error');
                    // }
                },
                error: function(xhr, status, error) {
                    res = xhr.responseJSON.errors;
                    list_error = '';
                    Object.entries(res).forEach((entry) => {
                        const [key, value] = entry;
                        list_error += value + "<br>";
                    });

                    swal.fire(xhr.responseJSON.message, list_error);
                }
            });
            return false;
        }
    </script>
    <form action="" id="delete-form" method="post">
        @method('delete')
        @csrf
    </form>
@endpush
