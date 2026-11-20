{{--
    Centang "Reset nomor tahunan" di form Project Detail (di DALAM <form>), data dari
    ProjectDetailController::numberResetField() lewat $data['page']['number_reset'] (null = kolom belum ada, tidak tampil).
    Hanya admin Master Data yang bisa mengubah; user lain melihat nilainya (centang mati, tanpa name = tidak dikirim).
    Baris pemilik project tetap bisa diubah: kuncinya hanya untuk kode/nama.
--}}
@php
    $nr = $data['page']['number_reset'] ?? null;
    $nrField = \Bangsamu\Master\Services\ProjectDetailNumberReset::COLUMN;
@endphp
@if ($nr)
    <div class="form-group mb-3" data-number-reset>
        @if ($nr['can_edit'])
            <input type="hidden" name="{{ $nrField }}" value="0">
        @endif
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="{{ $nrField }}" value="1"
                @if ($nr['can_edit']) name="{{ $nrField }}" @else disabled @endif
                @checked($nr['value'])>
            <label class="form-check-label" for="{{ $nrField }}">Reset nomor tahunan</label>
        </div>
        <div class="text-muted small">
            Nomor dokumen company ini di project ini mulai dari 1 lagi tiap tahun (mis. 21000 MEINDO, 23000 Handil, project
            umum MEB 71xxx/78xxx). Dipakai aplikasi SPB untuk nomor SPB.
            @unless ($nr['can_edit'])
                Hanya admin Master Data yang bisa mengubah.
            @endunless
        </div>
    </div>
@endif
