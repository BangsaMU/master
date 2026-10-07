<div class="card mt-4" id="log-karyawan">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">
            <i class="ti ti-history me-2 text-primary"></i>{{ $title ?? 'Timeline' }}
        </h3>
        @if (!empty($logs) && count($logs) > 0)
            <span class="badge bg-azure-lt">{{ count($logs) }} Aktivitas</span>
        @endif
    </div>

    <div class="card-body">
        @if (empty($logs) || count($logs) === 0)
            <div class="text-center py-4 text-secondary">
                <i class="ti ti-history-off fs-1 d-block mb-2 text-muted"></i>
                <p class="mb-0">Belum ada riwayat log untuk data ini.</p>
            </div>
        @else
            <ul class="timeline">
                @php
                    $previousDate = '';
                @endphp

                @foreach ($logs as $log)
                    {{-- Date Group Divider --}}
                    @if ($log->updated_at_formated_date !== $previousDate)
                        <li class="timeline-event">
                            <div class="timeline-event-icon bg-danger-lt">
                                <i class="ti ti-calendar"></i>
                            </div>
                            <div class="timeline-event-card">
                                <span class="badge bg-danger-lt px-2 py-1 text-uppercase fw-bold">
                                    {{ $log->updated_at_formated_date }}
                                </span>
                            </div>
                        </li>
                    @endif

                    {{-- Timeline Event Item --}}
                    <li class="timeline-event">
                        <div class="timeline-event-icon bg-primary-lt">
                            <i class="ti ti-edit"></i>
                        </div>
                        <div class="card timeline-event-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar avatar-xs rounded-circle bg-blue-lt">
                                            <i class="ti ti-user"></i>
                                        </span>
                                        <span class="fw-bold text-heading">{{ trim($log->nama_user) ?: 'System' }}</span>
                                        <span class="badge bg-blue-lt">{{ ucfirst($log->action) }}</span>
                                    </div>
                                    <span class="text-secondary small">
                                        <i class="ti ti-clock me-1"></i>{{ $log->updated_at_formated_time }}
                                    </span>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-vcenter table-bordered card-table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 25%">Field</th>
                                                <th style="width: 37.5%">Before</th>
                                                <th style="width: 37.5%">After</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if (isset($log['properties']['before']) && is_array($log['properties']['before']))
                                                @php $hasRows = false; @endphp
                                                @foreach ($log['properties']['before'] as $field => $beforeValue)
                                                    @if ($field === 'updated_at')
                                                        @continue
                                                    @endif

                                                    @php
                                                        $hasRows = true;
                                                        $afterValue = $log['properties']['after'][$field] ?? '-';
                                                        $displayBefore = $beforeValue !== null && $beforeValue !== '' ? $beforeValue : '-';
                                                        $displayAfter = $afterValue !== null && $afterValue !== '' ? $afterValue : '-';
                                                    @endphp

                                                    <tr>
                                                        <td class="text-secondary fw-semibold">{{ ucfirst(str_replace('_', ' ', $field)) }}</td>
                                                        <td>
                                                            @if ($displayBefore !== '-')
                                                                <span class="text-danger">{{ $displayBefore }}</span>
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if ($displayAfter !== '-')
                                                                <span class="text-success fw-bold">{{ $displayAfter }}</span>
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach

                                                @if (!$hasRows)
                                                    <tr>
                                                        <td colspan="3" class="text-center text-muted py-2">
                                                            <em>Tidak ada perubahan data</em>
                                                        </td>
                                                    </tr>
                                                @endif
                                            @elseif (isset($log['properties']) && is_array($log['properties']) && !empty($log['properties']))
                                                {{-- Untuk log selain updated (seperti created) yang menyimpan properties langsung --}}
                                                @foreach ($log['properties'] as $field => $val)
                                                    @if ($field === 'updated_at' || $field === 'created_at')
                                                        @continue
                                                    @endif
                                                    <tr>
                                                        <td class="text-secondary fw-semibold">{{ ucfirst(str_replace('_', ' ', $field)) }}</td>
                                                        <td><span class="text-muted">-</span></td>
                                                        <td><span class="text-success fw-bold">{{ is_array($val) ? json_encode($val) : ($val ?? '-') }}</span></td>
                                                    </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-2">
                                                        <em>Data sebelum tidak tersedia</em>
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </li>

                    @php
                        $previousDate = $log->updated_at_formated_date;
                    @endphp
                @endforeach
            </ul>
        @endif
    </div>

    @if (method_exists($logs, 'hasPages') && $logs->hasPages())
        <div class="card-footer d-flex justify-content-between align-items-center">
            <div>
                @if ($logs->previousPageUrl())
                    <a href="{{ $logs->previousPageUrl() }}#log-karyawan" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-chevron-left me-1"></i> Previous
                    </a>
                @else
                    <button class="btn btn-outline-secondary btn-sm" disabled>
                        <i class="ti ti-chevron-left me-1"></i> Previous
                    </button>
                @endif
            </div>

            <div class="text-secondary small">
                Menampilkan {{ $logs->count() }} aktivitas
            </div>

            <div>
                @if ($logs->nextPageUrl())
                    <a href="{{ $logs->nextPageUrl() }}#log-karyawan" class="btn btn-outline-secondary btn-sm">
                        Next <i class="ti ti-chevron-right ms-1"></i>
                    </a>
                @else
                    <button class="btn btn-outline-secondary btn-sm" disabled>
                        Next <i class="ti ti-chevron-right ms-1"></i>
                    </button>
                @endif
            </div>
        </div>
    @endif
</div>
