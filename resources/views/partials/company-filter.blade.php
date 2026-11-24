{{--
    Filter "klik company" di atas daftar master (layouts.dashboard.index, opt-in $data['datatable']['company_filter'] dari
    Support\CompanyListFilter::config: text, columns, url, initial). Sel kolom `columns` dirender jadi tautan
    [data-company-filter] memakai company_filter_id / company_filter_label baris JSON; tautan yang sama boleh dipasang di
    card ringkasan. Klik (tanpa Ctrl/Shift = tab baru tetap jalan) memilih company itu: bar "Hanya ... <company>" tampil,
    URL halaman diberi ?company_id= (muat ulang tetap terfilter), dan daftar dimuat ulang dari halaman 1.
    Skrip: window.masterCompanyFilter.params() dipakai fungsi ajax DataTables daftar, decorateColumns() saat kolom dibuat.
--}}
@php
    $companyFilter = $data['datatable']['company_filter'];
    $companyFilterInitial = $companyFilter['initial'] ?? null;
    $companyFilterParam = \Bangsamu\Master\Support\CompanyListFilter::PARAM;
@endphp
<div @class(['mb-3', 'd-none' => $companyFilterInitial === null]) data-company-filter-bar>
    <div class="alert alert-info d-flex flex-wrap align-items-center py-2 px-3 mb-0" role="status">
        <span class="me-3 mr-3">
            <i class="fas fa-filter me-1 mr-1" aria-hidden="true"></i>{{ $companyFilter['text'] }}
            <strong data-company-filter-label>{{ $companyFilterInitial['label'] ?? '' }}</strong>
            <span @class(['badge bg-danger badge-danger text-bg-danger ms-1 ml-1', 'd-none' => empty($companyFilterInitial['deleted'])]) data-company-filter-deleted>company sudah dihapus</span>
        </span>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto ml-auto" data-company-filter-clear>
            <i class="fas fa-times me-1 mr-1" aria-hidden="true"></i>Tampilkan semua company
        </button>
    </div>
</div>
@push('css')
    <style>
        a.master-company-filter-link {
            text-decoration: underline dotted;
            text-underline-offset: 2px;
        }

        a.master-company-filter-link.is-active {
            font-weight: 700;
            text-decoration-style: solid;
        }
    </style>
@endpush
@push('js')
    <script>
        (function() {
            var config = @json(['columns' => $companyFilter['columns'], 'url' => $companyFilter['url'], 'param' => $companyFilterParam]);
            var initial = @json($companyFilterInitial);
            var current = initial ? initial.id : null;
            var bar = document.querySelector('[data-company-filter-bar]');
            var label = bar.querySelector('[data-company-filter-label]');
            var deleted = bar.querySelector('[data-company-filter-deleted]');

            function hrefFor(id) {
                var url = new URL(config.url, window.location.href);
                url.searchParams.set(config.param, id);
                return url.toString();
            }

            // ?company_id= di URL halaman ikut pilihan sekarang (muat ulang / salin URL tetap terfilter)
            function syncUrl() {
                if (!window.history || typeof window.history.replaceState !== 'function') {
                    return;
                }
                var url = new URL(window.location.href);
                if (current) {
                    url.searchParams.set(config.param, current);
                } else {
                    url.searchParams.delete(config.param);
                }
                window.history.replaceState(window.history.state, '', url.toString());
            }

            function reload() {
                if (window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable(tableName)) {
                    jQuery(tableName).DataTable().ajax.reload(); // kembali ke halaman 1
                }
            }

            function select(id, text) {
                current = id;
                label.textContent = id ? (text || ('#' + id)) : '';
                deleted.classList.add('d-none'); // status terhapus hanya diketahui saat halaman dibuka dengan ?company_id=
                bar.classList.toggle('d-none', !current);
                syncUrl();
                reload();
            }

            window.masterCompanyFilter = {
                params: function() {
                    var params = {};
                    if (current) {
                        params[config.param] = current;
                    }
                    return params;
                },
                // sel kolom company jadi tautan filter (isi sel sudah di-escape server, DataTableCell)
                decorateColumns: function(columns) {
                    $.each(columns, function(i, column) {
                        if (config.columns.indexOf(column.data) === -1) {
                            return;
                        }
                        column.render = function(value, type, row) {
                            var id = parseInt(row.company_filter_id, 10);
                            if (type !== 'display' || !id || value === null || value === '') {
                                return value;
                            }
                            return '<a href="' + hrefFor(id) + '" class="master-company-filter-link' + (id === current ? ' is-active' : '') + '"' +
                                ' data-company-filter="' + id + '" data-company-label="' + (row.company_filter_label || '') + '"' +
                                ' title="Klik: tampilkan hanya company ini">' + value + '</a>';
                        };
                    });
                },
                clear: function() {
                    select(null, '');
                }
            };

            bar.querySelector('[data-company-filter-clear]').addEventListener('click', function() {
                window.masterCompanyFilter.clear();
            });

            document.addEventListener('click', function(e) {
                var link = e.target.closest('a[data-company-filter]');
                if (!link || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
                    return; // Ctrl/Shift/klik tengah: buka tautan ?company_id= di tab baru seperti biasa
                }
                var id = parseInt(link.getAttribute('data-company-filter'), 10);
                if (!id) {
                    return;
                }
                e.preventDefault();
                select(id, link.getAttribute('data-company-label'));
                // klik dari card ringkasan di atas: bawa bar filter ke layar supaya hasilnya terlihat
                if (!link.closest('table.dataTable') && typeof bar.scrollIntoView === 'function') {
                    bar.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            });
        })();
    </script>
@endpush
