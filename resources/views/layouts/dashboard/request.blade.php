@extends('adminlte::page')

@section('title', @$data['page']['title'])

@section('content_header')
    @include('master::layouts.dashboard.navbar', ['data' => @$data])
@stop

@section('content')
    @if (session('error_message'))
        <div class="row">
            <div class="col-xs-12 alert alert-danger alert-dismissible" role="alert">
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
        <div class="alert alert-success">
            {!! session('success_message') !!}
        </div>
    @endif

    @foreach ($formdata_multi as $keydata => $formdata)
        <form id="FormRequest{{ @$formdata->id }}" action="{{ $data['page']['store'] }}" method="POST" autocomplete="off">
            @csrf
            <div class="card card-outline card-primary">

                @isset($data['page']['form']['title'])
                    <div class="card-header">
                        <span class="card-title h3" style="font-weight:bold;">{!! $data['page']['form']['title'] !!}</span>
                    </div>
                @endisset

                <div class="card-body row">
                    <!-- GENERATE hidden input froreing key id -->
                    @isset($foreing_key)
                        @foreach ($foreing_key as $key_name => $key_id)
                            <input type="hidden" name="{{ $key_name }}" value="{{ $formdata->{$key_name} ?? $key_id }}">
                        @endforeach
                    @endisset
                    <!-- GENERATE FORM -->
                    @foreach ($view_form as $key => $form)
                        @php
                            if ($key == 1) {
                                // $data['setForm']['auto_status']['type']='select';
                                if (isset($data['setForm'][$formdata->name])) {
                                    $form['type'] = $data['setForm'][$formdata->name]['type'];
                                    $form['option'] = @$data['setForm'][$formdata->name]['option'] ?? [];
                                    // dd($form['type']);
                                }
                            }
                        @endphp
                        @include('master::layouts.dashboard.view_form', [
                            'form' => $form,
                            'formdata' => $formdata,
                        ])
                    @endforeach
                </div>
                {{-- {{dd(empty(search($view_form,'type','submit')))}} --}}
                {{-- {{dd($view_form)}} --}}
                @if (count($formdata_multi) > 1 && empty(search($view_form, 'type', 'submit')) && $form['disabled'] == false)
                    <div class="card-footer">
                        <button class="saveButton btn btn-primary">Save</button>
                        {{-- <a href="{{ route('hse-dar.' . $data['page']['url_prefix'] . '.index') }}" class="btn btn-default">Back</a> --}}
                    </div>
                @endif
            </div>
        </form>
    @endforeach

    @if (count($formdata_multi) > 1 && empty(search($view_form, 'type', 'submit')) && $form['disabled'] == false)
        <div class="card-footer">
            <button type="submit" class="submitButton btn btn-primary">Submit</button>
            {{-- <a href="{{ route('hse-dar.' . $data['page']['url_prefix'] . '.index') }}" class="btn btn-default">Back</a> --}}
        </div>
    @endif

@stop

@push('js')
    @if (isset($data['page']['js']))
        @include($data['page']['js'])
    @endif

    <script>
        $('#inputform_type').val('');
        $('#inputform_method').empty();
        @if (strpos('A|submit|close', @$formdata->status ?? '') || @$formdata->status_id > 0)
            $("#FormRequest :input").prop("disabled", true);
        @endif

        $(document).ready(function() {
            //jika buat form baru hapus lokal storage
            @if(empty(@$formdata->id))
                localStorage.clear();
            @endif
        });



        @foreach ($view_form as $form)
            @if (@$form['multi'] == true)
                var implodeData = [];
                var vals = ($("#{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}").val());
                if (vals) {
                    vals = vals.split(',');
                } else {
                    vals = [];
                }
                //  .split( ',');
                @if (@$formdata->id)
                    // console.log(vals);
                    vals.forEach(function(e) {
                        implodeData.push(e.trim());
                        if (!$("#{{ 'input' . $formdata->id . '_' . $form['field'] }}").find(
                                'option:contains(' + e + ')').length)
                            $("#{{ 'input' . $formdata->id . '_' . $form['field'] }}").append($(
                                '<option>').text(e));
                    });
                @endif
            @else
                var implodeData = ($("#{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}")
                    .val());
            @endif
            @if ($form['type'] == 'select2')
                // alert('{{ $form['field'] }}');
                var config_{{ $form['field'] }} = {
                    // dropdownCssClass: "select2-custom-dropdown",
                    width: '100%',
                    placeholder: 'Please select type',
                    ajax: {
                        url: "{!! $form['select2_url'] ?? '#' !!}",
                        // url: "{!! config('AKTConfig.master.SELECT2_MASTER') . '/api/gettaxonomybyparams?set[text]=name' !!}",
                        type: "get",
                        dataType: 'json',
                        delay: 300,
                        data: function(params) {
                            return {
                                _token: "{{ csrf_token() }}",
                                @if (is_array(@$form['select2_search']))
                                    @foreach ($form['select2_search'] as $cari)
                                        @if (is_array($cari))
                                            'search[{{ array_values($cari)[0] }}][{{ array_keys($cari)[0] }}]': params
                                                .term,
                                        @else
                                            'search[{{ $cari }}][|]': params.term,
                                        @endif
                                    @endforeach
                                @else
                                    'search[{!! @$form['select2_search'] ? $form['select2_search'] : 'name' !!}]': params.term
                                @endif

                            };
                        },
                        processResults: function(response) {
                            return {
                                results: response
                            };
                        },
                        cache: true
                    },
                    placeholder: '{{ $form['placeholder'] ?? 'Search for an item' }}',
                    minimumInputLength: {{ $form['select2_minimum'] ?? 2 }},
                    maximumSelectionLength: {{ $form['select2_maximum'] ?? 4 }},
                };
                config_{{ $form['field'] }}.tags = {{ $form['select2_tags'] === true ? 'true' : 'false' }};

                function select2_{{ $form['field'] }}(config_{{ $form['field'] }}) {
                    $('.select2-{{ $form['field'] }}').select2(
                            config_{{ $form['field'] }}
                        )
                        .on('select2:select', function(e) {
                            var data = e.params.data;
                            var data_id = $(this).attr('data-id');
                            $("#input" + data_id + "_type").val(data.text);
                            if (data_id) {
                                // $("#input"+data_id+"type").prop('disabled', false);
                                // if (document.getElementById('inputposition').getAttribute('data-disabled') ==
                                //     "true") {
                                //     $("#inputposition").prop('disabled', true);
                                // };
                            } else {
                                // $("#inputposition").prop('disabled', false);
                            }
                        });
                }
                @if (@$form['multi'] == false)
                    //menyebabkan bug doble data
                    // $("#{{ 'input' . @$formdata->id . '_' . $form['field'] }}").append($('<option>').text(implodeData));
                @endif
                //buat update data form select2
                console.log('implodeData {{ $form['field'] }}:', implodeData);
                $("#{{ 'input' . @$formdata->id . '_' . $form['field'] }}").val(implodeData).trigger("change");
                //buat triger get api select2 dropdown list
                select2_{{ $form['field'] }}(config_{{ $form['field'] }});
            @endif
        @endforeach


        // config_employee_id.tags = true;
        // config_company_id.tags = true;
        // select2_company_id(config_company_id);
        @foreach ($formdata_multi as $keydata => $formdata)
            @foreach ($formdata as $keyForm => $valForm)
                @if (@$view_form[$keyForm]['type'] == 'select2')
                    {{-- dd($valForm,$formdata,$formdata_multi, $view_form[$keyForm] ) --}}
                    @php
                        echo '/*' . $keyForm . 'test aja' . $valForm . $view_form[$keyForm]['select2_url'] . '*/';
                    @endphp


                    function getSelect2_{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }}(id) {
                        console.log(
                            "getSelect2_{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }}::get");

                        var data_storage_select2 = localStorage.getItem(
                            '{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }}');
                        console.log(
                            'data_storage_select2.{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }}::',
                            data_storage_select2);
                        if (data_storage_select2) {
                            let data = JSON.parse(data_storage_select2);
                            let selected_form_name = $(
                                "#{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }}");
                            let def_id = data[0].id;
                            let def_val = data[0].text;
                            let def_type = data[0].type;

                            selected_form_name.val(null).trigger('change');
                            var option = new Option(def_val, def_id, true, true);
                            selected_form_name.append(option).trigger('change');

                            // $("#{{ 'input' . @$formdata->id . '_type' }}").html(def_type);

                            /*call back FN select2*/
                            console.log(
                                'FN::{{ 'callbackSelect2_input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }}',
                                typeof callbackSelect2_{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }} ===
                                "function");
                            if (typeof callbackSelect2_{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }} ===
                                "function") {
                                callbackSelect2_{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }}
                                    (data[0]);
                            }
                        } else {
                            $.getJSON(
                                "{!! $view_form[$keyForm]['select2_url'] !!}", {
                                    _token: $('meta[name="csrf-token"]').attr('content'),
                                    "id": id,
                                },
                                function(data) {

                                    if (data[0]) {
                                        localStorage.setItem(
                                            '{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }}',
                                            JSON.stringify(data));
                                        // samu-cek {{ $view_form[$keyForm]['select2_url'] }} {-- dd($formdata,$view_form[$keyForm]['field']) --}
                                        let selected_form_name = $(
                                            "#{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }}"
                                        );
                                        let def_id = data[0].id;
                                        let def_val = data[0].text;
                                        let def_type = data[0].type;
                                        // console.log('data::', data);


                                        selected_form_name.val(null).trigger('change');
                                        var option = new Option(def_val, def_id, true, true);
                                        selected_form_name.append(option).trigger('change');

                                        // $("#{{ 'input' . @$formdata->id . '_type' }}").html(def_type);

                                        /*call back FN select2*/
                                        console.log(
                                            'FN::{{ 'callbackSelect2_input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }}',
                                            typeof callbackSelect2_{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }} ===
                                            "function");
                                        if (typeof callbackSelect2_{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }} ===
                                            "function") {
                                            callbackSelect2_{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }}
                                                (data[0]);
                                        }

                                    }
                                }
                            );
                        }

                    }
                    @if (!empty($valForm))
                        //buat auto default value di select2
                        getSelect2_{{ 'input' . @$formdata->id . '_' . $view_form[$keyForm]['field'] }}(
                            {{ $valForm }});
                    @endif
                @endif
            @endforeach
        @endforeach
    </script>
@endpush
