<div class="modal fade" id="{{ @$formModal['modal_format']['identification'] }}" tabindex="-1" aria-labelledby="modalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">{{ @$formModal['modal_format']['title'] }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form class="row" action="{{ $formModal['form_format']['url'] }}" method="post" enctype="multipart/form-data" id="{{ $formModal['form_format']['identification'] }}">
                    @csrf

                    @if($formModal['form_format']['mode'] == 'edit')
                        @method('PUT')
                    @endif

                    @isset($formModal['list_input'])
                        @foreach (@$formModal['list_input'] as $input)
                            @switch($input['type'])
                                @case('checkbox')
                                    <div class="col-12 mb-2">
                                        <label for="{{ $input['identification'] }}">{{ $input['label'] }}</label>
                                        <div class="row mx-0">
                                            @foreach ($input['data'] as $data)
                                                <div class="form-check col-6">
                                                    <input class="form-check-input" type="checkbox" value="{{ $data['value'] }}"
                                                        id="{{ $data['identification'] }}" name="{{ $input['identification'] }}[]"
                                                        checked="{{ $input['checked'] }}">
                                                    <label class="form-check-label" for="{{ $data['identification'] }}">
                                                        {{ $data['label'] }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @break

                                @case('text')
                                    <div class="form-group mb-2 col-6">
                                        <label for="{{ $input['identification'] }}">{{ $input['label'] }}</label>
                                        <input type="text" class="form-control" id="{{ $input['identification'] }}"
                                            name="{{ $input['identification'] }}" placeholder="{{ $input['label'] }}"
                                            style="{{ @$input['style'] }}">
                                    </div>
                                @break

                                @case('select2-multiple')
                                    <div class="form-group mb-2 col-6">
                                        <label for="{{ $input['identification'] }}">{{ $input['label'] }}</label>
                                        <select class="form-control" name="{{ $input['identification'] }}[]"
                                            id="{{ $input['identification'] }}" multiple="multiple">
                                            @foreach ($input['data'] as $key => $value)
                                                <option value="{{ $key }}" selected="{{ $input['selected'] }}">
                                                    {{ $value }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @break

                                @case('date_range')
                                    <div class="form-group mb-2 col-6">
                                        <label for="{{ $input['identification'] }}">{{ $input['label'] }}</label>
                                        <input type="text" class="form-control float-right"
                                            id="date-range-{{ $input['identification'] }}" name="{{ $input['identification'] }}">
                                    </div>
                                @break

                                @case('date')
                                    <div class="form-group mb-2 col-6">
                                        <label for="{{ $input['identification'] }}">{{ $input['label'] }}</label>
                                        <div class="input-group date" data-target-input="nearest">
                                            <input type="text" id="{{ $input['identification'] }}"
                                                class="form-control form-control-sm datepicker-input input-date"
                                                data-target="#{{ $input['identification'] }}"
                                                name="{{ $input['identification'] }}" value="">
                                            <div class="input-group-append" data-target="#{{ $input['identification'] }}"
                                                data-toggle="datepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                @break

                                @case('datetime')
                                    <div class="form-group mb-2 col-6">
                                        <label for="{{ $input['identification'] }}">{{ $input['label'] }}</label>
                                        <div class="input-group date" data-target-input="nearest">
                                            <input type="text" id="{{ $input['identification'] }}"
                                                class="form-control form-control-sm datetimepicker-input input-datetime"
                                                data-target="#{{ $input['identification'] }}"
                                                name="{{ $input['identification'] }}" value="">
                                            <div class="input-group-append" data-target="#{{ $input['identification'] }}"
                                                data-toggle="datetimepicker">
                                                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                @break

                                @case('select')
                                    <div class="form-group mb-2 col-6">
                                        <label class="col-form-label"
                                            for="{{ $input['identification'] }}">{{ $input['label'] }}</label>
                                        <div class="">
                                            <select
                                                class="form-control select @error($input['identification']) is-invalid @enderror"
                                                name="{{ $input['identification'] }}" id="{{ $input['identification'] }}">
                                                @foreach ($input['data'] as $key_data => $data)
                                                    <option value="{{ $key_data ?? $data }}">{{ $data }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @break

                                @default
                            @endswitch
                        @endforeach

                    @endisset
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary" id="btn-submit"
                    onclick="submitSearch()">{{ @$formModal['modal_format']['button_title'] }}</button>
            </div>
        </div>
    </div>
</div>

{{-- @push('css')
    <style>
        .select2-container--default .select2-selection--multiple .select2-selection__choice{
            background-color: #007bff;
        }
    </style>
@endpush --}}

@section('js')
    <script>
        $('.select2').select2();
        var queryParameXport = '?';

        $(document).ready(function() {
            $('.input-date').flatpickr({
                dateFormat: "Y-m-d"
            });

            $('.input-datetime').flatpickr({
                enableTime: true,
                dateFormat: "d-M-y H:i"
            });

            // $('.select2').select2();
        });

        function submitSearch() {
            var form = $('#form_search').serializeArray();

            var data_index = [];
            @isset($formModal['list_input'])
                @foreach (@$formModal['list_input'] as $data)
                    data_index['{{ $data['identification'] }}'] = {{ @$data['index'] }};
                @endforeach
            @endisset

            var executefilter = '$(tableName).DataTable()';
            $.map(form, function(data, i) {
                if (data['name'] in data_index) {
                    executefilter += ".columns(" + data_index[data['name']] + ")";
                    executefilter += ".search('" + data['value'] + "')";
                    queryParameXport += "&columns[" + data_index[data['name']] + "]="+ data['value'];
                }
            });

            executefilter += '.draw()';
            let result = eval(executefilter);
            console.log("executefilter:",executefilter);
            console.log("queryParameXport:",queryParameXport);
            $('#modal_search').modal('hide');
            return false;
        }
    </script>
@endsection
