<div class="modal fade" id="{{ $formModal['modal_format']['identification'] }}" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">{{ $formModal['modal_format']['title'] }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form class="row" action="{{ $formModal['form_format']['url'] }}" method="post" enctype="multipart/form-data" id="{{ $formModal['form_format']['identification'] }}">
                    @csrf
                    @foreach ($formModal['list_input'] as $input)
                        @switch($input['type'])
                            @case('checkbox')
                                <div class="col-12 mb-2">
                                    <label for="{{ $input['identification'] }}">{{ $input['label'] }}</label>
                                    <div class="row mx-0">
                                        @foreach ($input['data'] as $data)
                                            <div class="form-check col-6">
                                                <input class="form-check-input" type="checkbox" value="{{ $data['value'] }}" id="{{ $data['identification'] }}" name="{{ $input['identification'] }}[]" checked="{{ $input['checked'] }}">
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
                                    <input type="text" class="form-control" id="{{ $input['identification'] }}" name="{{ $input['identification'] }}" placeholder="{{ $input['label'] }}" style="{{ @$input['style'] }}">
                                </div>
                                @break

                            @case('select2-multiple')
                                <div class="form-group mb-2 col-6">
                                    <label for="{{ $input['identification'] }}">{{ $input['label'] }}</label>
                                    <select class="form-control" name="{{ $input['identification'] }}[]" id="{{ $input['identification'] }}" multiple="multiple">
                                        @foreach ($input['data'] as $key => $value)
                                            <option value="{{ $key }}" selected="{{ $input['selected'] }}">{{ $value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @break

                            @case('date_range')
                                <div class="form-group mb-2 col-6">
                                    <label for="{{ $input['identification'] }}">{{ $input['label'] }}</label>
                                    <input type="text" class="form-control float-right" id="date-range-{{ $input['identification'] }}" name="{{ $input['identification'] }}">
                                </div>
                                @break

                            @default
                        @endswitch
                  @endforeach
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary" id="btn-submit">{{ $formModal['modal_format']['button_title'] }}</button>
            </div>
        </div>
    </div>
</div>

@push('css')
    <style>
        .select2-container--default .select2-selection--multiple .select2-selection__choice{
            background-color: #007bff;
        }
    </style>
@endpush

@push('js')
    <script>
        let form_id = '#' + "{{ $formModal['form_format']['identification'] }}";

        $("#btn-submit").click(function(){
            $(form_id).submit();
        })
    </script>
@endpush
