@if (@$form['field'] == 'field_value' && @$formdata->field_type)
    @php
        $form['type'] = $formdata->field_type;
    @endphp
@endif
@switch($form['type'])
    @case('space')
        <div class="{{ $form['col'] }}">
        </div>
    @break

    @case('card')
        </div>
        <div class="card card-outline card-primary {{ $form['col'] }}">
    @break

    @case('date')
        <div class="{{ $form['col'] }}">
            <label for="{{ $form['field'] }}">{{ $form['label'] }}</label>
            <input type="hidden" value="{{ old($form['field'], @$formdata->{$form['field']}) }}"
                name="{{ $form['name'] ?? $form['field'] }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}">

            <input autocomplete="off" type="text" value="{{ old($form['field'], @$formdata->{$form['field']}) }}"
                {{ @$form['disabled'] == true ? 'data-disabled=true disabled=true' : null }}
                class="form-control datepicker @error($form['field']) is-invalid @enderror"
                name="{{ $form['name'] ?? $form['field'] }}" id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}" />

            @error($form['field'])
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    @break

    @case('datetime')
        <div class="{{ $form['col'] }}">
            <label for="{{ $form['field'] }}">{{ $form['label'] }}</label>
            <input type="hidden" value="{{ old($form['field'], @$formdata->{$form['field']}) }}"
                name="{{ $form['name'] ?? $form['field'] }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}">

            <input autocomplete="off" type="text" value="{{ old($form['field'], @$formdata->{$form['field']}) }}"
                {{ @$form['disabled'] == true ? 'data-disabled=true disabled=true' : null }}
                class="datetime form-control @error($form['field']) is-invalid @enderror"
                name="{{ $form['name'] ?? $form['field'] }}" id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}" />

            @error($form['field'])
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    @break

    @case('hidden')
        <input type="hidden" value="{{ old($form['field'], @$formdata->{$form['field']}) }}"
            name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}"
            id="{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}">
    @break

    @case('text')
        <div class="{{ $form['col'] }}">
            <label for="{{ $form['field'] }}">{{ $form['label'] }}</label>
            <input type="hidden" value="{{ old($form['field'], @$formdata->{$form['field']}) }}"
                name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}">

            <input autocomplete="off" type="text" value="{{ old($form['field'], @$formdata->{$form['field']}) }}"
                {{ @$form['readonly'] == true ? 'data-readonly=true readonly=true' : null }}
                {{ @$form['disabled'] == true ? 'data-disabled=true disabled=true' : null }}
                class="{{ 'input_' . $form['field'] }} form-control @error($form['field']) is-invalid @enderror"
                name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}" />

            @error($form['field'])
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    @break

    @case('label')
        <div class="{{ $form['col'] }}">
            {{-- {{dd($form,$form['label'])}} --}}
            <label for="{{ $form['field'] }}">{{ $form['label'] }}</label>
            {{-- <input type="hidden" value="{{ old($form['field'], @$formdata->{$form['field']}) }}" name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}" id="{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}"> --}}
            <input autocomplete="off" type="text" value="{{ old($form['field'], @$formdata->{$form['field']}) }}" disabled
                class="form-control-plaintext @error($form['field']) is-invalid @enderror"
                name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}" />
            {{-- @error($form['field']) <span class="text-danger">{{ $message }}</span> @enderror --}}
        </div>
    @break

    @case('number')
        <div class="{{ $form['col'] }}">
            <label for="{{ $form['field'] }}">{{ $form['label'] }}</label>
            <input type="hidden" value="{{ old($form['field'], @$formdata->{$form['field']}) }}"
                name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}">

            <input onkeypress="return validateInputNumber(event)" maxLength=1000 autocomplete="off" value="{{ old($form['field'], @$formdata->{$form['field']}) ?? 0 }}"
                {{ @$form['disabled'] == true ? 'data-disabled=true disabled=true' : null }}
                class="form-control @error($form['field']) is-invalid @enderror" name="{{ $form['name'] ?? $form['field'] }}"
                data-id="{{ @$formdata->id }}" min="{{ $form['min'] ?? 0 }}" max="{{ $form['max'] ?? 10000 }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}" />

            @error($form['field'])
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    @break

    @case('attachment')
        <div class="{{ $form['col'] }}">

            <div class="modal-body">
                <div class="mb-1">
                    <label for="{{ $form['field'] }}">{{ $form['label'] }}</label>
                    <div class="attachments">
                        <input type="hidden" name="app" value={{ $data['page']['sheet_name'] }} />
                        <input id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}"
                            accept="{{ @$data['attachment']['file'][$filetype] }}" type="file"
                            class="form-control attachment-file" name="attachment" multiple>
                    </div>
                    {{-- <a href="#" class="add-input mt-1">Add New File</a> --}}
                    @error('attachment')
                        <span class="text-danger">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            {{-- <label for="{{ $form['field'] }}">{{ $form['label'] }}</label>
            <input type="hidden" value="{{ old($form['field'], @$formdata->{$form['field']}) }}"
                name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}">

            <input type="number" inputmode="numeric" pattern="\d*"
                value="{{ old($form['field'], @$formdata->{$form['field']}) }}"
                {{ @$form['disabled'] == true ? 'data-disabled=true disabled=true' : null }}
                class="form-control @error($form['field']) is-invalid @enderror" name="{{ $form['name'] ?? $form['field'] }}"
                data-id="{{ @$formdata->id }}" min="{{ $form['min'] ?? 0 }}" max="{{ $form['max'] ?? 10000 }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}" />

            @error($form['field'])
                <span class="text-danger">{{ $message }}</span>
            @enderror --}}
        </div>
    @break

    @case('textarea')
        <div class="{{ $form['col'] }}">
            <label for="{{ $form['field'] }}">{{ $form['label'] }}</label>
            <input type="hidden" value="{{ old($form['field'], @$formdata->{$form['field']}) }}"
                name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}">

            <textarea maxlength="255" onkeypress="return validateInputText(event)" autocomplete="off"
                rows="{{ $form['rows'] ?? 3 }}" placeholder="{{ @$form['placeholder'] }}"
                {{ @$form['disabled'] == true ? 'data-disabled=true disabled=true' : null }}
                class="form-control @error($form['field']) is-invalid @enderror" name="{{ $form['name'] ?? $form['field'] }}"
                name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}">{!! trim(old($form['field'], @$formdata->{$form['field']})) !!}</textarea>

            @error($form['field'])
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    @break

    @case('select2')
        <div class="{{ $form['col'] }}">
            <label for="{{ $form['field'] }}">{{ $form['label'] }}</label>
            <input type="hidden" value="{{ old($form['field'], @$formdata->{$form['field']}) }}"
                name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}" autocomplete="off">

            <select type="select2" {{ @$form['multi'] == true ? 'multiple="multiple"' : null }} autocomplete="off"
                {{ @$form['disabled'] == true ? 'data-disabled=true disabled=true' : null }}
                class="form-control select2-{{ $form['field'] }} @error($form['field']) is-invalid @enderror"
                name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}">
            </select>

            @error($form['field'])
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    @break

    @case('select')
        <div class="{{ $form['col'] }}">
            <label for="{{ $form['field'] }}">{{ $form['label'] }}</label>
            <input type="hidden" value="{{ old($form['field'], @$formdata->{$form['field']}) }}"
                name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}">

            <select autocomplete="off" {{ old($form['field'], @$formdata->form_method) == 'po' ? 'disabled' : '' }}
                {{ @$form['disabled'] == true ? 'data-disabled=true disabled=true' : null }}
                class="form-control @error($form['field']) is-invalid @enderror"
                name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}">
                @foreach ($form['option'] as $itemK => $itemV)
                    <option value="{{ is_numeric($itemK) ? $itemV : $itemK }}"
                        {{ old($form['field'], @$formdata->{$form['field']}) == (is_numeric($itemK) ? $itemV : $itemK) ? 'selected' : '' }}>
                        {{ $itemV }}
                    </option>
                @endforeach
            </select>

            @error($form['field'])
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    @break

    @case('checbox')
        <div class="{{ $form['col'] }}">
            <label for="{{ $form['field'] }}">{{ $form['label'] }}</label>
            <input type="hidden" value="0" name="{{ $form['name'] ?? $form['field'] }}"
                data-id="{{ @$formdata->id }}" id="{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}">

            <input autocomplete="off" type="checkbox" id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}"
                class="form-control @error($form['field']) is-invalid @enderror"
                name="{{ $form['name'] ?? $form['field'] }}" name="{{ $form['name'] ?? $form['field'] }}" value="1"
                @if (!empty(@$formdata->{$form['field']})) checked=true @endif>



            @error($form['field'])
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>
    @break

    @case('image')
        <div class="{{ $form['col'] }}">

            <div class="modal-body">
                <div class="mb-1">
                    <label for="image">image</label>
                    <div class="images">
                        <img name="{{ $form['name'] ?? $form['field'] }}" data-id="{{ @$formdata->id }}"
                            id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}" src="{{ @$formdata->url }}"
                            class="img-fluid">
                    </div>

                </div>
            </div>

        </div>
    @break

    @case('tinymce')
        <div class="{{ $form['col'] }}">
            <label for="{{ $form['field'] }}">{{ $form['label'] }}</label>
            <textarea type="tinymce" autocomplete="off" rows="{{ $form['rows'] ?? 3 }}"
                name="{{ $form['name'] ?? $form['field'] }}" class="tinymceEditor"
                id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}">{{ old($form['field'], @$formdata->{$form['field']}) }}</textarea>

            {{-- <textarea autocomplete="off" rows="{{ $form['rows'] ?? 3 }}" placeholder="{{ @$form['placeholder'] }}"
                {{ @$form['disabled'] == true ? 'data-disabled=true disabled=true' : null }}
                class="tinymce form-control @error($form['field']) is-invalid @enderror"
                name="{{ $form['name'] ?? $form['field'] }}" name="{{ $form['name'] ?? $form['field'] }}"
                data-id="{{ @$formdata->id }}" id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}">
                {{ old($form['field'], @$formdata->{$form['field']}) }}
            </textarea> --}}

        </div>

        <div class="modal fade" id="file-manager-modal" tabindex="-1" aria-labelledby="fileManagerLabel"
            aria-hidden="true" style="z-index: 1500;">
            <div class="modal-dialog" style="max-width: 80%;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="fileManagerLabel">File Manager</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body" id="file-manager-body" style="height: 65vh; overflow-y: scroll;">

                    </div>
                    <div class="modal-footer justify-content-between">
                        <input type="hidden" id="offset-value" value="0">
                        <input type="hidden" id="data-total" value="0">
                        <div>
                            <button type="button" class="btn btn-info" onclick="getImages('prev')">Prev</button>
                            <button type="button" class="btn btn-info" onclick="getImages('next')">Next</button>
                        </div>
                        <div>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            {{-- <button type="button" class="btn btn-primary">Save changes</button> --}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @break

    @case('custom')
        @include('master::'.$form['file'], ['data'=>$form])
    @break

    {{-- hapus sudah ada submit globall --}}
    @case('submit')
        @if (@$form['disabled'] == false )
            <div class="{{ $form['col'] }}">
                <button type="submit" class="submitButton btn btn-primary">
                    {{ $form['label'] }}
                </button>
            </div>
        @endif
    @break

    @case('save')
        <div class="{{ $form['col'] }}">
            <button {{ @$form['disabled'] == true ? 'data-disabled=true disabled=true' : null }} type="submit"
                class="saveButton btn btn-primary">
                {{ $form['label'] }}
            </button>
        </div>
    @break

    @default
@endswitch

@section('css')
    <style>
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #007bff !important;
        }

        .select2-container--default .select2-selection--single {
            height: calc(2.25rem + 2px) !important;
        }
    </style>
@endsection

@section('js')
    <script>
        // fungsi untuk callback setelash save
        function update_from_data(data) {
            $.each(data, function(key, input) {
                /*cari filed date dan suffix _id*/
                if (key.indexOf("ted_at") != -1 || key.indexOf("_id") != -1) {
                    // alert(key+key.indexOf("_id"));
                    // console.log("key, input::", key, input);
                } else {
                    // console.log("input" + data.id + "_" + key);
                    if ($("#input" + data.id + "_" + key).length) {
                        var attrs = document.getElementById("input" + data.id + "_" + key).attributes;
                        $.each(attrs, function(i, elem) {
                            // $("#attrs").html($("#attrs").html() + "<br><b>" + elem.name + "</b>:<i>" + elem
                            //     .value + "</i>");
                            if (elem) {
                                if (elem.name == 'value' && input) {
                                    $("#input" + data.id + "_" + key).val(input);
                                }
                                if (elem.name == 'src' && input) {
                                    $("#input" + data.id + "_" + key).attr("src", input);
                                }
                                console.log(key + "==>attr::" + elem.name, elem.value)
                            }
                        });
                    }


                }
            });
            // input241_remarks
        }

        $(document).ready(function() {

            $('.datepicker').datepicker({
                gotoCurrent: true,
                dateFormat: "yy-mm-dd",
            });

            // var dpStart = $("#datetime").datepicker({
            //     autoclose: true,
            //     format: "yyyy-mm",
            //     startView: "months",
            //     minViewMode: "months"
            // });
            $(".datetime").datetimepicker({
                timePicker: true,
                timePickerIncrement: 30,
                // format: 'd/m/Y h:i A'
                format: 'Y-m-d H:i:s'
            })

        });

        $(".saveButton").click(function(e) {

            e.preventDefault();

            var data = new FormData();

            var form_data = $(this.form).serializeArray();
            var id_form = null;
            $.each(form_data, function(key, input) {
                if (input.name == 'id') {
                    id_form = input.value;
                }
                // tinyMCE.get('tinymceEditor').getContent();
                data.append(input.name, input.value);
            });
            //File data
            var file_data = $('#input' + id_form + '_attachment');
            for (var i = 0; i < file_data.length; i++) {
                data.append("attachment[]", file_data[i].files[0]);
            }

            //Custom data
            // data.append('key', 'value');

            $.ajax({
                type: 'post',
                url: "{{ $data['page']['store'] }}",
                data: data,
                dataType: "JSON",
                contentType: false,
                processData: false,
                cache: false,
                beforeSend: function() {
                    alert(window.isFormChanged);
                    $.LoadingOverlay("show");
                },
                complete: function(data) {
                    $.LoadingOverlay("hide");
                },
                success: function(result) {
                    var data_return = result.data;
                    update_from_data(data_return);

                    $(document).Toasts('create', {
                        class: 'bg-success',
                        title: 'Update',
                        subtitle: result.data.updated_at,
                        delay: 3000,
                        autohide: true,
                        fade: true,
                        body: result.success_message
                    })
                },
                error: function(xhr, status, error) {
                    $.LoadingOverlay("hide");
                    console.log("data error::", xhr, status, error);
                    $(document).Toasts('create', {
                        class: 'bg-danger',
                        title: status,
                        subtitle: xhr.status,
                        delay: 3000,
                        autohide: true,
                        fade: true,
                        body: error
                    })
                }
            });

        });

        $(".submitButton").click(function(e) {
            e.preventDefault();

            $('form').each(function(e) {
                var data = new FormData();

                //Form data
                var form_data = $(this).serializeArray();
                var id_form = null;
                $.each(form_data, function(key, input) {
                    if (input.name == 'id') {
                        id_form = input.value;
                    }
                    console.log("e::", e);
                    console.log("form_data::", form_data);
                    console.log("key::", key);
                    console.log("input::", input);

                    console.log('attr::', $("#input" + id_form + "_" + input.name).attr("type"))

                    let type_form = $("#input" + id_form + "_" + input.name).attr("type");
                    if (type_form == 'tinymce') { //samu hardcode harus dinamis namenya
                        // alert(type_form);
                        let tinymceData = tinyMCE.get("input" + id_form + "_" + input.name)
                            .getContent();
                        // let tinymceDat = tinyMCE.editors[$("#input" + id_form + "_" + input.name)
                        //     .attr('id')].getContent()
                        data.append(input.name, tinymceData);
                    }
                    //agar data yang di kirim jadi array by koma
                     else if (type_form == 'select2') { //samu hardcode harus dinamis namenya
                        // alert(type_form);
                        let select2Data = $("#input" + id_form + "_" + input.name).val();
                        if (select2Data) {
                            let select2DataString = ($("#input" + id_form + "_" + input.name).val())
                                .toString;
                            data.append(input.name, select2Data);
                        }
                        console.log(type_form + "::", select2Data);

                    }
                     else {
                        data.append(input.name, input.value);
                    }
                });
                //File data
                var file_data = $('#input' + id_form + '_attachment');
                for (var i = 0; i < file_data.length; i++) {
                    data.append("attachment[]", file_data[i].files[0]);
                }

                var form = $(this);
                console.log("form.attr::",form.attr('action'));
                if (form.attr('action')  ==
                    "{{ $data['page']['store'] }}"
                ) {
                    $.ajax({
                        type: 'post',
                        url: form.attr('action'),
                        data: data,
                        dataType: "JSON",
                        contentType: false,
                        processData: false,
                        cache: false,
                        beforeSend: function() {
                            $.LoadingOverlay("show");
                        },
                        complete: function(data) {
                            $.LoadingOverlay("hide");
                        },
                        success: function(result) {
                            localStorage.clear();
                            $.LoadingOverlay("hide");
                            var data_return = result.data;
                            console.log("data::", result);
                            // console.log("data_return::",data_return);
                            // console.log("id_form::",id_form);
                            update_from_data(data_return);
                            // Reset the flag on form submit
                            window.isFormChanged = false;
                            if (data_return) {
                                let bg_return = result.code == 200 ? 'bg-success' : 'bg-danger';
                                $(document).Toasts('create', {
                                    class: bg_return,
                                    title: 'Update',
                                    subtitle: data_return.updated_at,
                                    delay: 3000,
                                    autohide: true,
                                    fade: true,
                                    body: result.success_message
                                })
                            }
                            console.log("cek redirect::", result.redirect,
                                "{{ url()->current() }}");
                            console.log("id_form::", id_form);

                            if (result.redirect != "{{ url()->current() }}") {
                                // window.location.assign(result.redirect);
                                window.open(
                                    result.redirect,
                                    '_parent'
                                );
                            }

                            if (id_form.length <= 0 && id_form) {
                                window.location.assign(result.redirect);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log("json::", xhr.responseJSON);
                            console.log("json.message::", xhr.responseJSON.message);
                            console.log("json.errors::", xhr.responseJSON.errors);
                            // console.log("error::", xhr.responseJSON.errors);
                            var list_error = xhr.responseJSON.errors;
                            var message = xhr.responseJSON.message;
                            $.LoadingOverlay("hide");

                            $.each(list_error, function(key, value) {
                                console.log(key + ": " + value);
                                let pesan_error = key + ": " + value;
                                $(document).Toasts('create', {
                                    class: 'bg-danger',
                                    title: status,
                                    subtitle: xhr.status,
                                    delay: 3000,
                                    autohide: true,
                                    fade: true,
                                    body: pesan_error
                                })
                            });


                        }
                    });
                } else {
                    console.warn("action not found::", form.attr('action') + "-json",
                        "{{ $data['page']['store'] }}");
                }

            });

        });

        @if (Config::get('adminlte.plugins.TinyMce.active'))
            tinymce.init({
                selector: '.tinymceEditor', // Replace this CSS selector to match the placeholder element for TinyMCE
                plugins: 'code table lists',
                toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | indent outdent | bullist numlist | code | table | customFileManager',
                setup: (editor) => {
                    @if (@$data['page']['sheet_slug'] != 'attachment')
                        editor.ui.registry.addToggleButton('customFileManager', {
                            icon: 'gallery',
                            onAction: (api) => {
                                getImages('');
                            }
                        });
                    @endif
                },
            });
        @endif


        function getImages(action) {
            var offset = parseInt($('#offset-value').val());
            var data_total = parseInt($('#data-total').val());
            if (action == 'next') {
                offset += 20;
            } else if (action == 'prev') {
                offset -= 20;
            }

            if (offset >= 0 && offset <= data_total) {
                $('#offset-value').val(offset);
                $('#file-manager-body').empty();

                ajaxGetImages(offset);
            }
        }

        @if (Route::has('getimagesbyparams'))
        function ajaxGetImages(offset) {
            $.ajax({
                type: 'get',
                url: "{{ route('getimagesbyparams') }}",
                data: {
                    offset: offset
                },
                dataType: "JSON",
                success: function(result) {
                    var datas = result.data;
                    var data = datas['data'];
                    var data_total = datas['data_total'];
                    // console.log(datas, datas['data_total']);
                    $('#data-total').val(data_total);
                    var dataContent = '<div class="row">';
                    data.forEach(data => {
                        dataContent += `

                                <div class="col-sm-3 col-md-2">
                                    <img class="img-fluid" src="${data.url}" alt="Carousel Image" onclick="appendImageToText(event,this)" style="cursor: pointer"/>
                                    <p class="text-center" style="font-size: 10px;">${data.filename}</p>
                                </div>

                        `;
                    });
                    dataContent += '</div>';

                    $('#file-manager-body').append(dataContent);
                    $("#file-manager-modal").modal('show');
                },
                error: function(xhr, status, error) {
                    console.log(xhr, status, error);
                }
            });
        }
        @endif

        function appendImageToText(event, el) {
            var imageUrl = $(el).attr('src');

            var text = `<img src="${imageUrl}" style="width: 500px"/>`
            tinymce.activeEditor.execCommand('mceInsertContent', false, text);
            $("#file-manager-modal").modal('hide');
        }

        $("#file-manager-modal").on('hide.bs.modal', function() {
            $('#file-manager-body').empty();
            $('#offset-value').val(0);
        });

        function validateInputNumber(event) {
            const regex = /[0-9.,]/i;
            const key = String.fromCharCode(event.which || event.keyCode);

            // Memastikan karakter yang dimasukkan sesuai dengan regex
            if (!regex.test(key)) {
                event.preventDefault();
                return false;
            }

            // Memastikan panjang karakter tidak melebihi batas maksimal
            const input = event.target;
            console.log("input.value.length", input.value.length);
            console.log("input.maxLength", input.maxLength);
            if (input.value.length >= input.maxLength) {
                event.preventDefault();
                return false;
            }

            return true;
        }

        function validateInputText(event) {
            const regex = /[0-9.,A-Za-z ]/i;
            const key = String.fromCharCode(event.which || event.keyCode);

            // Memastikan karakter yang dimasukkan sesuai dengan regex
            if (!regex.test(key)) {
                event.preventDefault();
                return false;
            }

            // Memastikan panjang karakter tidak melebihi batas maksimal
            const input = event.target;
            if (input.value.length >= input.maxLength) {
                event.preventDefault();
                return false;
            }

            return true;
        }
    </script>
@stop
