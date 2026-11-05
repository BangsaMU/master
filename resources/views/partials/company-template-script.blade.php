{{--
    Script kartu Template JSON di form company (tema master, master_tabler, master_meridian).
    Variabel: $templateData, $templateEditable (false = hanya baca, aplikasi selain Master Data), $appNames (kode => nama
    master_apps, label "kode | nama"), $ui (kelas CSS baris/grup buatan JS, beda per tema).
    Nilai template diisi lewat properti DOM (bukan innerHTML) karena isinya data dari aplikasi Master Data.
--}}
@php
    $templateData = is_array($templateData ?? null) ? $templateData : [];
    $templateEditable = $templateEditable ?? false;
    $appNames = $appNames ?? [];
@endphp
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const templateData = @json((object) $templateData);
        const editable = @json((bool) $templateEditable);
        const appNames = @json((object) $appNames);
        const ui = @json($ui);
        const dynamicFields = ['form_no', 'rev_no', 'issued_date'];
        const scalarFields = ['format_date', 'template_header'];
        const reservedFields = scalarFields.concat(['logo_pdf']);
        const selectedKeySelect = document.getElementById('selected_key');
        const templateJsonInput = document.getElementById('template_json_input');
        const logoKeyInput = document.getElementById('logo_pdf_key');
        const emptyNote = document.getElementById('template-empty-note');

        function sampleTemplate() {
            return {
                form_no: { spb: 'MEI-FLK-SPB-001', spj: 'MEI-FLK-SPJ-001', spa: 'MEI-FLK-SPA-001' },
                rev_no: { spb: '1-spb', spj: '1-spj', spa: '1-spa' },
                issued_date: { spb: '2026-07-09', spj: '2026-07-10', spa: '2026-07-11' },
                format_date: 'F,Y',
                template_header: 1
            };
        }

        function appLabel(code) {
            return appNames[code] ? code + ' | ' + appNames[code] : code;
        }

        function fieldLabel(fieldName) {
            return fieldName.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        }

        function currentKey() {
            return selectedKeySelect ? selectedKeySelect.value : '';
        }

        function updateHiddenInput() {
            if (templateJsonInput) {
                templateJsonInput.value = JSON.stringify(templateData);
            }
        }

        function syncFieldData(fieldName) {
            const key = currentKey();
            if (!key) return;
            if (!templateData[key]) {
                templateData[key] = {};
            }

            const container = document.getElementById(`${fieldName}_container`);
            if (!container) return;

            const rows = container.querySelectorAll(`.${fieldName}-row`);

            if (rows.length === 0) {
                templateData[key][fieldName] = "";
                updateHiddenInput();
                return;
            }

            if (rows.length === 1) {
                const firstKey = rows[0].querySelector(`.${fieldName}-key`).value.trim();
                const firstVal = rows[0].querySelector(`.${fieldName}-value`).value.trim();
                if (firstKey === '') {
                    templateData[key][fieldName] = firstVal;
                    updateHiddenInput();
                    return;
                }
            }

            const obj = {};
            rows.forEach(row => {
                const k = row.querySelector(`.${fieldName}-key`).value.trim();
                const v = row.querySelector(`.${fieldName}-value`).value.trim();
                if (k !== '') {
                    obj[k] = v;
                }
            });
            templateData[key][fieldName] = obj;
            updateHiddenInput();
        }

        function createFieldRow(containerId, subKey = '', subVal = '', fieldName = 'form_no') {
            const container = document.getElementById(containerId);
            if (!container) return;

            const row = document.createElement('div');
            row.className = `${ui.row} ${fieldName}-row`;

            const keyInput = document.createElement('input');
            keyInput.type = 'text';
            keyInput.className = `${ui.keyInput} ${fieldName}-key`;
            keyInput.style.maxWidth = '35%';
            keyInput.placeholder = 'Key (e.g. spb)';
            keyInput.value = subKey;

            const valInput = document.createElement('input');
            valInput.type = 'text';
            valInput.className = `${ui.valueInput} ${fieldName}-value`;
            valInput.placeholder = `${fieldLabel(fieldName)} (e.g. value)`;
            valInput.value = subVal;

            row.append(keyInput, valInput);

            if (editable) {
                const btnRemove = document.createElement('button');
                btnRemove.type = 'button';
                btnRemove.className = `${ui.removeButton} btn-remove-row`;
                btnRemove.title = 'Remove';
                btnRemove.innerHTML = '&times;';
                btnRemove.addEventListener('click', function() {
                    row.remove();
                    syncFieldData(fieldName);
                });
                row.append(btnRemove);

                keyInput.addEventListener('input', () => syncFieldData(fieldName));
                valInput.addEventListener('input', () => syncFieldData(fieldName));
            } else {
                keyInput.readOnly = true;
                valInput.readOnly = true;
            }

            container.appendChild(row);
        }

        function renderFieldContainer(fieldName, fieldValue) {
            const containerId = `${fieldName}_container`;
            const container = document.getElementById(containerId);
            if (!container) return;
            container.innerHTML = '';

            if (typeof fieldValue === 'string' || typeof fieldValue === 'number') {
                createFieldRow(containerId, '', fieldValue, fieldName);
            } else if (typeof fieldValue === 'object' && fieldValue !== null && !Array.isArray(fieldValue)) {
                const keys = Object.keys(fieldValue);
                if (keys.length === 0) {
                    if (editable) createFieldRow(containerId, '', '', fieldName);
                } else {
                    keys.forEach(k => {
                        createFieldRow(containerId, k, fieldValue[k], fieldName);
                    });
                }
            } else if (editable) {
                createFieldRow(containerId, '', '', fieldName);
            }
        }

        function bindAddButton(button, fieldName) {
            if (!button || !editable) return;
            button.addEventListener('click', function() {
                createFieldRow(`${fieldName}_container`, '', '', fieldName);
                syncFieldData(fieldName);
            });
        }

        function ensureFieldContainer(fieldName) {
            const existing = document.getElementById(`${fieldName}_container`);
            if (existing) return existing;

            const dynamicFieldsWrapper = document.getElementById('dynamic-form-fields');
            if (!dynamicFieldsWrapper) return null;

            const fieldGroup = document.createElement('div');
            fieldGroup.className = `${ui.fieldGroup} custom-dynamic-field-group`;
            fieldGroup.id = `field_group_${fieldName}`;

            const head = document.createElement('div');
            head.className = ui.fieldHead;
            const label = document.createElement('label');
            label.className = ui.label;
            label.textContent = fieldLabel(fieldName);
            head.append(label);

            if (editable) {
                const btnAdd = document.createElement('button');
                btnAdd.type = 'button';
                btnAdd.className = ui.addButton;
                btnAdd.id = `btn_add_${fieldName}`;
                btnAdd.textContent = `+ Add ${fieldLabel(fieldName)}`;
                head.append(btnAdd);
                bindAddButton(btnAdd, fieldName);
            }

            const container = document.createElement('div');
            container.id = `${fieldName}_container`;
            if (ui.container) container.className = ui.container;

            fieldGroup.append(head, container);

            const formatDateEl = document.getElementById('field_format_date');
            const formatDateGroup = formatDateEl ? formatDateEl.closest(ui.groupSelector) : null;

            if (formatDateGroup && formatDateGroup.parentNode === dynamicFieldsWrapper) {
                dynamicFieldsWrapper.insertBefore(fieldGroup, formatDateGroup);
            } else {
                dynamicFieldsWrapper.appendChild(fieldGroup);
            }

            return container;
        }

        function storedLogo(key, field) {
            const data = templateData[key];
            const logos = data && data.logo_pdf && typeof data.logo_pdf === 'object' ? data.logo_pdf : {};
            return typeof logos[field] === 'string' ? logos[field] : '';
        }

        function showLogo(box, src) {
            const img = box.querySelector('.template-logo-preview');
            const empty = box.querySelector('.template-logo-empty');
            const remove = box.querySelector('.template-logo-remove');
            if (src) {
                img.src = src;
                img.style.display = '';
            } else {
                img.removeAttribute('src');
                img.style.display = 'none';
            }
            if (empty) empty.style.display = src ? 'none' : '';
            if (remove) remove.style.display = src ? '' : 'none';
        }

        /* logo per App Code Key: file yang dipilih ikut terkirim saat form disimpan, untuk key yang sedang dipilih */
        function renderLogos(key) {
            if (logoKeyInput) logoKeyInput.value = key || '';
            document.querySelectorAll('.template-logo').forEach(box => {
                const file = box.querySelector('.template-logo-file');
                if (file) file.value = '';
                showLogo(box, storedLogo(key, box.dataset.field));
            });
        }

        function loadKeyData(key) {
            document.querySelectorAll('.custom-dynamic-field-group').forEach(el => el.remove());

            if (!templateData[key]) {
                if (!editable) {
                    dynamicFields.forEach(fieldName => renderFieldContainer(fieldName, undefined));
                    scalarFields.forEach(field => {
                        const el = document.getElementById('field_' + field);
                        if (el) el.value = '';
                    });
                    if (emptyNote) emptyNote.style.display = '';
                    renderLogos(key);
                    return;
                }
                templateData[key] = sampleTemplate();
            }
            if (emptyNote) emptyNote.style.display = 'none';

            const data = templateData[key];

            const formatDateEl = document.getElementById('field_format_date');
            if (formatDateEl) formatDateEl.value = data.format_date || '';
            const templateHeaderEl = document.getElementById('field_template_header');
            if (templateHeaderEl) templateHeaderEl.value = data.template_header !== undefined ? data.template_header : (editable ? 1 : '');

            dynamicFields.forEach(fieldName => renderFieldContainer(fieldName, data[fieldName]));

            Object.keys(data).forEach(fieldName => {
                if (reservedFields.includes(fieldName) || dynamicFields.includes(fieldName)) return;
                ensureFieldContainer(fieldName);
                renderFieldContainer(fieldName, data[fieldName]);
            });

            renderLogos(key);
            updateHiddenInput();
        }

        dynamicFields.forEach(fieldName => bindAddButton(document.getElementById(`btn_add_${fieldName}`), fieldName));

        scalarFields.forEach(field => {
            const el = document.getElementById('field_' + field);
            if (!el) return;
            if (!editable) {
                el.readOnly = true;
                return;
            }
            const updateValue = function() {
                const key = currentKey();
                if (!templateData[key]) {
                    templateData[key] = {};
                }
                if (field === 'template_header') {
                    templateData[key][field] = /^\d+$/.test(el.value.trim()) ? parseInt(el.value.trim()) : el.value.trim();
                } else {
                    templateData[key][field] = el.value;
                }
                updateHiddenInput();
            };
            el.addEventListener('input', updateValue);
            el.addEventListener('change', updateValue);
        });

        document.querySelectorAll('.template-logo').forEach(box => {
            const file = box.querySelector('.template-logo-file');
            const remove = box.querySelector('.template-logo-remove');
            if (file) {
                file.addEventListener('change', function() {
                    showLogo(box, file.files && file.files[0] ? URL.createObjectURL(file.files[0]) : storedLogo(currentKey(), box.dataset.field));
                });
            }
            if (remove) {
                remove.addEventListener('click', function() {
                    if (file) file.value = '';
                    const data = templateData[currentKey()];
                    if (data && data.logo_pdf && typeof data.logo_pdf === 'object') {
                        delete data.logo_pdf[box.dataset.field];
                        if (Object.keys(data.logo_pdf).length === 0) delete data.logo_pdf;
                        updateHiddenInput();
                    }
                    showLogo(box, '');
                });
            }
        });

        if (selectedKeySelect) {
            selectedKeySelect.addEventListener('change', function() {
                loadKeyData(this.value);
            });

            loadKeyData(selectedKeySelect.value);
        }

        const btnAddKey = document.getElementById('btn_add_key');
        if (btnAddKey && editable) {
            btnAddKey.addEventListener('click', function() {
                const newKey = prompt('Enter new App Code key (e.g. APP32):');
                if (!newKey) return;
                const sanitizedKey = newKey.trim().toUpperCase();
                if (sanitizedKey === '') return;

                if (templateData[sanitizedKey]) {
                    alert('Key already exists!');
                    selectedKeySelect.value = sanitizedKey;
                    loadKeyData(sanitizedKey);
                    return;
                }
                if (Object.keys(appNames).length > 0 && !appNames[sanitizedKey]
                    && !confirm(`App Code ${sanitizedKey} tidak terdaftar di master_apps. Tetap tambahkan?`)) {
                    return;
                }

                templateData[sanitizedKey] = sampleTemplate();

                const opt = document.createElement('option');
                opt.value = sanitizedKey;
                opt.textContent = appLabel(sanitizedKey);
                selectedKeySelect.appendChild(opt);

                selectedKeySelect.value = sanitizedKey;
                loadKeyData(sanitizedKey);
            });
        }

        const btnAddField = document.getElementById('btn_add_field');
        if (btnAddField && editable) {
            btnAddField.addEventListener('click', function() {
                const newFieldName = prompt('Enter new field name (e.g. header, footer, remarks):');
                if (!newFieldName) return;
                const sanitizedField = newFieldName.trim().toLowerCase().replace(/\s+/g, '_');
                if (sanitizedField === '') return;

                const key = currentKey();
                if (!templateData[key]) {
                    templateData[key] = {};
                }

                if (templateData[key][sanitizedField] !== undefined || reservedFields.includes(sanitizedField) || dynamicFields.includes(sanitizedField)) {
                    alert('Field already exists!');
                    return;
                }

                templateData[key][sanitizedField] = "";
                loadKeyData(key);
            });
        }
    });
</script>
