<div class="{{ $form['col'] }} mb-2">
    <label for="{{ $form['field'] }}">{{ $form['label'] }}</label>
    <input type="hidden" value="{{ old($form['field'], @$formdata->{$form['field']}) }} " name="{{ $form['field'] }}"
        id="{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}">

    <div class="row" id="div-{{ $form['field'] }}">
    </div>

    <div class="row" id="div2-{{ $form['field'] }}">
    </div>

    @error($form['field'])
        <span class="text-danger">{{ $message }}</span>
    @enderror
</div>

@push('js')
    <script>
        var index_{{ $form['field'] }} = 0;
        var global_disable = '{{ @$form['disabled'] }}';

        function appendInputSelect2{{ $form['field'] }}(position = '', disabled = false) {

            $("#div" + position + "-{{ $form['field'] }}").append(`
            <div class="col-12 mb-2" id="div-input-{{ $form['field'] }}-${index_{{ $form['field'] }}}">
                <div class="row" id="row-input-{{ $form['field'] }}-${index_{{ $form['field'] }}}">
                    <div class="col">
                        <select ${global_disable == true ? 'disabled' : disabled == true ? 'disabled' : ''} type="select2_custom" class="select2_{{ $form['field'] }}_${index_{{ $form['field'] }}}" id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}">

                        </select>
                    </div>
                </div>
            </div>
        `);

            if (!disabled && !global_disable) {
                if (index_{{ $form['field'] }} == 0) {
                    $("#row-input-{{ $form['field'] }}-" + index_{{ $form['field'] }}).append(`

                    <div class="col-1 d-flex justify-content-end">
                        <button type="button" class="btn btn-primary btn-sm" id="btn-append-input-select2" onclick="appendInputSelect2{{ $form['field'] }}()"><i class="fas fa-plus"></i></button>
                    </div>
                `);
                } else {
                    $("#row-input-{{ $form['field'] }}-" + index_{{ $form['field'] }}).append(`
                    <div class="col-1 d-flex justify-content-end" id="div-btn-remove-input-{{ $form['field'] }}-${index_{{ $form['field'] }}}">
                        <button type="button" class="btn btn-danger btn-sm" id="btn-append-input-select2"
                            onclick="removeInput{{ $form['field'] }}('{{ $form['field'] }}', ${index_{{ $form['field'] }}},${position})"><i class="fas fa-times"></i></button>
                    </div>
                `);
                }
            }

            initSelect2{{ $form['field'] }}("{{ $form['field'] }}", index_{{ $form['field'] }});
            index_{{ $form['field'] }}++;
        }

        function initSelect2{{ $form['field'] }}(field, index) {

            $(".select2_" + field + "_" + index).select2({
                    ajax: {
                        url: "{!! $form['select2_url'] ?? '#' !!}",
                        type: "get",
                        dataType: 'json',
                        delay: 300,
                        data: function(params) {
                            return {
                                "search[name]": params.term
                            };
                        },
                        processResults: function(response) {
                            return {
                                results: response
                            };
                        },
                        cache: true
                    },
                    placeholder: 'Search for an item',
                    width: '100%'
                })
                .on('select2:select', function(e) {
                    var value = $(e.currentTarget).val();
                    update_{{ $form['field'] }}_value();
                });
        }

        @foreach ($formdata_multi as $keydata => $formdata)
            var {{ $form['field'] }}_field = [];
            {{ $form['field'] }}_field[0] = {}; //sequence
            {{ $form['field'] }}_field[1] = {}; //end
            @foreach ($formdata as $keyForm => $valForm)
                var keyForm = '{{ $keyForm }}';

                if (keyForm.includes('{{ $form['field'] }}end')) {
                    var id = keyForm.substring('{{ $form['field'] }}end'.length, keyForm.length);
                    {{ $form['field'] }}_field[1][id] = parseInt('{{ $valForm }}');
                } else if (keyForm.includes('{{ $form['field'] }}')) {
                    var id = keyForm.substring('{{ $form['field'] }}'.length, keyForm.length);
                    {{ $form['field'] }}_field[0][id] = parseInt('{{ $valForm }}');
                }
            @endforeach
        @endforeach

        @if (@$formdata->id)
            var formdata = @json($formdata);

            var {{ $form['field'] }}_data = formdata.{{ $form['field'] }}.split(',');

            for (let i = 0; i < {{ $form['field'] }}_data.length; i++) {
                appendInputSelect2{{ $form['field'] }}('');

                setDefaultData({{ $form['field'] }}_data[i], i);
            }
        @else
            appendInputSelect2{{ $form['field'] }}();

            if (Object.keys({{ $form['field'] }}_field[1]).length > 0 || Object.keys({{ $form['field'] }}_field[0])
                .length > 0) {
                $.each({{ $form['field'] }}_field, function(index, fields) {
                    if (index == 0) {
                        const keys = Object.keys(fields);
                        const maxKey = Math.max(...keys.map(Number));

                        for (let i = 0; i < maxKey - 1; i++) {
                            appendInputSelect2{{ $form['field'] }}('');
                        }
                    }

                    $.each(fields, function(indexJ, id) {
                        if (index == 0) {
                            setDefaultData(id, indexJ - 1);
                            $("#div-btn-remove-input-{{ $form['field'] }}-" + (indexJ - 1)).remove();
                            $(".select2_{{ $form['field'] }}_" + (indexJ - 1)).prop('disabled', true);
                        } else if (index == 1) {
                            appendInputSelect2{{ $form['field'] }}(2, true);
                            setDefaultData(id);
                        }

                    });
                });
            }
        @endif

        function setDefaultData(data_id, index) {
            var index_select2 = index ?? index_{{ $form['field'] }} - 1;
            if (data_id) {
                $.getJSON(
                    "{!! $form['select2_url'] ?? '#' !!}", {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        "id": data_id,
                    },
                    function(response) {
                        var data = {
                            id: response[0].id,
                            text: response[0].text
                        };

                        var newOption = new Option(data.text, data.id, false, false);

                        $(".select2_{{ $form['field'] }}_" + index_select2).append(newOption).trigger('change');
                        update_{{ $form['field'] }}_value();
                    }
                );
            }

        }

        function removeInput{{ $form['field'] }}(field, index, position) {
            $('#div-input-' + field + '-' + index).remove();
            update_{{ $form['field'] }}_value();
        }

        function update_{{ $form['field'] }}_value() {
            var hidden_value = [];
            var {{ $form['field'] }}_select2 = $('[id="{{ 'input' . @$formdata->id . '_' . $form['field'] }}"]');

            $.each({{ $form['field'] }}_select2, function(index, select2) {
                var index_select2 = 0;

                $.each(select2.classList, function(indexJ, valueJ) {
                    if (valueJ.includes('{{ $form['field'] }}')) {

                        var subsstart = "{{ 'select2_' . $form['field'] . '_' }}";

                        index_select2 = valueJ.substring(subsstart.length, valueJ.length);
                    }
                })

                // gabisa pake index ini
                var select2_data = $('.select2_{{ $form['field'] }}_' + index_select2).select2('data');
                if (select2_data.length > 0) {

                    var data_id = select2_data[0]['id'];

                    hidden_value.push(data_id);
                }
            });

            $("#{{ 'input' . @$formdata->id . '_' . $form['field'] . '_hidden' }}").val(hidden_value);
        }
    </script>
@endpush
