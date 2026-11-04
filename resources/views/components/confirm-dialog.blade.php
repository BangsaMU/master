{{--
    Dialog konfirmasi perubahan di form master (mandiri, tidak bergantung Bootstrap JS / theme). Dipakai
    components.unique-key-guard (kunci unik) dan partials.master-owner (pemindahan pemilik data). Boleh di-include lebih
    dari sekali dalam satu halaman; fungsi hanya dipasang sekali.

    window.masterConfirmDialog({
        title, subtitle, okText,
        changes: [{label, before, after}],
        noteTitle, notes: ['...'],
    }, onConfirm)
--}}
<script>
    (function () {
        if (window.masterConfirmDialog) return;

        window.masterConfirmDialog = function (opts, onConfirm) {
            injectStyle();

            var lastFocus = document.activeElement;
            var overlay = el('div', 'mcd-overlay');
            var dialog = el('div', 'mcd-dialog');
            dialog.setAttribute('role', 'alertdialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-labelledby', 'mcd-title');
            dialog.setAttribute('aria-describedby', 'mcd-desc');

            var head = el('div', 'mcd-head');
            var icon = el('div', 'mcd-icon');
            icon.innerHTML = '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4"/><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z"/><path d="M12 16h.01"/></svg>';
            var titles = el('div', 'mcd-titles');
            var title = el('h3', 'mcd-title', opts.title);
            title.id = 'mcd-title';
            var subtitle = el('p', 'mcd-subtitle', opts.subtitle || '');
            subtitle.id = 'mcd-desc';
            titles.appendChild(title);
            titles.appendChild(subtitle);
            head.appendChild(icon);
            head.appendChild(titles);

            var body = el('div', 'mcd-body');
            var table = el('table', 'mcd-table');
            var thead = el('thead');
            var hr = el('tr');
            ['Kolom', 'Sebelum', '', 'Sesudah'].forEach(function (t) { hr.appendChild(el('th', null, t)); });
            thead.appendChild(hr);
            var tbody = el('tbody');
            (opts.changes || []).forEach(function (c) {
                var tr = el('tr');
                tr.appendChild(el('td', 'mcd-field', c.label));
                tr.appendChild(el('td', 'mcd-before', c.before || '(kosong)'));
                tr.appendChild(el('td', 'mcd-arrow', '→'));
                tr.appendChild(el('td', 'mcd-after', c.after || '(kosong)'));
                tbody.appendChild(tr);
            });
            table.appendChild(thead);
            table.appendChild(tbody);
            body.appendChild(table);

            if (opts.notes && opts.notes.length) {
                var note = el('div', 'mcd-note');
                note.appendChild(el('strong', null, opts.noteTitle || 'Dampak perubahan:'));
                var ul = el('ul');
                opts.notes.forEach(function (t) { ul.appendChild(el('li', null, t)); });
                note.appendChild(ul);
                body.appendChild(note);
            }

            var foot = el('div', 'mcd-foot');
            var cancel = el('button', 'mcd-btn mcd-btn-cancel', 'Batal');
            cancel.type = 'button';
            var ok = el('button', 'mcd-btn mcd-btn-ok', opts.okText || 'Ya, simpan');
            ok.type = 'button';
            foot.appendChild(cancel);
            foot.appendChild(ok);

            dialog.appendChild(head);
            dialog.appendChild(body);
            dialog.appendChild(foot);
            overlay.appendChild(dialog);
            document.body.appendChild(overlay);
            document.body.classList.add('mcd-open');
            requestAnimationFrame(function () { overlay.classList.add('mcd-show'); });
            cancel.focus();

            var close = function (accepted) {
                document.removeEventListener('keydown', onKey, true);
                overlay.classList.remove('mcd-show');
                document.body.classList.remove('mcd-open');
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
        };

        function el(tag, cls, text) {
            var node = document.createElement(tag);
            if (cls) node.className = cls;
            if (text !== undefined) node.textContent = text; // textContent: aman dari HTML di nilai input
            return node;
        }

        function injectStyle() {
            if (document.getElementById('mcd-style')) return;
            var css = ''
                + '.mcd-open{overflow:hidden}'
                + '.mcd-overlay{position:fixed;inset:0;z-index:2050;display:flex;align-items:center;justify-content:center;padding:16px;'
                + 'background:rgba(15,23,42,.55);backdrop-filter:blur(2px);opacity:0;transition:opacity .15s ease}'
                + '.mcd-overlay.mcd-show{opacity:1}'
                + '.mcd-dialog{width:100%;max-width:520px;max-height:calc(100vh - 32px);overflow:auto;background:#fff;color:#1e293b;'
                + 'border-radius:12px;box-shadow:0 24px 48px -12px rgba(15,23,42,.35);transform:translateY(8px) scale(.98);transition:transform .15s ease;'
                + 'font-family:inherit;font-size:14px;line-height:1.5;text-align:left}'
                + '.mcd-show .mcd-dialog{transform:none}'
                + '.mcd-head{display:flex;gap:14px;align-items:flex-start;padding:20px 22px 8px}'
                + '.mcd-icon{flex:0 0 44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fef3c7;color:#d97706}'
                + '.mcd-title{margin:2px 0 2px;font-size:17px;font-weight:600;color:#0f172a}'
                + '.mcd-subtitle{margin:0;color:#64748b;font-size:13px}'
                + '.mcd-body{padding:10px 22px 4px}'
                + '.mcd-table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin:0 0 14px}'
                + '.mcd-table th{background:#f8fafc;color:#64748b;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;padding:8px 10px;text-align:left;border-bottom:1px solid #e2e8f0}'
                + '.mcd-table td{padding:9px 10px;vertical-align:middle;border-top:1px solid #f1f5f9;word-break:break-word}'
                + '.mcd-table tbody tr:first-child td{border-top:0}'
                + '.mcd-field{color:#475569;font-weight:500;white-space:nowrap}'
                + '.mcd-before{color:#b91c1c;text-decoration:line-through;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}'
                + '.mcd-after{color:#15803d;font-weight:600;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}'
                + '.mcd-arrow{color:#94a3b8;width:1%;padding-left:0!important;padding-right:0!important;text-align:center}'
                + '.mcd-note{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 12px;color:#92400e;font-size:13px}'
                + '.mcd-note ul{margin:4px 0 0;padding-left:18px}'
                + '.mcd-note li{margin:2px 0}'
                + '.mcd-foot{display:flex;justify-content:flex-end;gap:8px;padding:16px 22px 20px}'
                + '.mcd-btn{appearance:none;border-radius:8px;padding:8px 16px;font-size:14px;font-weight:600;cursor:pointer;line-height:1.4;transition:background .12s,border-color .12s,box-shadow .12s}'
                + '.mcd-btn:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(59,130,246,.35)}'
                + '.mcd-btn-cancel{background:#fff;border:1px solid #cbd5e1;color:#334155}'
                + '.mcd-btn-cancel:hover{background:#f8fafc}'
                + '.mcd-btn-ok{background:#d97706;border:1px solid #d97706;color:#fff}'
                + '.mcd-btn-ok:hover{background:#b45309;border-color:#b45309}'
                + '.mcd-btn-ok:disabled{opacity:.7;cursor:wait}'
                + '@media (max-width:480px){.mcd-foot{flex-direction:column-reverse}.mcd-btn{width:100%}.mcd-field{white-space:normal}}';
            var style = document.createElement('style');
            style.id = 'mcd-style';
            style.textContent = css;
            document.head.appendChild(style);
        }
    })();
</script>
