@extends('adminlte::page')

@section('title', 'Update User')

@section('content_header')
    <h1 class="m-0 text-dark">Profile User</h1>
@stop

@section('content')

    @if (session('success_message'))
        <div class="alert alert-success">
            {{ session('success_message') }}
        </div>
    @endif

    <div id="errors"></div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <form id="upload-form" action="{{ route('profile.update') }}" method="POST"
                    enctype="multipart/form-data">
                    <div class="card-body">

                        @method('patch')
                        @csrf

                        <!-- Hidden input untuk simpan base64 -->
                        <input type="hidden" id="croppedSignature" name="signature">
                        <input type="hidden" id="croppedParaf" name="paraf">


                        <!-- Hidden input untuk simpan base64 -->
                        <div class="form-group row">
                            <div class="col">
                                <label for="inputName">Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    id="inputName" placeholder="Name" name="name"
                                    value="{{ $user->name ?? old('name') }}">
                                @error('name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col">
                                <label for="inputEmail">Email</label>
                                <input readonly type="email" class="form-control @error('email') is-invalid @enderror"
                                    id="inputEmail" placeholder="Email" name="email"
                                    value="{{ $user->email ?? old('email') }}">
                                @error('email')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group row">
                            {{-- <div class="col">
                            <label for="inputProjects">Project</label>
                            <select multiple class="form-control @error('welding_process') is-invalid @enderror" name="projects[]" id="inputProjects">
                                @foreach ($project_items as $project_item)
                                @if (in_array($project_item->id, $project_selecteds))
                                <option value="{{ $project_item->id }}" selected="true">{{ $project_item->project_name }}</option>
                                @else
                                <option value="{{ $project_item->id }}">{{ $project_item->project_name }}</option>
                                @endif
                                @endforeach
                            </select>
                            @error('projects') <span class="text-danger">{{$message}}</span> @enderror
                        </div> --}}

                            {{-- <div class="col">
                                <label for="inputCompany">Company</label>
                                <select class="form-control @error('company') is-invalid @enderror" name="company"
                                    id="inputCompany">
                                </select>
                                @error('company')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div> --}}
                            {{-- {{dd($user->is_active)}} --}}
                            <div class="col">
                                <label for="inputis_active">is active</label>
                                <select @can('is_admin') @else disabled @endcan autocomplete="off"
                                    class="form-control @error('is_active') is-invalid @enderror" name="is_active"
                                    id="inputis_active">
                                    <option value="1" @if ($user->is_active == 1) selected @endif>Active</option>
                                    <option value="0" @if ($user->is_active == 0) selected @endif>InActive
                                    </option>
                                </select>
                                @error('is_active')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col">
                                <label for="inputRole">Role</label>
                                <select @can('is_admin') @else disabled @endcan
                                    class="form-control @error('role') is-invalid @enderror" name="role" id="inputRole">
                                </select>
                                @error('role')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        {{-- <div class="form-group row">
                            <div class="col">
                                <label for="inputPassword">Password</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror"
                                    id="inputPassword" placeholder="Password" name="password">
                                @error('password')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col">
                                <label for="inputPassword">Confirm Password</label>
                                <input type="password" class="form-control" id="inputPassword"
                                    placeholder="Confirm Password" name="password_confirmation">
                            </div>
                        </div> --}}
{{-- {{dd(auth()->user(),auth()->user()->signature,auth()->user()->paraf)}} --}}
                        <div class="form-group row">
                            <div class="col">
                                <label for="inputSignature">Signature</label>
                                <input name="inputSignature" type="file" class="form-control @error('signature') is-invalid @enderror"
                                    id="inputSignature" accept="image/png, image/jpg, image/jpeg" />
                                @error('inputSignature')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                                <br>
                                <img id="previewSignature" class="img-fluid pad"
                                    src="{{ auth()->user()->signature ?? 'data:image/jpeg;base64,iVBORw0KGgoAAAANSUhEUgAAAJYAAABkBAMAAACWddTDAAAAG1BMVEXv7++qqqrd3d27u7vm5uaysrLMzMzDw8PV1dWB9FXoAAAACXBIWXMAAA7EAAAOxAGVKw4bAAABGElEQVRYhe3TsW6DMBSF4YMLgRGME1ZQ1b2RGrJe1D4AbUSUkTRiz1DEWpQ+eO0iunWwYnXJ/SQjT7+4lg0wxhhjjDHGGLtZlcPWs8PW9eT6CUIvM6Ps3+5T8vuUsNhVhIve2Fi+b1CUGzItvT88nMP9pcY4rClKu9qqlaGiAXetaWUoPkUd5L7CHkcKzjhYtSQ8kogef2bUXT8WEAoKBekpB6uWgvcSQ8SmpfTyY5xelf5WVCRJemUrzL5My6OxaXa2rd8Zp5ZHMDPq/yKr0tSaz35qVRQplPrsvRZb69Z8J6ZWUPYrFENCi+wkrVvzXZ1aovnoEDUjoUtzq9YfcodPfYveWeuYrJy1wmXrrMUYY4wxxtj/+gb7wytaOvUBxgAAAABJRU5ErkJggg==' }}" />
                            </div>

                            <div class="col">
                                <label for="inputParaf">Paraf</label>
                                <input name="inputParaf" type="file" class="form-control @error('paraf') is-invalid @enderror"
                                    id="inputParaf" accept="image/png, image/jpg, image/jpeg" />
                                @error('paraf')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                                <br>
                                <img id="previewParaf" class="img-fluid pad"
                                    src="{{ auth()->user()->paraf ?? 'data:image/jpeg;base64,iVBORw0KGgoAAAANSUhEUgAAAJYAAABkBAMAAACWddTDAAAAG1BMVEXv7++qqqrd3d27u7vm5uaysrLMzMzDw8PV1dWB9FXoAAAACXBIWXMAAA7EAAAOxAGVKw4bAAABGElEQVRYhe3TsW6DMBSF4YMLgRGME1ZQ1b2RGrJe1D4AbUSUkTRiz1DEWpQ+eO0iunWwYnXJ/SQjT7+4lg0wxhhjjDHGGLtZlcPWs8PW9eT6CUIvM6Ps3+5T8vuUsNhVhIve2Fi+b1CUGzItvT88nMP9pcY4rClKu9qqlaGiAXetaWUoPkUd5L7CHkcKzjhYtSQ8kogef2bUXT8WEAoKBekpB6uWgvcSQ8SmpfTyY5xelf5WVCRJemUrzL5My6OxaXa2rd8Zp5ZHMDPq/yKr0tSaz35qVRQplPrsvRZb69Z8J6ZWUPYrFENCi+wkrVvzXZ1aovnoEDUjoUtzq9YfcodPfYveWeuYrJy1wmXrrMUYY4wxxtj/+gb7wytaOvUBxgAAAABJRU5ErkJggg==' }}" />
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">

                        <button type="submit" class="btn btn-primary">Save</button>
                        {{-- <button onclick="saveForm()"  type="submit" class="btn btn-primary">Save
                            {{ config('SsoConfig.main.ACTIVE') ? 'with SSO' : '' }}</button> --}}
                        {{-- <a href="{{ url('api/users.index') }}" class="btn btn-default">
                            Cancel
                        </a> --}}
                    </div>
                </form>

                <!-- Modal for Cropping -->
                <div class="modal fade" id="cropModalSignature" tabindex="-1" role="dialog" aria-labelledby="cropModalLabel"
                    aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="cropModalLabel">Crop Image</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <img id="image-cropperSignature" src="" alt="Image" class="img-fluid">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-primary" id="cropSignature">Crop and Save</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Modal for Cropping -->
                <div class="modal fade" id="cropModalParaf" tabindex="-1" role="dialog" aria-labelledby="cropModalLabel"
                    aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="cropModalLabel">Crop Image</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <img id="image-cropperParaf" src="" alt="Image" class="img-fluid">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-primary" id="cropParaf">Crop and Save</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@stop

@push('css')
    <!-- Cropper CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
    <style>
        .cropper-container {
            max-width: 100%;
        }
    </style>
@endpush

@push('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>

    <script>
        $(document).ready(function() {


    var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

    var $modalSignature = $('#cropModalSignature');
    var $modalParaf = $('#cropModalParaf');

    var $previewSignature = $('#previewSignature');
    var $previewParaf = $('#previewParaf');

    var cropperSignature;
    var cropperParaf;

    // Trigger saat input file Signature diubah
    $('#inputSignature').on('change', function () {
        const file = this.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const $image = $('#image-cropperSignature');
            $image.attr('src', e.target.result);
            $modalSignature.modal('show');

            $modalSignature.on('shown.bs.modal', function () {
                if (cropperSignature) cropperSignature.destroy();
                cropperSignature = new Cropper($image[0], {
                    autoCropArea: 0.9,
                    viewMode: 1
                });
            });
        };
        reader.readAsDataURL(file);
    });

    // Trigger saat input file Paraf diubah
    $('#inputParaf').on('change', function () {
        const file = this.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const $image = $('#image-cropperParaf');
            $image.attr('src', e.target.result);
            $modalParaf.modal('show');

            $modalParaf.on('shown.bs.modal', function () {
                if (cropperParaf) cropperParaf.destroy();
                cropperParaf = new Cropper($image[0], {
                    autoCropArea: 0.9,
                    viewMode: 1
                });
            });
        };
        reader.readAsDataURL(file);
    });

    // Tombol crop signature
    $('#cropSignature').on('click', function () {
        const canvas = cropperSignature.getCroppedCanvas();
        const dataUrl = canvas.toDataURL('image/png');
        $('#croppedSignature').val(dataUrl); // untuk form submission
        $previewSignature.attr('src', dataUrl); // preview
        $modalSignature.modal('hide');
    });

    // Tombol crop paraf
    $('#cropParaf').on('click', function () {
        const canvas = cropperParaf.getCroppedCanvas();
        const dataUrl = canvas.toDataURL('image/png');
        $('#croppedParaf').val(dataUrl); // untuk form submission
        $previewParaf.attr('src', dataUrl); // preview
        $modalParaf.modal('hide');
    });

    // Submit form
    $('#upload-form').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('_token', CSRF_TOKEN);
        formData.append('_method', 'PATCH'); // kalau kamu update

        $.ajax({
            url: '{{ route("profile.update") }}',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            success: function (res) {
                Swal.fire('Success', 'Data berhasil diperbarui', 'success');
            },
            error: function (err) {
                console.error(err);
                Swal.fire('Error', 'Terjadi kesalahan saat menyimpan', 'error');
            }
        });
    });

    // Hapus cropper saat modal ditutup
    $modalSignature.on('hidden.bs.modal', function () {
        if (cropperSignature) {
            cropperSignature.destroy();
            cropperSignature = null;
        }
    });

    $modalParaf.on('hidden.bs.modal', function () {
        if (cropperParaf) {
            cropperParaf.destroy();
            cropperParaf = null;
        }
    });



            // $('#crop').on('click', function() {
            //     var canvas = cropper.getCroppedCanvas({
            //         // width: 400,
            //         // height: 400
            //     });

            //     // Convert the canvas to a base64 data URL and log it
            //     var dataUrlSignature = canvas.toDataURL('image/png', 0.9);
            //     var dataUrlParaf = canvas.toDataURL('image/png', 0.9);
            //     console.log('Cropped Image Data dataUrlSignature:', dataUrlSignature);
            //     console.log('Cropped Image Data dataUrlParaf:', dataUrlParaf);


            //     canvas.toBlob(function(blob) {
            //         var formData = new FormData($('#upload-form')[0]);
            //         formData.append('croppedImage', blob);
            //         formData.append('signature', dataUrlSignature);
            //         formData.append('paraf', dataUrlParaf);
            //         formData.append('_token', CSRF_TOKEN);


            //         console.log('formData:', formData);

            //         $.ajax('{{ route('profile.update') }}', {
            //             method: 'post',
            //             data: formData,
            //             processData: false,
            //             contentType: false,
            //             headers: {
            //                 'X-CSRF-TOKEN': CSRF_TOKEN
            //             },
            //             success: function(response) {
            //                 // Set the preview image src
            //                 $previewSignature.attr('src', dataUrlParaf);
            //                 // Handle success
            //                 console.log(response);
            //                 $modalSignature.modal('hide');
            //                 Swal.fire('Update success', '', 'info')
            //             },
            //             error: function(errorQ) {
            //                 $modalSignature.modal('hide');
            //                 if (errorQ.responseJSON.errors) {
            //                     let data = errorQ.responseJSON.errors;
            //                     peringatan(data, 'Pesan Kesalahan', errorQ);

            //                 } else {
            //                     let data = errorQ.responseJSON.message;
            //                     peringatan(data, 'Pesan Kesalahan', errorQ);
            //                     console.log('errorQ', errorQ);
            //                 }
            //             }
            //         });
            //     });
            // });



            // set location code in edit form
            var data = {
                id: '{{ $user->role_id }}',
                text: '{{ $user->role_name }}'
            };
            var newOption = new Option(data.text, data.id, false, false);
            $('#inputRole').append(newOption).trigger('change');

            var data = {
                id: '{{ @$user->company_id }}',
                text: '{{ @$user->company_name }}'
            };
            var newOption = new Option(data.text, data.id, false, false);
            $('#inputCompany').append(newOption).trigger('change');

            $('#inputProjects').select2({
                width: '100%',
                placeholder: 'Please select Projects',
                ajax: {
                    url: "{{ url('api/getprojectssbyparams') }}",
                    type: "get",
                    dataType: 'json',
                    delay: 5,
                    data: function(params) {
                        return {
                            _token: CSRF_TOKEN,
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

            $('#inputCompany').select2({
                width: '100%',
                placeholder: 'Please select company',
                ajax: {
                    url: "{{ url('api/getcompanybyparams') }}",
                    type: "get",
                    dataType: 'json',
                    delay: 5,
                    data: function(params) {
                        return {
                            _token: CSRF_TOKEN,
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

            $('#inputRole').select2({
                width: '100%',
                placeholder: 'Please select Role',
                ajax: {
                    url: "{{ url('api/getrolesbyparams') }}",
                    type: "get",
                    dataType: 'json',
                    delay: 5,
                    data: function(params) {
                        return {
                            'set[text]': "name",
                            _token: CSRF_TOKEN,
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
        });

        function saveForm() {
            // alert("{{ url('api/storechangepassword') }}");
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: "{{ url('api/storechangepassword') }}",
                type: "post",
                data: $('#upload-form').serialize(),
                success: function(data) {
                    alert(1);
                    Swal.fire("Done!", data.success, "success");
                    setInterval('history.back()', 1000);
                },
                error: function(data) {
                    // alert(2);
                    err =
                        '<div class="col-xs-12 alert alert-danger alert-dismissible" role="alert" id="errors">';
                    console.log(data.responseJSON.errors);
                    $.each(data.responseJSON.errors, function(key, item) {
                        $.each(item, function(index, value) {
                            err += value + '<br>';
                        });
                    });
                    // console.log(err);
                    err +=
                        '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a></div>';
                    $("#errors").append(err);

                    // Swal.fire("Failed!", err, "error");
                }
            });
        }
    </script>
@endpush
