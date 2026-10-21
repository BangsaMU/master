{{--
    Penjaga kunci unik di form master (semua theme).
    Pakai: @include('master::components.unique-key-guard', ['entity' => 'category', 'param' => $param ?? null])
    - kolom kode otomatis huruf besar
    - edit oleh non-admin: kolom kunci unik dikunci (server juga menolak)
    - edit oleh is_admin: konfirmasi sebelum menyimpan bila kunci unik diubah
--}}
@php
    $uniqueKeyGuard = app(\Bangsamu\Master\Services\MasterUniqueKeyGuard::class)->viewConfig($entity, $param ?? null);
@endphp
<script>
    (function () {
        var cfg = @json($uniqueKeyGuard);
        var names = Object.keys(cfg.fields);
        var pick = function (name) { return document.querySelector('form [name="' + name + '"]'); };
        var anchor = names.map(pick).filter(Boolean)[0];
        if (!anchor) return;
        var form = anchor.closest('form');

        var valueOf = function (el) {
            return (el.value || '').trim();
        };
        var labelOf = function (el, value) {
            if (el.tagName === 'SELECT') {
                var opt = Array.prototype.find.call(el.options, function (o) { return o.value === value; });
                return opt ? opt.text.trim() : value;
            }
            return value;
        };

        // Kolom kode wajib huruf besar
        cfg.codes.forEach(function (name) {
            var el = pick(name);
            if (!el || el.tagName === 'SELECT') return;
            el.style.textTransform = 'uppercase';
            el.value = el.value.toUpperCase();
            el.addEventListener('input', function () {
                var start = el.selectionStart, end = el.selectionEnd;
                el.value = el.value.toUpperCase();
                try { el.setSelectionRange(start, end); } catch (e) {}
            });
        });

        // Non-admin: kunci unik tidak bisa diubah saat edit
        if (cfg.locked) {
            names.forEach(function (name) {
                var el = pick(name);
                if (!el) return;
                if (el.tagName === 'SELECT') {
                    // disabled tidak ikut terkirim; server memakai nilai lama untuk kunci yang tidak dikirim
                    el.disabled = true;
                    if (window.jQuery && jQuery.fn.select2 && jQuery(el).data('select2')) {
                        jQuery(el).prop('disabled', true).trigger('change.select2');
                    }
                } else {
                    el.readOnly = true;
                    el.classList.add('bg-light');
                }
                el.title = 'Kunci unik tidak dapat diubah';
                var hint = document.createElement('small');
                hint.className = 'form-text text-muted d-block';
                hint.textContent = 'Kunci unik, tidak dapat diubah. Hubungi admin bila perlu koreksi.';
                (el.nextElementSibling && el.nextElementSibling.classList.contains('select2') ? el.nextElementSibling : el).insertAdjacentElement('afterend', hint);
            });
        }

        // Admin: konfirmasi bila kunci unik diubah
        if (cfg.confirm) {
            var flag = document.createElement('input');
            flag.type = 'hidden';
            flag.name = cfg.confirm_field;
            flag.value = '0';
            form.appendChild(flag);

            form.addEventListener('submit', function (e) {
                var changes = [];
                names.forEach(function (name) {
                    var el = pick(name);
                    if (!el) return;
                    var isCode = cfg.codes.indexOf(name) !== -1;
                    var before = isCode ? cfg.original[name].toUpperCase() : cfg.original[name];
                    var after = isCode ? valueOf(el).toUpperCase() : valueOf(el);
                    if (before !== after) {
                        changes.push('- ' + cfg.fields[name] + ': ' + labelOf(el, before) + ' -> ' + labelOf(el, after));
                    }
                });
                if (changes.length === 0) {
                    flag.value = '0';
                    return;
                }
                var ok = window.confirm(
                    'Anda akan mengubah KUNCI UNIK:\n' + changes.join('\n') +
                    '\n\nMengubah kunci unik dapat memutus acuan di aplikasi lain dan membuat import berikutnya dianggap data baru.' +
                    '\n\nYakin ingin menyimpan perubahan ini?'
                );
                if (!ok) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    flag.value = '0';
                    return;
                }
                flag.value = '1';
            }, true);
        }
    })();
</script>
