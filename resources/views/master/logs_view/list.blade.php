<div class="row">
    <div class="col ">
        <div class="card card-outline card-primary">
            <div class="card-header font-weight-bold">
                {{ $title ?? 'Timeline' }}
            </div>
            <div class="card-body">


                <div class="app-content">
                    <!--begin::Container-->
                    <div class="container-fluid">
                        <!-- Timelime example  -->
                        <div class="row">
                            <div class="col-md-12">
                                <!-- The time line -->
                                <div class="timeline">

                                    @foreach ($logs as $key => $log)
                                        <!-- timeline time label -->
                                        <div class="time-label">
                                            <span class="text-bg-danger">{{ $log->updated_at_formated_date }}</span>
                                        </div>
                                        <!-- /.timeline-label -->
                                        <!-- timeline item -->
                                        <div>
                                            <i class="timeline-icon bi bi-envelope text-bg-primary">
                                            </i>
                                            <div class="timeline-item">
                                                <span class="time">
                                                    <i class="bi bi-clock-fill"></i>
                                                    {{ $log->updated_at_formated_time }}
                                                </span>
                                                <h3 class="timeline-header">
                                                    <a href="#">{{ $log->nama_user }}</a> {{ $log->action }}
                                                </h3>

                                                <div class="timeline-body">
                                                    {{-- {{$log->sort_description}} --}}
                                                    <table class="table table-bordered table-striped">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Field</th>
                                                                <th>Before</th>
                                                                <th>After</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @if (isset($log['properties']['before']) && is_array($log['properties']['before']))
                                                                @foreach ($log['properties']['before'] as $key => $beforeValue)
                                                                    @if ($key === 'updated_at')
                                                                        @continue
                                                                    @endif

                                                                    @php
                                                                        $afterValue = $log['properties']['after'][$key] ?? '-';
                                                                    @endphp

                                                                    <tr>
                                                                        <td>{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                                                                        <td>{{ $beforeValue ?? '-' }}</td>
                                                                        <td>{{ $afterValue }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            @else
                                                                <tr>
                                                                    <td colspan="3"><em>Data sebelum tidak tersedia</em></td>
                                                                </tr>
                                                            @endif
                                                        </tbody>
                                                    </table>



                                                </div>
                                                {{-- <div class="timeline-footer">
                                                    <a class="btn btn-primary btn-sm">Read more</a>
                                                    <a class="btn btn-danger btn-sm">Delete</a>
                                                </div> --}}
                                            </div>
                                        </div>
                                        <!-- END timeline item -->
                                    @endforeach



                                    <div>
                                        <i class="timeline-icon bi bi-clock-fill text-bg-secondary">
                                        </i>
                                    </div>
                                </div>
                            </div>
                            <!-- /.col -->
                        </div>
                        <!-- /.row -->
                    </div>
                    <!--end::Container-->
                </div>



            </div>
        </div>
    </div>
</div>
