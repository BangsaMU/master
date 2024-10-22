<span class="mb-1 text-subtitle text-muted">{!! @$data['page']['sub_title']??'<br>' !!}</span>
<h1 class="mb-0">{!! @$data['page']['title'] !!}</h1>
@if ($agent->isMobile() && isset($data['route']))
    <div class="card card-outline card-primary mb-0">
        <div class="card-body">
            <div class="dropdown mt-2">
                <a href="#" class="btn btn-primary btn-block dropdown-toggle" type="button" id="dropdownMenuButton"
                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Menu
                </a>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                    @foreach ($data['route'] as $name => $item)
                        <a class="nav-link {{ str_contains(url()->current(), $item) ? 'active' : ($data['page']['id'] ? '' : 'disabled') }}"
                            href="{{ $item }}">{{ ucfirst($name) }}
                        </a>
                    @endforeach
                </div>
            </div>

            @if (isset($data['tab-menu']['action']))
                <div class="dropdown show ml-auto mt-2">
                    <a class="btn btn-block btn-secondary dropdown-toggle" href="#" role="button"
                        id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Action
                    </a>

                    <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                        @foreach ($data['tab-menu']['action'] as $name => $item)
                            <a class="nav-link {{ str_contains($name, url()->current()) ? 'active' : '' }}"
                                @if (@$item['attribut']) {!! $item['attribut'] !!} @endif
                                @if (@$item['url']) href="{{ $item['url'] }}" @endif
                                @if (@$item['target']) target="{{ $item['target'] }}" @endif
                                @if (@$item['function']) onclick="{{ $item['function'] }}" @endif>
                                {{ ucfirst($item['title']) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
@else
    <div class="card card-outline card-primary mb-0
            @if (!isset($data['route']) && !isset($data['tab-menu']['action'])) d-none @endif
    ">
        <div class="card-body">
            <div class="d-flex">
                <!-- root navigation -->
                <nav class="nav nav-pills nav-justified">
                    @if (isset($data['route']))
                        @foreach ($data['route'] as $name => $item)
                        <!-- active::{{json_encode(str_contains($item,url()->current()))}} curr_url::{{url()->current()}} $item::{{$item}}-->
                            <a class="nav-link mr-1 {{ str_contains($item,url()->current()) ? 'active' : ($data['page']['id'] ? '' : 'disabled') }}"
                                href="{{ $item }}">{{ ucfirst($name) }}
                            </a>
                        @endforeach
                    @endif
                </nav>

                @if (isset($data['tab-menu']['action']))
                    <div class="dropdown show ml-auto dropleft">
                        <a class="btn btn-secondary dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Action
                        </a>

                        <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                            @foreach ($data['tab-menu']['action'] as $name => $item)
                                @if (@$item['divider'] === true)
                                    <div class="dropdown-divider"></div>
                                @endif

                                @if (@$item['header'])
                                    <span class="dropdown-header">{{ ucfirst($item['header']) }}</span>
                                @endif

                                @isset($item['url'])
                                    <a class="dropdown-item {{ str_contains($name, url()->current()) ? 'active' : '' }}"
                                        @if (@$item['attribut']) {!! $item['attribut'] !!} @endif
                                        @if (@$item['url']) href="{{ $item['url'] }}" @endif
                                        @if (@$item['target']) target="{{ $item['target'] }}" @endif
                                        @if (@$item['function']) onclick="{{ $item['function'] }}" @endif>
                                        {{ ucfirst($item['title']) }}
                                    </a>
                                @endisset
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
@endif

@push('js')
    <script>
        $(document).ready(function() {
            var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');

            // $('#inputAssetNumber').select2({
            //     width: '100%',
            //     placeholder: 'Please select Joint Type',
            //     ajax: {
            //         url: "#",
            //         type: "get",
            //         dataType: 'json',
            //         delay: 5,
            //         data: function(params) {
            //             return {
            //                 _token: CSRF_TOKEN,
            //                 search: params.term
            //             };
            //         },
            //         processResults: function(response) {
            //             return {
            //                 results: response
            //             };
            //         },
            //         cache: true
            //     }
            // });

            $("#inputAssetNumber").on("select2:select", function(e) {
                window.location.assign("{{ url(@$data['master']['url_prefix'] . '-details') }}" + '/' + e
                    .params.data.id);
            });

            $(document).on("click", ".action-btn", function() {
                var actionid = $(this).data('actionid');
                $(".actionid").val(actionid);
            });
        });
    </script>
@endpush
