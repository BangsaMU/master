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

@php
    $previousTime = '';
    $previousDate = '';
@endphp

@foreach ($logs as $key => $log)
    <!-- tampil label tanggal kalau berganti -->
    @if ($log->updated_at_formated_date !== $previousDate)
        <div class="time-label">
            <span class="text-bg-danger">{{ $log->updated_at_formated_date }}</span>
        </div>
    @endif

    <div>
        <i class="timeline-icon bi bi-envelope text-bg-primary"></i>
        <div class="timeline-item">
            <span class="time">
                @if ($log->updated_at_formated_time !== $previousTime)
                    <i class="bi bi-clock-fill"></i>{{ $log->updated_at_formated_time }}
                @endif
            </span>

            <h3 class="timeline-header">
                <a href="#">{{ $log->nama_user }}</a> {{ $log->action }}
            </h3>

            <div class="timeline-body">
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
                            @foreach ($log['properties']['before'] as $field => $beforeValue)
                                @if ($field === 'updated_at')
                                   @continue
                                @endif

                                @php
                                   $afterValue = $log['properties']['after'][$field] ?? '-';
                                @endphp

                                <tr>
                                   <td>{{ ucfirst(str_replace('_', ' ', $field)) }}</td>
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
        </div>
    </div><!-- END timeline item -->

    @php
        $previousTime = $log->updated_at_formated_time;
        $previousDate = $log->updated_at_formated_date;
    @endphp
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
