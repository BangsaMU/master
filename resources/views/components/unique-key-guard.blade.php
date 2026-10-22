{{--
    Penjaga kunci unik di form master (semua theme).
    Pakai: @include('master::components.unique-key-guard', ['entity' => 'category', 'param' => $param ?? null])
    - kolom kode otomatis huruf besar
    - edit oleh non-admin: kolom kunci unik dikunci (server juga menolak)
    - edit oleh is_admin: modal konfirmasi sebelum menyimpan bila kunci unik diubah
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

            var confirmed = false;

            form.addEventListener('submit', function (e) {
                if (confirmed) {
                    confirmed = false; // submit hasil konfirmasi, biarkan lanjut
                    return;
                }
                var changes = [];
                names.forEach(function (name) {
                    var el = pick(name);
                    if (!el) return;
                    var isCode = cfg.codes.indexOf(name) !== -1;
                    var before = isCode ? cfg.original[name].toUpperCase() : cfg.original[name];
                    var after = isCode ? valueOf(el).toUpperCase() : valueOf(el);
                    if (before !== after) {
                        changes.push({ label: cfg.fields[name], before: labelOf(el, before), after: labelOf(el, after) });
                    }
                });
                flag.value = '0';
                if (changes.length === 0) return;

                e.preventDefault();
                e.stopImmediatePropagation();
                var submitter = e.submitter || null;

                ukgModal(changes, function () {
                    flag.value = '1';
                    confirmed = true;
                    if (typeof form.requestSubmit === 'function') {
                        submitter ? form.requestSubmit(submitter) : form.requestSubmit();
                    } else {
                        form.submit();
                    }
                });
            }, true);
        }

        // Modal konfirmasi kunci unik (mandiri, tidak bergantung Bootstrap JS / theme)
        function ukgModal(changes, onConfirm) {
            injectStyle();

            var lastFocus = document.activeElement;
            var overlay = el('div', 'ukg-overlay');
            var dialog = el('div', 'ukg-dialog');
            dialog.setAttribute('role', 'alertdialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-labelledby', 'ukg-title');
            dialog.setAttribute('aria-describedby', 'ukg-desc');

            var head = el('div', 'ukg-head');
            var icon = el('div', 'ukg-icon');
            icon.innerHTML = '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4"/><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/><path d="M12 16h.01"/></svg>';
            var titles = el('div', 'ukg-titles');
            var title = el('h3', 'ukg-title', 'Ubah kunci unik?');
            title.id = 'ukg-title';
            var subtitle = el('p', 'ukg-subtitle', 'Kunci unik dipakai sebagai acuan oleh data dan aplikasi lain.');
            subtitle.id = 'ukg-desc';
            titles.appendChild(title);
            titles.appendChild(subtitle);
            head.appendChild(icon);
            head.appendChild(titles);

            var body = el('div', 'ukg-body');
            var table = el('table', 'ukg-table');
            var thead = el('thead');
            var hr = el('tr');
            ['Kolom', 'Sebelum', '', 'Sesudah'].forEach(function (t) { hr.appendChild(el('th', null, t)); });
            thead.appendChild(hr);
            var tbody = el('tbody');
            changes.forEach(function (c) {
                var tr = el('tr');
                tr.appendChild(el('td', 'ukg-field', c.label));
                tr.appendChild(el('td', 'ukg-before', c.before || '(kosong)'));
                tr.appendChild(el('td', 'ukg-arrow', '→'));
                tr.appendChild(el('td', 'ukg-after', c.after || '(kosong)'));
                tbody.appendChild(tr);
            });
            table.appendChild(thead);
            table.appendChild(tbody);
            body.appendChild(table);

            var note = el('div', 'ukg-note');
            note.appendChild(el('strong', null, 'Dampak perubahan:'));
            var ul = el('ul');
            [
                'Dokumen, laporan, atau aplikasi lain yang menyimpan kode lama bisa kehilangan acuan.',
                'Import berikutnya yang masih memakai kode lama akan dianggap data baru.',
                'Perubahan ini tercatat di log data.'
            ].forEach(function (t) { ul.appendChild(el('li', null, t)); });
            note.appendChild(ul);
            body.appendChild(note);

            var foot = el('div', 'ukg-foot');
            var cancel = el('button', 'ukg-btn ukg-btn-cancel', 'Batal');
            cancel.type = 'button';
            var ok = el('button', 'ukg-btn ukg-btn-ok', 'Ya, ubah kunci unik');
            ok.type = 'button';
            foot.appendChild(cancel);
            foot.appendChild(ok);

            dialog.appendChild(head);
            dialog.appendChild(body);
            dialog.appendChild(foot);
            overlay.appendChild(dialog);
            document.body.appendChild(overlay);
            document.body.classList.add('ukg-open');
            requestAnimationFrame(function () { overlay.classList.add('ukg-show'); });
            cancel.focus();

            var close = function (accepted) {
                document.removeEventListener('keydown', onKey, true);
                overlay.classList.remove('ukg-show');
                document.body.classList.remove('ukg-open');
                setTimeout(function () { overlay.remove(); }, 160);
                if (accepted) {
                    onConfirm();
                } else if (lastFocus && lastFocus.focus) {
                    lastFocus.focus();
                }
            };
            var onKey = function (e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    close(false);
                } else if (e.key === 'Tab') {
                    // jaga fokus tetap di dalam modal
                    e.preventDefault();
                    (document.activeElement === cancel ? ok : cancel).focus();
                }
            };
            document.addEventListener('keydown', onKey, true);
            cancel.addEventListener('click', function () { close(false); });
            ok.addEventListener('click', function () { ok.disabled = true; close(true); });
            overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) close(false); });
        }

        function el(tag, cls, text) {
            var node = document.createElement(tag);
            if (cls) node.className = cls;
            if (text !== undefined) node.textContent = text; // textContent: aman dari HTML di nilai input
            return node;
        }

        function injectStyle() {
            if (document.getElementById('ukg-style')) return;
            var css = ''
                + '.ukg-open{overflow:hidden}'
                + '.ukg-overlay{position:fixed;inset:0;z-index:2050;display:flex;align-items:center;justify-content:center;padding:16px;'
                + 'background:rgba(15,23,42,.55);backdrop-filter:blur(2px);opacity:0;transition:opacity .15s ease}'
                + '.ukg-overlay.ukg-show{opacity:1}'
                + '.ukg-dialog{width:100%;max-width:520px;max-height:calc(100vh - 32px);overflow:auto;background:#fff;color:#1e293b;'
                + 'border-radius:12px;box-shadow:0 24px 48px -12px rgba(15,23,42,.35);transform:translateY(8px) scale(.98);transition:transform .15s ease;'
                + 'font-family:inherit;font-size:14px;line-height:1.5;text-align:left}'
                + '.ukg-show .ukg-dialog{transform:none}'
                + '.ukg-head{display:flex;gap:14px;align-items:flex-start;padding:20px 22px 8px}'
                + '.ukg-icon{flex:0 0 44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fef3c7;color:#d97706}'
                + '.ukg-title{margin:2px 0 2px;font-size:17px;font-weight:600;color:#0f172a}'
                + '.ukg-subtitle{margin:0;color:#64748b;font-size:13px}'
                + '.ukg-body{padding:10px 22px 4px}'
                + '.ukg-table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin:0 0 14px}'
                + '.ukg-table th{background:#f8fafc;color:#64748b;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;padding:8px 10px;text-align:left;border-bottom:1px solid #e2e8f0}'
                + '.ukg-table td{padding:9px 10px;vertical-align:middle;border-top:1px solid #f1f5f9;word-break:break-word}'
                + '.ukg-table tbody tr:first-child td{border-top:0}'
                + '.ukg-field{color:#475569;font-weight:500;white-space:nowrap}'
                + '.ukg-before{color:#b91c1c;text-decoration:line-through;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}'
                + '.ukg-after{color:#15803d;font-weight:600;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}'
                + '.ukg-arrow{color:#94a3b8;width:1%;padding-left:0!important;padding-right:0!important;text-align:center}'
                + '.ukg-note{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 12px;color:#92400e;font-size:13px}'
                + '.ukg-note ul{margin:4px 0 0;padding-left:18px}'
                + '.ukg-note li{margin:2px 0}'
                + '.ukg-foot{display:flex;justify-content:flex-end;gap:8px;padding:16px 22px 20px}'
                + '.ukg-btn{appearance:none;border-radius:8px;padding:8px 16px;font-size:14px;font-weight:600;cursor:pointer;line-height:1.4;transition:background .12s,border-color .12s,box-shadow .12s}'
                + '.ukg-btn:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(59,130,246,.35)}'
                + '.ukg-btn-cancel{background:#fff;border:1px solid #cbd5e1;color:#334155}'
                + '.ukg-btn-cancel:hover{background:#f8fafc}'
                + '.ukg-btn-ok{background:#d97706;border:1px solid #d97706;color:#fff}'
                + '.ukg-btn-ok:hover{background:#b45309;border-color:#b45309}'
                + '.ukg-btn-ok:disabled{opacity:.7;cursor:wait}'
                + '@media (max-width:480px){.ukg-foot{flex-direction:column-reverse}.ukg-btn{width:100%}.ukg-field{white-space:normal}}';
            var style = document.createElement('style');
            style.id = 'ukg-style';
            style.textContent = css;
            document.head.appendChild(style);
        }
    })();
</script>
