@extends('adminlte::page')

@php
    $apiUrl = config('app.ticket');
    $appCode = config('SsoConfig.main.APP_CODE');
    $appName = config('app.name');

@endphp

@section('title',$appName . ' Notification Tickets')

@section('content_header')
<h1 class="m-0 text-dark">{{ $appName }} Notification Tickets</h1>

<style>
    select[readonly].select2-hidden-accessible + .select2-container {
        pointer-events: none;
        touch-action: none;
    }

    select[readonly].select2-hidden-accessible + .select2-container .select2-selection {
        background: #eee;
        box-shadow: none;
    }

    select[readonly].select2-hidden-accessible + .select2-container .select2-selection__arrow, select[readonly].select2-hidden-accessible + .select2-container .select2-selection__clear {
        display: none;
    }
</style>
@stop

@section('content')
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    Fitur ini digunakan untuk mencatat informasi keluhan dari user yang akan dikirimkan langsung ke email <strong>apps-support@meindo.com</strong> sebagai report ticket. Mohon di pilih sesuai dengan kriteria tiket yang tersedia pada box dibawah!
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
</div>
<div class="row">
    <div class="col-md-3 col-6">
        <div class="card card-outline card-warning">
            <div class="card-header">
                <h3 class="card-title font-weight-bold">Employee Internal</h3>
            </div>
            <div class="card-body">
                <p>
                    Used to add new employee data (internal). Make sure all required data is prepared.
                </p>
                <p>
                    <span>Format Subject : </span>
                    <br>
                    <b>[{{ $appName }}][EMPLOYEE-INTERNAL]</b>
                </p>
                <button type="button" class="btn btn-warning btn-block btn-ticket" data-toggle="modal" data-target="#composeModal"
                    data-subject="[{{ $appName }}][EMPLOYEE-INTERNAL]">
                    <i class="fa fa-plus"></i> Create Employee Internal Ticket
                </button>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card card-outline card-warning">
            <div class="card-header">
                <h3 class="card-title font-weight-bold">Employee External</h3>
            </div>
            <div class="card-body">
                <p>
                    Used to add new employee data (External). Make sure all required data is prepared.
                </p>
                <p>
                    Please ensure KTP (ID Card) and WO/NPWP Vendor (Vendor Tax ID/Work Order) details are complete for external employees.
                </p>
                <p>
                    <span>Format Subject : </span>
                    <br>
                    <b>[{{ $appName }}][EMPLOYEE-EXTERNAL]</b>
                </p>
                <button type="button" class="btn btn-warning btn-block btn-ticket" data-toggle="modal" data-target="#composeModal"
                    data-subject="[{{ $appName }}][EMPLOYEE-EXTERNAL]">
                    <i class="fa fa-plus"></i> Create Employee External Ticket
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-6">
        <div class="card card-outline card-danger">
            <div class="card-header">
                <h3 class="card-title font-weight-bold">Master</h3>
            </div>
            <div class="card-body">
                <p>
                    Used to add data to master MCU data (Matrix, Package, Etc.). Make sure all required data is prepared.
                </p>
                <p>
                    <span>Format Subject : </span>
                    <br>
                    <b>[{{ $appName }}][MASTER]</b>
                </p>
                <button type="button" class="btn btn-danger btn-block btn-ticket" data-toggle="modal" data-target="#composeModal"
                    data-subject="[{{ $appName }}][MASTER]">
                    <i class="fa fa-plus"></i> Create Master Ticket
                </button>
            </div>
        </div>
    </div>

    {{-- <div class="col-md-3 col-6">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title font-weight-bold">Report</h3>
            </div>
            <div class="card-body">
                <p>
                    Changes, revisions, and provision of reports in the {{ $appName }} system to suit user needs.
                </p>
                <p>
                    <span>Format Subject : </span>
                    <br>
                    <b>[{{ $appName }}][REPORT]</b>
                </p>
                <button type="button" class="btn btn-primary btn-block btn-ticket" data-toggle="modal" data-target="#composeModal"
                    data-subject="[{{ $appName }}][REPORT]">
                    <i class="fa fa-plus"></i> Create Report Ticket
                </button>
            </div>
        </div>
    </div> --}}

    <div class="col-md-3 col-6">
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title font-weight-bold">Others</h3>
            </div>
            <div class="card-body">
                <p>
                    This category is information, general advice, non-technical assistance, access requests, or other reports related to the system.
                </p>
                <p>
                    <span>Format Subject : </span>
                    <br>
                    <b>[{{ $appName }}][OTHERS]</b>
                </p>
                <button type="button" class="btn btn-secondary btn-block btn-ticket" data-toggle="modal" data-target="#composeModal"
                    data-subject="[{{ $appName }}][OTHERS]">
                    <i class="fa fa-plus"></i> Create Others Ticket
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-primary card-body">
    <div class="table-responsive">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0">Ticket List</h4> <!-- Heading -->
                <span class="text-secondary">Ticket list submitted for {{ $appName }} Support/Maintenance</span>
            </div>
        </div>

        @if ($error)
            <div class="alert alert-danger">{{ $error }}</div>
        @endif

        <table id="ticket-table" class="table table-hover table-striped table-bordered">
            <thead>
                <tr>
                    <th>Ticket No</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Notified Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tickets as $ticket)
                    <tr>
                        <td>TICKET #{{ $ticket['id'] }}</td>
                        <td>{{ $ticket['subject'] }}</td>
                        <td>
                            <span class="badge
                                {{ $ticket['status'] === 'open' ? 'badge-success' : ($ticket['status'] === 'closed' ? 'badge-danger' : 'badge-secondary') }}">
                                {{ $ticket['status'] }}
                            </span>
                        </td>
                        <td>{{ \Carbon\Carbon::parse($ticket['created_at'])->format('Y-m-d') }}</td>
                        <td>
                            <span class="badge {{ $ticket['email_sent'] == 1 ? 'badge-primary' : 'badge-warning' }}">
                                {{ $ticket['email_sent'] == 1 ? 'Sent to Email' : 'Not yet sent' }}
                            </span>
                        </td>
                        <td>
                            <!-- Pastikan $ticket adalah objek atau array yang berisi data tiket -->
                            @if ($ticket['email_sent'] != 1)
                                <button class="btn btn-xs btn-warning resend-email" data-id="{{ $ticket['id'] }}">
                                    Re-send mail
                                </button>
                            @endif

                            @if ($ticket['status'] == 'open')
                                <button class="btn btn-xs btn-danger close-ticket" data-id="{{ $ticket['id'] }}">
                                    Close Ticket
                                </button>
                            @endif

                            <button class="btn btn-xs btn-info view-ticket" data-id="{{ $ticket['id'] }}">
                                View Ticket
                            </button>

                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Pagination -->
        @if ($totalPages > 1)
            <nav>
                <ul class="pagination">
                    @for ($i = 1; $i <= $totalPages; $i++)
                        <li class="page-item {{ request('page', 1) == $i ? 'active' : '' }}">
                            <a class="page-link" href="{{ route('support.ticket-email', ['page' => $i]) }}">{{ $i }}</a>
                        </li>
                    @endfor
                </ul>
            </nav>
        @endif
    </div>
</div>

    <!-- Modal -->
    <div class="modal fade" id="composeModal" tabindex="-1" role="dialog" aria-labelledby="composeModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="composeModalLabel">Compose New Ticket</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="ticketForm" action="{{ route('support.ticket-store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    @if (auth()->user()->email == 'bagas.setyonugroho@meindo.com')
                        <div class="form-group">
                            <label>CC/Notified User</label>
                            <select id="email-to" name="email_to[]" autocomplete="off" class="form-control" multiple="multiple"
                                style="width: 100%;" placeholder="To:">
                                <option selected value="bagas.setyonugroho@meindo.com">bagas.setyonugroho@meindo.com</option>
                                <option selected value="{{ auth()->user()->email }}">{{ auth()->user()->email }}</option>
                            </select>
                        </div>
                    @endif
                    <div class="form-group">
                        <label for="email-subject">Subject</label>
                        <input autocomplete="off" type="text" class="form-control" id="email-subject" name="email_subject"
                            data-prefixsubject="" placeholder="Ticket Subject">
                    </div>
                    <div class="form-group">
                        <label for="compose-textarea">Description</label>
                        <textarea id="compose-textarea" name="description" class="form-control"
                            style="height: 300px;"></textarea>
                    </div>
                    <div class="form-group">
                        <div class="custom-file">
                            <input type="file" autocomplete="off" class="custom-file-input" id="customFile"
                                name="attachments[]" multiple>
                            <label class="custom-file-label" for="customFile">Choose files</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i
                            class="fas fa-times"></i> Discard</button>
                    <button type="submit" class="btn btn-primary"><i class="far fa-envelope"></i> Send</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- End Modal -->

<!-- Bootstrap 4 Modal -->
<div class="modal fade" id="ticketModal" tabindex="-1" aria-labelledby="ticketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ticketModalLabel">View Ticket</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="cc">CC</label>
                    <input readonly type="text" class="form-control" id="ticket-cc" name="cc" placeholder="Ticket Subject">
                </div>
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input readonly type="text" class="form-control" id="ticket-subject" name="subject" placeholder="Ticket Subject">
                </div>
                <div class="form-group">
                    <label for="subject">Description/Detail</label>
                    <div id="ticket-description" class="form-control" style="height: 300px; overflow-y: auto; background: #fff;"></div>
                </div>
                <div class="form-group">
                    <label for="created-at">Created At</label>
                    <input readonly type="text" class="form-control" id="ticket-created-at" name="created-at" placeholder="Ticket Created At">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@stop

@push('js')
<!-- In your Blade template -->
<script src="https://unpkg.com/alpinejs" defer></script>
<!-- Summernote CSS -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote.min.css" rel="stylesheet">
<!-- Summernote JS -->
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote.min.js"></script>
<script>
    $(document).ready(function() {


    // $('#ticket-table').DataTable({
    //     processing: true,
    //     serverSide: true,
    //     responsive: true,
    //     ajax: {
    //         url: 'http://192.168.16.205:9016/api/tickets', // Ganti dengan URL API kamu
    //         data: function (d) {
    //             console.log('samu data::',d);
    //             // Mapping agar query param sesuai dengan API backend
    //             return {
    //                 limit: d.length,
    //                 offset: d.start,
    //                 search: d.search.value,
    //                 order_by: d.columns[d.order[0].column].data,
    //                 order: d.order[0].dir
    //             };
    //         }
    //     },
    //     columns: [
    //         { data: 'id', name: 'ticket_no' },
    //         { data: 'subject', name: 'subject' },
    //         { data: 'status', name: 'status' },
    //         { data: 'created_at', name: 'created_at' },
    //         { data: 'email_sent', name: 'email_sent' },
    //         { data: 'id', name: 'action', orderable: false, searchable: false }
    //     ],
    //     language: {
    //         search: "Search:",
    //         lengthMenu: "Show _MENU_ entries per page",
    //         zeroRecords: "No matching records found",
    //         info: "Showing page _PAGE_ of _PAGES_",
    //         infoEmpty: "No data available",
    //         infoFiltered: "(filtered from _MAX_ total entries)"
    //     }
    // });


    // $('#ticket-table').DataTable({
    //     responsive: true,
    //     language: {
    //         search: "Search:",
    //         lengthMenu: "Show _MENU_ entries per page",
    //         zeroRecords: "No matching records found",
    //         info: "Showing page _PAGE_ of _PAGES_",
    //         infoEmpty: "No data available",
    //         infoFiltered: "(filtered from _MAX_ total entries)"
    //     }
    // });


        // Initialize Summernote
        $('#compose-textarea').summernote({
            height: 200, // Set editor height
            placeholder: 'Write your content here...', // Set placeholder text
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['insert', ['link', 'picture']],
            ],
            callbacks: {
                onImageUpload: function(files) {
                    // Handle image upload
                    for (let i = 0; i < files.length; i++) {
                        let file = files[i];
                        if (file.type.startsWith('image/')) {
                            resizeImage(file, 1600, function(resizedDataUrl) {
                                $('#compose-textarea').summernote('insertImage', resizedDataUrl, file.name);
                            });
                        }
                    }
                },
                onMediaDelete: function(target) {
                    // Optional: Handle image deletion if necessary
                }
            }
        });

        $(".view-ticket").on("click", function() {
            var ticketId = $(this).data("id");
            var ticketData = "{{url('support/ticket-email')}}/" + ticketId;

            // Fetch data through PHP cURL endpoint instead of direct API call
            $.get(ticketData, function(response) {
                if (response.success) {
                    var ticket = response.data;
                    $("#ticket-cc").val(ticket.cc);
                    $("#ticket-subject").val(ticket.subject);
                    $("#ticket-description").html(ticket.description);
                    $("#ticket-created-at").val(new Date(ticket.created_at).toLocaleString());
                    // Show modal
                    $("#ticketModal").modal("show");
                } else {
                    alert("Failed to retrieve ticket: " + (response.message || "Unknown error"));
                }
            }).fail(function(jqXHR) {
                alert("Error fetching ticket data: " + jqXHR.statusText);
            });
        });

        $('.close-ticket').on('click', function(e) {
            e.preventDefault(); // Mencegah aksi default tombol jika ada

            var ticketId = $(this).data('id');
            if (!confirm('Apakah Anda yakin ingin close tiket #' + ticketId + '?')) {
                return; // Jika pengguna membatalkan, hentikan eksekusi
            }

            var apiUrl = "{{ config('app.ticket') }}" + '/api/tickets/' + ticketId + '/close';

            $.ajax({
                url: apiUrl,
                type: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                success: function(response) {
                    alert('Tiket #' + ticketId + ' berhasil di close!');
                    location.reload();
                },
                error: function(xhr, status, error) {
                    alert('Gagal close tiket #' + ticketId + '.\nError: ' + xhr.responseText);
                }
            });
        });

        $('.resend-email').on('click', function(e) {
            e.preventDefault(); // Mencegah aksi default tombol jika ada

            var ticketId = $(this).data('id');
            if (!confirm('Apakah Anda yakin ingin mengirim ulang email untuk tiket #' + ticketId + '?')) {
                return; // Jika pengguna membatalkan, hentikan eksekusi
            }

            var apiUrl = "{{ config('app.ticket') }}" + '/api/tickets/' + ticketId + '/resend-email';

            $.ajax({
                url: apiUrl,
                type: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                success: function(response) {
                    alert('Email berhasil dikirim ulang untuk tiket #' + ticketId + '.');
                    location.reload();
                },
                error: function(xhr, status, error) {
                    alert('Gagal mengirim ulang email untuk tiket #' + ticketId + '.\nError: ' + xhr.responseText);
                }
            });
        });

        // Function to resize images
        function resizeImage(file, maxWidth, callback) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;

                    if (width > maxWidth) {
                        height = height * (maxWidth / width);
                        width = maxWidth;
                    }

                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    // Convert canvas to data URL
                    const dataUrl = canvas.toDataURL('image/jpeg', 0.9); // Adjust quality as needed
                    callback(dataUrl);
                }
                img.src = event.target.result;
            }
            reader.readAsDataURL(file);
        }

        $('.btn-ticket').on('click', function() {
            let subject = $(this).data('subject'); // Get the data-subject value from the clicked button
            $('#email-subject').data('prefixsubject', subject); // Store it as a data attribute
        });

        // Initialize Select2
        $('#composeModal').on('shown.bs.modal', function() {
            $('#email-to').select2({
                tags: true,
                tokenSeparators: [',', ' '],
                placeholder: "To:",
                allowClear: true
            });
        });

        // Update file input label dynamically
        $('#customFile').on('change', function() {
            const fileNames = Array.from(this.files).map(file => file.name).join(', ');
            $(this).next('.custom-file-label').text(fileNames || 'Choose files');
        });

        // Handle form submission via AJAX
        $('#ticketForm').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            const $submitButton = $(this).find('button[type="submit"]'); // Cache the submit button
            const originalButtonHTML = $submitButton.html(); // Save the original button HTML

            // Update button to show loading state
            $submitButton.html(`
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Sending...
            `);
            $submitButton.prop('disabled', true); // Disable the button

            // Gather form data
            const formData = new FormData(this);

            // Append additional data if necessary
            const prefixSubject = $('#email-subject').data('prefixsubject') || '';
            const subject = prefixSubject + ' ' + $('#email-subject').val();
            formData.set('subject', subject.trim());

            // Send data to the Laravel controller
            $.ajax({
                url: $(this).attr('action'),
                method: 'POST',
                data: formData,
                processData: false, // Required for FormData
                contentType: false, // Required for FormData
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' // Ensure CSRF token is sent
                },
                success: function(response) {
                    // Handle success
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Ticket created successfully!',
                    }).then(() => {
                        $('#composeModal').modal('hide'); // Hide modal
                        $('#ticketForm')[0].reset(); // Reset form
                        $('.custom-file-label').text('Choose files'); // Reset file input label
                        $('#email-to').val(null).trigger('change'); // Reset Select2
                        $('#compose-textarea').summernote('reset'); // Reset Summernote
                        location.reload(); // Reload the page to fetch new tickets
                    });
                },
                error: function(xhr) {
                    // Handle error
                    if (xhr.status === 422) {
                        // Validation error
                        let errorMessages = '';
                        $.each(xhr.responseJSON.errors, function(key, errors) {
                            errorMessages += `${errors.join(', ')}<br>`;
                        });

                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            html: errorMessages, // Show error messages
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to create ticket. Please try again.',
                        });
                    }

                    console.error(xhr); // Log error for debugging
                },
                complete: function() {
                    // Re-enable the button and restore its original HTML
                    $submitButton.html(originalButtonHTML);
                    $submitButton.prop('disabled', false);
                }
            });
        });

        // Update file input label dynamically on form reset
        $('#ticketForm').on('reset', function() {
            $('.custom-file-label').text('Choose files');
        });
    });
</script>
@endpush
