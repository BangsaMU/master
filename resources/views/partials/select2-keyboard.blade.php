{{--
    Select2 dengan keyboard. Aman di-include berulang / bersama layout aplikasi: hanya aktif sekali per halaman.
    - Tab / Shift+Tab ke dropdown select2 langsung membuka kotak cari. Bawaan Select2 4.0: fokus hanya berhenti di kotak,
      kotak cari baru muncul setelah klik, Enter, atau Spasi.
    - Saat kotak cari terbuka, Tab / Shift+Tab menutup dropdown lalu pindah ke field berikut / sebelumnya. Bawaan: Tab
      hanya menutup dan fokus kembali ke dropdown yang sama (perlu Tab dua kali). Bila sudah mengetik kata, pilihan yang
      disorot ikut dipilih seperti Enter; tanpa kata, nilai tidak berubah (Tab sekadar lewat).
    - Klik, Esc, dan Enter tetap seperti bawaan (dropdown tidak terbuka lagi saat fokus kembali sesudah ditutup).
--}}
<script>
    (function ($) {
        if (!$ || !$.fn || !$.fn.select2 || window.masterSelect2Keyboard) {
            return;
        }
        window.masterSelect2Keyboard = true;

        var viaTab = false;        // fokus berikutnya berasal dari tombol Tab, bukan klik / fokus balik dari select2
        var abaikanFokus = false;  // select2 sedang ditutup oleh skrip ini (fokus balik ke dropdown jangan membuka lagi)
        var $terbuka = null;       // <select> yang dropdown-nya sedang terbuka

        var KANDIDAT_FOKUS = 'a[href], button, input, select, textarea, [tabindex]';

        function bisaDifokus(el) {
            return !el.disabled && el.tabIndex >= 0 && el.type !== 'hidden' && $(el).is(':visible')
                && $(el).closest('[inert], .select2-dropdown').length === 0;
        }

        function pindahFokus(dari, mundur) {
            var daftar = $(KANDIDAT_FOKUS).filter(function () { return bisaDifokus(this); }).toArray();
            var posisi = daftar.indexOf(dari);
            var tujuan = posisi < 0 ? null : daftar[posisi + (mundur ? -1 : 1)];
            if (!tujuan) {
                return;
            }
            viaTab = true; // select2 tujuan ikut terbuka
            tujuan.focus();
            viaTab = false;
        }

        $(document).on('select2:open', 'select', function () {
            $terbuka = $(this);
        });
        $(document).on('select2:close', 'select', function () {
            if ($terbuka && $terbuka[0] === this) {
                $terbuka = null;
            }
        });

        // fase capture: jalan sebelum handler select2 di kotak cari
        document.addEventListener('mousedown', function () {
            viaTab = false;
        }, true);
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab' || e.altKey || e.ctrlKey || e.metaKey) {
                return;
            }

            var $search = $(e.target).closest('.select2-dropdown .select2-search__field');
            var instance = $terbuka && $terbuka.data('select2');
            if (!$search.length || !instance || !instance.isOpen()) {
                // Tab biasa: fokus yang mendarat di select2 sesudah aksi bawaan Tab membuka dropdown-nya
                viaTab = true;
                window.setTimeout(function () { viaTab = false; }, 0);
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            var $select = $terbuka;
            var $hasil = $search.closest('.select2-dropdown');
            var $disorot = $hasil.find('.select2-results__option--highlighted[aria-selected]');
            var masihMemuat = $hasil.find('.loading-results').length > 0;

            abaikanFokus = true;
            try {
                if ($.trim($search.val()) !== '' && $disorot.length && !masihMemuat) {
                    instance.trigger('results:select', {}); // sama dengan Enter (lalu tertutup)
                }
                if (instance.isOpen()) {
                    $select.select2('close');
                }
            } finally {
                abaikanFokus = false;
            }

            pindahFokus($select.next('.select2-container').find('.select2-selection')[0], e.shiftKey);
        }, true);

        $(document).on('focus', '.select2-container .select2-selection--single', function () {
            if (!viaTab || abaikanFokus) {
                return;
            }
            viaTab = false;

            var $select = $(this).closest('.select2-container').prev('select');
            var instance = $select.data('select2');
            if (instance && !instance.isOpen() && !$select.prop('disabled') && !$select.is('[readonly]')) {
                $select.select2('open');
            }
        });
    })(window.jQuery);
</script>
