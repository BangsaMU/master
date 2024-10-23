<script>
    @if (env('APP_ENV') != 'production')
        console.log('load js:{{ dirname(__FILE__) }}/spb-detail.blade');
    @endif


    var tableCommentName = '#{{ $data['page']['slug'] }}_tabel_comment';
    function getCommentDetail(requisition_detail_id) {
            // $('#container').css('display', 'block');
            var jqxhr2 = $.ajax({
                    "url": "{{ route('module.procurement.comment.json') }}",
                    "dataType": "json",
                    "type": "POST",
                    "data": {
                        _token: "{{ csrf_token() }}",
                        sheet_name: "{{ $data['page']['sheet_name'] }}",
                        serverSide: "true",
                        item_comment: "true",
                        id: requisition_detail_id,
                    }
                })
                .done(function(data) {
                    // $.each(data.columns, function(k, colObj) {
                    //     str = '<th>' + colObj.name + '</th>';
                    //     str2 = '<th><input type="text" placeholder="Search ' + colObj.name + '" /></th>';
                    //     $(str).appendTo(tableCommentName + '>thead>tr.header');
                    //     $(str2).appendTo(tableCommentName + '>thead>tr.cari');
                    //     $(str).appendTo(tableCommentName + '>tfoot>tr.header');
                    //     $(str2).appendTo(tableCommentName + '>tfoot>tr.cari');
                    // });

                    data.columns[0].render = function(data, type, row) {
                        return '<b>' + data + '</b>';
                    }
                    data.columns[0].render = function(data, type, row) {
                        return '<b>' + data + '</b>';
                    }
                    $(tableCommentName).DataTable().destroy();
                    var idxComment = $(tableCommentName).DataTable({
                        // order: [[0, 'desc']],
                        // "retrieve": true,
                        // "scrollCollapse": true,
                        // "autoWidth": false,
                        // "scrollX": true,
                        // "scrollY": true,
                        // "destroy": true,
                        "ordering": false,
                        "processing": true,
                        "serverSide": true,
                        "ajax": {
                            'type': 'POST',
                            'url': '{{ route('module.procurement.comment.json') }}',
                            'data': {
                                _token: "{{ csrf_token() }}",
                                sheet_name: "{{ $data['page']['sheet_name'] }}",
                                serverSide: "true",
                                item_comment: "true",
                                id: requisition_detail_id,
                            },
                            // dataSrc: function(json) {
                            //     // Clear the thead and tfoot rows first
                            //     $(tableCommentName + '>thead>tr').empty();
                            //     $(tableCommentName + '>tfoot>tr').empty();

                            //     // Dynamically create headers and footers from columns in JSON
                            //     $.each(json.columns, function(index, col) {
                            //         if (col.width) {
                            //             json.columns[index].width = col.width;
                            //         }
                            //         $('<th>' + col.name + '</th>').appendTo(tableCommentName +
                            //             '>thead>tr');
                            //         $('<th><input type="text" placeholder="Search ' + col.name +
                            //             '" /></th>').appendTo(tableCommentName + '>tfoot>tr');
                            //     });
                            //     return json.data; // Return the actual data for rows
                            // }
                        },
                        "data": data.data,
                        "columns": data.columns,
                        "fnInitComplete": function() {
                            // Event handler to be fired when rendering is complete (Turn off Loading gif for example)
                            console.log('Datatable rendering complete'); // Apply the search
                            // this.api().columns().every(function() {
                            //     var that = this;

                            //     $('input', this.footer()).on('keyup change clear', function() {
                            //         if (that.search() !== this.value) {
                            //             that
                            //                 .search(htmlEntities(this.value))
                            //                 .draw();
                            //         }
                            //     });
                            // });

                            idxComment.columns.adjust();

                            // idxComment.columns.adjust();
                            setTimeout(function() {
                                // table.columns.adjust().draw();
                                $('#{{ $data['page']['slug'] }}_tabel_comment').DataTable().columns
                                    .adjust();
                            }, 100); // Delay in milliseconds
                        }
                    }).columns.adjust();

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


        }

        $('#COMMENT-MODAL').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget) // Button that triggered the modal
            var data_index = button.data('index') // Extract info from data-* attributes
            var data_requisition_detail_id = button.data(
                'requisition_detail_id') // Extract info from data-* attributes
            // If necessary, you could initiate an AJAX request here (and then do the updating in a callback).
            // Update the modal's content. We'll use jQuery here, but you could use a data binding library or other methods instead.
            var modal = $(this)

            getCommentDetail(data_requisition_detail_id);
            modal.find('.modal-title').text('List Comment on item No ' + data_index)
            modal.find('#requisition_detail_id').val(data_requisition_detail_id)


        })

        $('#commentSubmit').off('click').on('click', function() {
            let comment = $('#requisition_detail_input_comment').val();
            let requisition_detail_id = $('#requisition_detail_id').val();

            $('#requisition_detail_input_comment').val('');


            event.preventDefault();
            $.LoadingOverlay('show');

            $.ajax({
                type: 'POST',
                url: "{{ route('module.procurement.comment.store') }}",
                data: {
                    comment: comment,
                    requisition_detail_id: requisition_detail_id,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $.LoadingOverlay('hide', true);
                    if (response.code == 200) {
                        $('#COMMENT-MODAL').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            html: response.success_message
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: response.success_message ||
                                'An error occurred.' // Use message from response or default
                        });
                    }

                    // setTimeout(location.reload(), 2000);
                },
                error: function(xhr) {
                    $.LoadingOverlay('hide', true);
                    console.error("Approval error:", xhr.responseText);

                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: xhr.responseJSON.message ||
                            'Something went wrong on the server.'
                    });

                    if (xhr.responseJSON.redirect) {
                        setTimeout(() => {
                            window.location.href = xhr.responseJSON
                                .redirect;
                        }, 2000);
                    }

                    // setTimeout(location.reload(), 2000);
                }
            });
        });
</script>
