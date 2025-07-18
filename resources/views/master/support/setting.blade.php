@extends('adminlte::page')

@section('title', 'Apps Settings')

@section('content_header')
<h1 class="m-0 text-dark">Settings</h1>
@stop

@section('content')

<!-- Display Success Message -->
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <small class="d-block">{{ session('success') }}</small>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

<!-- Display Validation Errors -->
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        @foreach ($errors->all() as $error)
            <small class="d-block">{{ $error }}</small>
        @endforeach
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

<div class="row">

    <div class="col-7">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Project General Settings</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('setting.store') }}" method="POST" autocomplete="off">
                    @csrf
                    <div class="row">
                        <div class="col-8">
                            <div class="form-group">
                                <label for="project_name">Project Name</label>
                                <input type="text" id="project_name" name="project_name" class="form-control" readonly
                                    value="{{ $project->project_name }}">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label for="project_code">Project Code</label>
                                <input type="text" id="project_code" name="project_code" class="form-control" readonly
                                    value="{{ $project->project_code }}">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label for="is_sendmail">External Mail Notification</label>
                                <select id="is_sendmail" name="project[is_sendmail]" class="form-control custom-select">
                                    <option value="1" @if($project->is_sendmail == 1) selected @endif>True</option>
                                    <option value="0" @if($project->is_sendmail == 0 || $project->is_sendmail ==
                                        null) selected @endif>False</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label for="is_newproject">WSMT New Export</label>
                                <select id="is_newproject" name="project[is_newproject]" class="form-control custom-select">
                                    <option value="1" @if($project->is_newproject == 1) selected @endif>Enabled</option>
                                    <option value="0" @if($project->is_newproject == 0 || $project->is_newproject == null)
                                        selected @endif>Disabled</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-group">
                                <label for="is_reportexternal">Report Type</label>
                                <select id="is_reportexternal" name="project[is_reportexternal]" class="form-control custom-select">
                                    <option value="1" @if($project->is_reportexternal == 1) selected @endif>External
                                    </option>
                                    <option value="0" @if($project->is_reportexternal == 0 || $project->is_reportexternal ==
                                        null) selected @endif>Internal</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-4">
                            <label for="wsmtprepare_id">WSMT Final Prepared By @error('wsmtprepare_id')<small class="text-danger">*{{ $message }}</small>@enderror</label>
                            <select class="form-control select2-wsmtprepare_id" name="project[wsmtprepare_id]" id="wsmtprepare_id">
                            </select>
                        </div>

                        <div class="col-4">
                            <div class="form-group">
                                <label>Wsmt Discipline</label>
                                <select name="wsmt_discipline" class="form-control custom-select">
                                    <option value="1" @if($wsmtDiscipline == 1) selected @endif>Show</option>
                                    <option value="0" @if($wsmtDiscipline == 0 || $wsmtDiscipline == null) selected @endif>Hide</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-4">
                            <div class="form-group">
                                <label>Fitup Area Location</label>
                                <select name="fitup_arealocation" class="form-control custom-select">
                                    <option value="1" @if($fitupAreaLocation == 1) selected @endif>Show</option>
                                    <option value="0" @if($fitupAreaLocation == 0 || $fitupAreaLocation == null) selected @endif>Hide</option>
                                </select>
                            </div>
                        </div>

                    </div>
                    <button type="submit" class="btn btn-primary float-right mt-3">Save Setting</button>
                </form>
            </div>
        </div>

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Change MVR No</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form id="form-changemvr" action="{{ route('setting.change-mvr') }}" method="POST" autocomplete="off">
                    @csrf
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="mrr_no">Choose MRR</label>
                                <select id="mrr_id" name="mrr_id" class="form-control custom-select"></select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="mvr_no">Choose MVR</label>
                                <select id="mvr_no" name="mvr_no" class="form-control custom-select"></select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="mvr_no">Change From</label>
                                <input readonly type="text" id="from_mvr_no" name="from_mvr_no" class="form-control">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="mvr_no">Change To</label>
                                <input type="text" id="to_mvr_no" name="to_mvr_no" class="form-control">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary float-right">Save Changes</button>
                </form>
            </div>
        </div>

        <form action="{{route('setting.feedback.store')}}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="card card-outline card-primary">
                <div class="card-header font-weight-bold">
                    Feedback Url
                </div>
                <div class="card-body">
                    <input type="text" class="form-control @error('feedback_url') is-invalid @enderror" id="feedbackurl"
                        placeholder="Feedback url" name="feedback_url" value="{{ $feedback->feedback_url }}">
                    @error('feedback_url') <span class="text-danger">{{$message}}</span> @enderror
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="{{route('companys.index')}}" class="btn btn-default">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    </div>
    <div class="col-5">
        <div class="card card-outline card-primary">
            <div class="card-header font-weight-bold">
                Wsmt Pagebreak Custom Report
                <div class="card-tools">
                    <button href="#" data-key="wsmt_pagebreak" class="btn btn-primary btn-sm addNewWeldmap">Add New Weldmap</button>
                </div>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover table-bordered table-stripped datatables">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Weldmaps</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($wsmtPagebreak as $no => $item)
                        <tr>
                            <td>{{ ++$no }}</td>
                            <td>{{ $item->value }}</td>
                            <td align="center">
                                <a href="#" class="btn btn-sm btn-danger delete-weldmap"
                                    data-id="{{ $item->id }}"><i class="fa fa-trash"></i></a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card card-outline card-primary">
            <div class="card-header font-weight-bold">
                Internal Report Weldmaps
                <div class="card-tools">
                    <button href="#" data-key="fv_no_external_date_wm" class="btn btn-primary btn-sm addNewWeldmap">Add New Weldmap</button>
                </div>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover table-bordered table-stripped datatables" id="matTable">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Weldmaps</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($internalWeldmaps as $no => $item)
                        <tr>
                            <td>{{ ++$no }}</td>
                            <td>{{ $item->value }}</td>
                            <td align="center">
                                <a href="#" class="btn btn-sm btn-danger delete-weldmap"
                                    data-id="{{ $item->id }}"><i class="fa fa-trash"></i></a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop

@push('js')
<script>
        $('.addNewWeldmap').on('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Input Weldmap',
                input: 'text',
                inputAttributes: {
                    autocapitalize: 'off'
                },
                showCancelButton: true,
                confirmButtonText: 'Submit',
                showLoaderOnConfirm: true,
                preConfirm: (weldmap) => {
                    if (!weldmap) {
                        Swal.showValidationMessage('Please enter a weldmap');
                        return false;
                    } else {
                        return $.ajax({
                            url: "{{ route('setting.projectsetting.store') }}",
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            data: JSON.stringify({
                                key: $(this).data('key'),
                                value: weldmap
                            }),
                            contentType: 'application/json',
                            dataType: 'json'
                        }).then(response => {
                            if (!response.status) {
                                throw new Error(response.message);
                            }
                            return response;
                        }).catch(error => {
                            Swal.showValidationMessage(`Request failed: ${error}`);
                        });
                    }
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.value.status == true) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'New weldmap has been added.',
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                }
            });
        });

        $(document).on('click', '.delete-weldmap', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                console.log(result);
                if (result.value) {
                    $.ajax({
                        url: '{{ route("setting.projectsetting.delete") }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            id: id
                        },
                        success: function(response) {
                            if (response.status) {
                                Swal.fire(
                                    'Deleted!',
                                    'Your weldmap has been deleted.',
                                    'success'
                                ).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire(
                                    'Error!',
                                    'There was an error deleting the weldmap.',
                                    'error'
                                );
                            }
                        }
                    });
                }
            });
        });

        initSelect2('wsmtprepare_id',"{{ route('api.select2.getusers') }}", 'Please Select Prepared By..', '{{ $project->wsmtprepare_id }}', '{{ $project->wsmtprepare_text }}');

        $('#mrr_id').select2({
            width: '100%',
            placeholder: 'Please Select MRR..',
            ajax: {
                url: "{{ route('api.select2.getmrr') }}",
                type: "get",
                dataType: 'json',
                delay: 5,
                data: function(params) {
                    return {
                        _token: "{{ csrf_token() }}",
                        project_id: "{{ session()->get('CQS_SELECTED_PROJECT') }}",
                        search: params.term
                    };
                },
                processResults: function(response) {
                    return {
                        results: response
                    };
                },
                cache: true
            }
        });

        $('#mvr_no').select2({
            width: '100%',
            placeholder: 'Please select MVR..',
            ajax: {
                url: "{{ route('api.select2.getmvrbymrr') }}",
                type: "get",
                dataType: 'json',
                delay: 5,
                data: function(params) {
                    return {
                        _token: "{{ csrf_token() }}",
                        _filter: $('#mrr_id').val(),
                        project_id: "{{ session()->get('CQS_SELECTED_PROJECT') }}",
                        search: params.term
                    };
                },
                processResults: function(response) {
                    return {
                        results: response
                    };
                },
                cache: true
            }
        }).prop('disabled', true); // Disable it initially

        // Listen for changes on #mrr_no
        $('#mrr_id').on('change', function() {
            var selectedMrr = $(this).val();

            $('#mvr_no').val(null).trigger('change');

            if (selectedMrr) {
                $('#mvr_no').prop('disabled', false);
            } else {
                $('#mvr_no').prop('disabled', true);
            }
        });

        $('#mvr_no').on('select2:select', function(e) {
            let selectedData = e.params.data;
            $('#from_mvr_no').val(selectedData.text);
        });

        $("#form-changemvr").on("submit", function(e) {
            e.preventDefault();
            let formData = new FormData(this);

            $.ajax({
                url: $(this).attr("action"),
                method: $(this).attr("method"),
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message,
                    }).then(() => {
                        window.location.reload();
                    });
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON.message || 'Something went wrong',
                    });
                }
            });
        });
</script>
@endpush
