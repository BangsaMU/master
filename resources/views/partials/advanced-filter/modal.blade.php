{{--
    Modal Filter Lanjutan untuk daftar master (layouts.dashboard.index). Variabel:
    - $config: AdvancedFilter::viewConfig() (fields, operators, max_depth, max_rules, max_value_length, options_url)
    - $tableSelector: selector tabel DataTables (#{slug}_tabel)

    Grup kondisi bertingkat (grup utama = tingkat 1, maks $config['max_depth']), tiap grup AND/OR (bawaan AND).
    Filter terpasang dikirim di setiap request DataTables sebagai advanced_filter (JSON), lewat
    window.masterAdvancedFilter.payload() di ajax.data layout. Disimpan per tab browser (sessionStorage) supaya tetap
    terpasang sesudah membuka View/Update lalu kembali ke daftar. Semua teks dari user/server dipasang lewat .text()/.val().
    Markup memakai kelas Bootstrap 4 + 5 sekaligus (paket dipakai aplikasi AdminLTE 3 dan 4).
--}}
<style>
    .master-advanced-filter .maf-group {
        --maf-accent: var(--bs-primary, #0d6efd);
        border: 1px solid var(--bs-border-color, #dee2e6);
        border-left: 3px solid var(--maf-accent);
        border-radius: .375rem;
        padding: .5rem .75rem .25rem;
        background-color: var(--bs-body-bg, #fff);
    }

    .master-advanced-filter .maf-group[data-depth="2"] {
        --maf-accent: var(--bs-info, #0dcaf0);
        background-color: var(--bs-tertiary-bg, #f8f9fa);
    }

    .master-advanced-filter .maf-group[data-depth="3"] {
        --maf-accent: var(--bs-warning, #ffc107);
        background-color: var(--bs-body-bg, #fff);
    }

    .master-advanced-filter .maf-group-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: .25rem;
    }

    .master-advanced-filter .maf-group-head > *,
    .master-advanced-filter .maf-rule > * {
        margin: 0 .375rem .375rem 0;
    }

    .master-advanced-filter .maf-group-title {
        font-weight: 600;
        font-size: .875rem;
    }

    .master-advanced-filter .maf-group-hint {
        font-size: .8125rem;
        color: var(--bs-secondary-color, #6c757d);
    }

    .master-advanced-filter .maf-group-actions {
        margin-left: auto !important;
        display: flex;
        flex-wrap: wrap;
    }

    .master-advanced-filter .maf-group-actions > * {
        margin: 0 0 0 .25rem;
    }

    .master-advanced-filter .maf-rule {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
    }

    .master-advanced-filter .maf-rule .maf-field {
        flex: 0 1 11rem;
        width: auto;
    }

    .master-advanced-filter .maf-rule .maf-op {
        flex: 0 1 10.5rem;
        width: auto;
    }

    .master-advanced-filter .maf-rule .maf-value {
        flex: 1 1 12rem;
        min-width: 8rem;
        width: auto;
    }

    .master-advanced-filter .maf-children > .maf-item > .maf-group {
        margin-bottom: .375rem;
    }

    /* penghubung antar kondisi: AND / OR sesuai logika grup induk */
    .master-advanced-filter .maf-children > .maf-item + .maf-item::before {
        display: block;
        margin: 0 0 .25rem .25rem;
        font-size: .6875rem;
        font-weight: 700;
        letter-spacing: .06em;
        color: var(--maf-accent);
    }

    .master-advanced-filter .maf-group[data-logic="and"] > .maf-children > .maf-item + .maf-item::before {
        content: "AND";
    }

    .master-advanced-filter .maf-group[data-logic="or"] > .maf-children > .maf-item + .maf-item::before {
        content: "OR";
    }

    .maf-summary {
        white-space: normal;
        overflow-wrap: anywhere;
    }
</style>

<div class="modal fade master-advanced-filter" id="maf-modal" tabindex="-1" aria-labelledby="maf-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="maf-modal-title"><i class="fas fa-filter mr-2 me-2" aria-hidden="true"></i>Filter Lanjutan</h5>
                <button type="button" class="close btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Tutup"><span aria-hidden="true" class="visually-hidden">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Kondisi dalam satu grup digabung dengan <b>AND</b> (semua harus cocok) atau <b>OR</b> (cukup salah satu).
                    Grup bisa bertingkat sampai {{ (int) $config['max_depth'] }}. Nilai dicari utuh (termasuk spasi dan koma),
                    tanpa beda huruf besar/kecil. Filter ini digabung (AND) dengan kotak cari di tabel.
                </p>
                <div data-maf-root></div>
                <div class="alert alert-danger small d-none mt-3 mb-0" data-maf-error role="alert"></div>
            </div>
            <div class="modal-footer">
                <span class="small text-muted mr-auto me-auto" data-maf-counter></span>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-maf-reset>
                    <i class="fas fa-eraser mr-1 me-1" aria-hidden="true"></i>Kosongkan
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-sm btn-primary" data-maf-apply>
                    <i class="fas fa-check mr-1 me-1" aria-hidden="true"></i>Terapkan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function ($) {
        if (!$ || window.masterAdvancedFilter) {
            return;
        }

        var config = @json($config);
        var tableSelector = @json($tableSelector);
        var storageKey = 'master-advanced-filter:' + window.location.pathname;

        var $modal = $('#maf-modal');
        var $root = $modal.find('[data-maf-root]');
        var $error = $modal.find('[data-maf-error]');
        var $counter = $modal.find('[data-maf-counter]');

        var fields = {};
        $.each(config.fields, function (i, f) { fields[f.field] = f; });
        var operators = {};
        $.each(config.operators, function (i, o) { operators[o.op] = o; });
        var defaultField = fields.description ? 'description' : config.fields[0].field;

        var applied = null; // filter terpasang (sudah dibersihkan), null = tanpa filter
        var optionsLoaded = false;

        function needsValue(op) {
            return !operators[op] || operators[op].value !== false;
        }

        function newRule() {
            return { field: defaultField, op: 'contains', value: '' };
        }

        function newGroup() {
            return { logic: 'and', rules: [newRule()] };
        }

        function isGroup(item) {
            return item && $.isArray(item.rules);
        }

        /* buang kondisi tanpa nilai dan grup kosong (sama dengan server); null = tidak ada kondisi */
        function clean(group, depth) {
            if (!isGroup(group) || depth > config.max_depth) {
                return null;
            }
            var rules = [];
            $.each(group.rules, function (i, item) {
                if (isGroup(item)) {
                    var sub = clean(item, depth + 1);
                    if (sub) {
                        rules.push(sub);
                    }
                    return;
                }
                if (!item || !fields[item.field] || !operators[item.op]) {
                    return;
                }
                var value = needsValue(item.op) ? $.trim(String(item.value == null ? '' : item.value)) : '';
                if (needsValue(item.op) && value === '') {
                    return;
                }
                rules.push({ field: item.field, op: item.op, value: value });
            });
            return rules.length ? { logic: group.logic === 'or' ? 'or' : 'and', rules: rules } : null;
        }

        function countRules(group) {
            var n = 0;
            $.each(group ? group.rules : [], function (i, item) {
                n += isGroup(item) ? countRules(item) : 1;
            });
            return n;
        }

        function describe(group) {
            var parts = [];
            $.each(group.rules, function (i, item) {
                if (isGroup(item)) {
                    parts.push('(' + describe(item) + ')');
                    return;
                }
                parts.push(fields[item.field].label + ' ' + operators[item.op].label
                    + (needsValue(item.op) ? ' "' + item.value + '"' : ''));
            });
            return parts.join(' ' + group.logic.toUpperCase() + ' ');
        }

        /* ---------- tampilan pembuat kondisi ---------- */

        function buildRule(rule) {
            var $rule = $('<div class="maf-rule"></div>');

            var $field = $('<select class="form-control form-control-sm form-select form-select-sm maf-field"></select>')
                .attr('aria-label', 'Kolom');
            $.each(config.fields, function (i, f) {
                $('<option></option>').val(f.field).text(f.label).appendTo($field);
            });
            $field.val(fields[rule.field] ? rule.field : defaultField);

            var $op = $('<select class="form-control form-control-sm form-select form-select-sm maf-op"></select>')
                .attr('aria-label', 'Operator');
            $.each(config.operators, function (i, o) {
                $('<option></option>').val(o.op).text(o.label).appendTo($op);
            });
            $op.val(operators[rule.op] ? rule.op : 'contains');

            var $value = $('<input type="text" class="form-control form-control-sm maf-value" autocomplete="off">')
                .attr({ maxlength: config.max_value_length, placeholder: 'Nilai', 'aria-label': 'Nilai' })
                .val(rule.value == null ? '' : String(rule.value));

            var $remove = $('<button type="button" class="btn btn-sm btn-outline-danger" data-maf-act="remove-rule"></button>')
                .attr({ title: 'Hapus kondisi', 'aria-label': 'Hapus kondisi' })
                .append('<i class="fas fa-times" aria-hidden="true"></i>');

            $rule.append($field, $op, $value, $remove);
            syncRule($rule);

            return $('<div class="maf-item"></div>').append($rule);
        }

        /* nilai ikut operator (kosong / tidak kosong = tanpa nilai) dan saran nilai ikut kolom */
        function syncRule($rule) {
            var field = fields[$rule.find('.maf-field').val()];
            var $value = $rule.find('.maf-value');
            var show = needsValue($rule.find('.maf-op').val());
            $value.toggleClass('d-none', !show).prop('disabled', !show);
            if (!show) {
                $value.removeClass('is-invalid');
            }
            if (field && field.suggest) {
                $value.attr('list', 'maf-opt-' + field.field);
            } else {
                $value.removeAttr('list');
            }
        }

        function setLogic($group, logic) {
            $group.attr('data-logic', logic);
            var $head = $group.children('.maf-group-head');
            $head.find('button[data-logic]').each(function () {
                var on = $(this).attr('data-logic') === logic;
                $(this).toggleClass('active', on).attr('aria-pressed', on ? 'true' : 'false');
            });
            $head.children('.maf-group-hint').text(logic === 'and' ? 'semua kondisi harus cocok' : 'cukup salah satu kondisi cocok');
        }

        function buildGroup(group, depth) {
            var $group = $('<div class="maf-group"></div>').attr('data-depth', depth);
            var $head = $('<div class="maf-group-head"></div>');

            $('<span class="maf-group-title"></span>')
                .text(depth === 1 ? 'Grup utama' : 'Subgrup tingkat ' + depth)
                .appendTo($head);

            var $logic = $('<div class="btn-group btn-group-sm" role="group"></div>')
                .attr('aria-label', 'Logika ' + (depth === 1 ? 'grup utama' : 'subgrup tingkat ' + depth));
            $.each(['and', 'or'], function (i, logic) {
                $('<button type="button" class="btn btn-outline-primary"></button>')
                    .attr('data-logic', logic)
                    .text(logic.toUpperCase())
                    .appendTo($logic);
            });
            $head.append($logic, '<span class="maf-group-hint"></span>');

            var $actions = $('<div class="maf-group-actions"></div>');
            $('<button type="button" class="btn btn-sm btn-outline-secondary" data-maf-act="add-rule"></button>')
                .append('<i class="fas fa-plus mr-1 me-1" aria-hidden="true"></i>', document.createTextNode('Kondisi'))
                .appendTo($actions);
            if (depth < config.max_depth) {
                $('<button type="button" class="btn btn-sm btn-outline-secondary" data-maf-act="add-group"></button>')
                    .append('<i class="fas fa-layer-group mr-1 me-1" aria-hidden="true"></i>', document.createTextNode('Subgrup'))
                    .attr('title', 'Tambah subgrup tingkat ' + (depth + 1))
                    .appendTo($actions);
            }
            if (depth > 1) {
                $('<button type="button" class="btn btn-sm btn-outline-danger" data-maf-act="remove-group"></button>')
                    .attr({ title: 'Hapus subgrup', 'aria-label': 'Hapus subgrup tingkat ' + depth })
                    .append('<i class="fas fa-trash-alt" aria-hidden="true"></i>')
                    .appendTo($actions);
            }
            $head.append($actions);

            var $children = $('<div class="maf-children"></div>');
            $.each(group.rules, function (i, item) {
                $children.append(isGroup(item) ? $('<div class="maf-item"></div>').append(buildGroup(item, depth + 1)) : buildRule(item));
            });

            $group.append($head, $children);
            setLogic($group, group.logic === 'or' ? 'or' : 'and');

            return $group;
        }

        /* isi modal -> objek filter (belum dibersihkan) */
        function readGroup($group) {
            var group = { logic: $group.attr('data-logic') === 'or' ? 'or' : 'and', rules: [] };
            $group.children('.maf-children').children('.maf-item').each(function () {
                var $child = $(this).children().first();
                if ($child.hasClass('maf-group')) {
                    group.rules.push(readGroup($child));
                    return;
                }
                var op = $child.find('.maf-op').val();
                group.rules.push({
                    field: $child.find('.maf-field').val(),
                    op: op,
                    value: needsValue(op) ? $child.find('.maf-value').val() : ''
                });
            });
            return group;
        }

        function render(group) {
            $root.empty().append(buildGroup(group || newGroup(), 1));
            $error.addClass('d-none').text('');
            refreshLimits();
        }

        /* batas jumlah kondisi: tombol tambah mati bila sudah maks */
        function refreshLimits() {
            var total = $root.find('.maf-rule').length;
            var full = total >= config.max_rules;
            $root.find('[data-maf-act="add-rule"], [data-maf-act="add-group"]').prop('disabled', full);
            $counter.text(total + ' dari maks ' + config.max_rules + ' kondisi');
        }

        function loadOptions() {
            if (optionsLoaded || !config.options_url) {
                return;
            }
            optionsLoaded = true;
            $.ajax({ url: config.options_url, dataType: 'json', type: 'GET' }).done(function (json) {
                $.each((json && json.options) || {}, function (field, values) {
                    var $list = $('<datalist></datalist>').attr('id', 'maf-opt-' + field);
                    $.each(values || [], function (i, value) {
                        $('<option></option>').attr('value', value).appendTo($list);
                    });
                    $modal.find('#maf-opt-' + field).remove();
                    $modal.append($list);
                });
            }).fail(function () {
                optionsLoaded = false; // dicoba lagi saat modal dibuka berikutnya
            });
        }

        /* ---------- modal (Bootstrap 5, Bootstrap 4 jQuery, atau cadangan tanpa Bootstrap) ---------- */

        function modalApi(action) {
            var el = $modal[0];
            if (window.bootstrap && window.bootstrap.Modal) {
                var instance = window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
                instance[action]();
            } else if ($.fn.modal) {
                $modal.modal(action);
            } else {
                $modal.toggleClass('d-block show', action === 'show').attr('aria-hidden', action === 'show' ? 'false' : 'true');
                if (action === 'show') {
                    $modal.trigger('shown.bs.modal');
                }
            }
        }

        function open() {
            render(applied ? JSON.parse(JSON.stringify(applied)) : null);
            loadOptions();
            modalApi('show');
        }

        /* ---------- filter terpasang ---------- */

        function store() {
            try {
                if (applied) {
                    window.sessionStorage.setItem(storageKey, JSON.stringify(applied));
                } else {
                    window.sessionStorage.removeItem(storageKey);
                }
            } catch (e) {
                // sessionStorage diblokir: filter hanya berlaku sampai halaman dimuat ulang
            }
        }

        function restore() {
            try {
                var saved = window.sessionStorage.getItem(storageKey);
                applied = saved ? clean(JSON.parse(saved), 1) : null;
            } catch (e) {
                applied = null;
            }
        }

        function refreshToolbar(message) {
            var count = countRules(applied);
            $('[data-maf-count]').text(count).toggleClass('d-none', count === 0);
            $('[data-maf-clear]').toggleClass('d-none', count === 0);
            $('[data-maf-open]').toggleClass('btn-primary', count > 0).toggleClass('btn-outline-primary', count === 0);
            var $summary = $('[data-maf-summary]');
            if (message) {
                $summary.removeClass('d-none text-muted').addClass('text-danger').text(message);
            } else if (count) {
                $summary.removeClass('d-none text-danger').addClass('text-muted').text('Filter lanjutan: ' + describe(applied));
            } else {
                $summary.addClass('d-none').text('');
            }
        }

        function redraw() {
            if ($.fn.dataTable && $.fn.dataTable.isDataTable(tableSelector)) {
                $(tableSelector).DataTable().draw(); // kembali ke halaman 1
            }
        }

        function setApplied(filter, message) {
            applied = filter;
            store();
            refreshToolbar(message);
            redraw();
        }

        function applyFromModal() {
            var draft = readGroup($root.children('.maf-group'));
            var filter = clean(draft, 1);

            // kondisi tanpa nilai: ditandai, kecuali semuanya kosong (= hapus filter)
            var $blank = $root.find('.maf-rule').filter(function () {
                return needsValue($(this).find('.maf-op').val()) && $.trim($(this).find('.maf-value').val()) === '';
            }).find('.maf-value');
            $root.find('.maf-value').removeClass('is-invalid');
            if (filter && $blank.length) {
                $blank.addClass('is-invalid');
                $error.removeClass('d-none').text($blank.length + ' kondisi belum diisi nilainya. Isi nilainya atau hapus barisnya.');
                $blank.first().trigger('focus');
                return;
            }

            modalApi('hide');
            setApplied(filter);
        }

        /* ---------- event ---------- */

        $(document).on('click', '[data-maf-open]', function (e) {
            e.preventDefault();
            open();
        });

        $(document).on('click', '[data-maf-clear]', function (e) {
            e.preventDefault();
            setApplied(null);
        });

        $modal.on('click', 'button[data-logic]', function () {
            setLogic($(this).closest('.maf-group'), $(this).attr('data-logic'));
        });

        $modal.on('click', '[data-maf-act]', function () {
            var $group = $(this).closest('.maf-group');
            var depth = parseInt($group.attr('data-depth'), 10) || 1;
            var $children = $group.children('.maf-children');
            var $focus = null;

            switch ($(this).attr('data-maf-act')) {
                case 'add-rule':
                    $focus = buildRule(newRule()).appendTo($children).find('.maf-field');
                    break;
                case 'add-group':
                    if (depth < config.max_depth) {
                        $focus = $('<div class="maf-item"></div>').append(buildGroup(newGroup(), depth + 1))
                            .appendTo($children).find('.maf-field').first();
                    }
                    break;
                case 'remove-group':
                    $group.parent('.maf-item').remove();
                    break;
                case 'remove-rule':
                    var $parentGroup = $(this).closest('.maf-group');
                    $(this).closest('.maf-item').remove();
                    // grup utama selalu menyisakan satu baris supaya ada yang bisa diisi
                    if ($parentGroup.attr('data-depth') === '1' && !$parentGroup.children('.maf-children').children().length) {
                        buildRule(newRule()).appendTo($parentGroup.children('.maf-children'));
                    }
                    break;
            }
            refreshLimits();
            if ($focus && $focus.length) {
                $focus.trigger('focus');
            }
        });

        $modal.on('change', '.maf-field, .maf-op', function () {
            syncRule($(this).closest('.maf-rule'));
        });

        $modal.on('input', '.maf-value', function () {
            $(this).removeClass('is-invalid');
        });

        $modal.on('keydown', '.maf-value', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFromModal();
            }
        });

        $modal.on('click', '[data-maf-apply]', applyFromModal);

        $modal.on('click', '[data-maf-reset]', function () {
            render(null);
            $root.find('.maf-field').first().trigger('focus');
        });

        $modal.on('click', '[data-dismiss="modal"], [data-bs-dismiss="modal"]', function () {
            if (!(window.bootstrap && window.bootstrap.Modal) && !$.fn.modal) {
                modalApi('hide');
            }
        });

        // ditunda: AdminLTE 4 (listener shown.bs.modal di document) memfokuskan tombol tutup sesudah handler ini
        $modal.on('shown.bs.modal', function () {
            setTimeout(function () {
                var $first = $root.find('.maf-value:visible').filter(function () { return this.value === ''; }).first();
                ($first.length ? $first : $root.find('.maf-field').first()).trigger('focus');
            }, 0);
        });

        restore();
        $(function () {
            refreshToolbar();
        });

        window.masterAdvancedFilter = {
            open: open,
            /** JSON untuk parameter advanced_filter (null = tanpa filter) */
            payload: function () {
                return applied ? JSON.stringify(applied) : null;
            },
            /** server menolak filter (422): filter dilepas, daftar dimuat ulang tanpa filter */
            rejected: function (message) {
                setApplied(null, 'Filter lanjutan dilepas: ' + (message || 'tidak valid.'));
            }
        };
    })(window.jQuery);
</script>
