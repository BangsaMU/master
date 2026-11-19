{{--
    Pindah pemilik project (Edit Project, admin Master Data di hub), form tersendiri di LUAR form Project. Data dari
    ProjectController::ownerFormData() ($data['page']['project_owner']['transfer_choices'] kosong = tidak tampil).
    Server: POST master.project.transfer-owner -> ProjectOwnerDetail::transferOwner.
--}}
@php
    $po = $data['page']['project_owner'] ?? null;
    $poTransferField = \Bangsamu\Master\Services\ProjectOwnerDetail::FIELD_TRANSFER_COMPANY;
@endphp
@if ($po && $po['mode'] === 'edit' && ! $po['trashed'] && empty($data['page']['readonly']) && $po['transfer_choices']->isNotEmpty())
    <div class="row">
        <div class="col-12 col-sm-8">
            <div class="card card-outline card-warning mt-3" id="project-owner-transfer">
                <div class="card-header font-weight-bold fw-bold">Pindah pemilik project</div>
                <div class="card-body">
                    <p class="small mb-2">
                        Pemilik sekarang: <strong>{{ $po['owner_label'] }}</strong>. Pemilik baru memakai baris Project
                        Detail company itu (dibuat dari kode/nama project sekarang bila belum ada). Project Code dan
                        Project Name ikut kode/nama baris itu. Baris pemilik lama tetap ada sebagai baris company lain.
                    </p>
                    <p class="small text-muted mb-3">
                        Aplikasi yang hanya membaca tabel master_project (mis. AMS, DMS, Warehouse) akan menampilkan
                        kode/nama baru.
                    </p>
                    <form action="{{ $po['transfer_url'] }}" method="POST" autocomplete="off">
                        @csrf
                        <div class="form-group mb-3">
                            <label for="{{ $poTransferField }}">Company pemilik baru</label>
                            <select class="form-control form-select @error($poTransferField) is-invalid @enderror" name="{{ $poTransferField }}" id="{{ $poTransferField }}" required>
                                <option value="">-- pilih company --</option>
                                @foreach ($po['transfer_choices'] as $poChoice)
                                    <option value="{{ $poChoice->id }}" @selected((string) old($poTransferField) === (string) $poChoice->id)>
                                        {{ $poChoice->company_code }}{{ filled($poChoice->company_name) ? ' - '.$poChoice->company_name : '' }}
                                        @if ($poChoice->has_row)
                                            (kode/nama: {{ $poChoice->code }} | {{ $poChoice->name }})
                                        @else
                                            (belum punya baris: dibuat {{ $po['project_code'] }} | {{ $po['project_name'] }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error($poTransferField)
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input @error('confirm_transfer') is-invalid @enderror" type="checkbox" name="confirm_transfer" value="1" id="confirm_transfer" required>
                            <label class="form-check-label" for="confirm_transfer">
                                Saya mengerti: pemilik project, Project Code, dan Project Name berubah mengikuti company pemilik baru.
                            </label>
                            @error('confirm_transfer')
                                <span class="text-danger d-block">{{ $message }}</span>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-warning">Pindah pemilik</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
