const roleSidebar = document.getElementById('roleSidebar');
document.querySelector('[data-role-sidebar]')?.addEventListener('click', () => roleSidebar?.classList.toggle('open'));

const roleProfileToggle = document.querySelector('[data-role-profile-toggle]');
const roleProfileDropdown = document.querySelector('[data-role-profile-dropdown]');
if (roleProfileToggle && roleProfileDropdown) {
    roleProfileToggle.addEventListener('click', () => {
        const isOpen = roleProfileDropdown.classList.toggle('open');
        roleProfileToggle.setAttribute('aria-expanded', String(isOpen));
    });
    document.addEventListener('click', (event) => {
        if (!roleProfileToggle.contains(event.target) && !roleProfileDropdown.contains(event.target)) {
            roleProfileDropdown.classList.remove('open');
            roleProfileToggle.setAttribute('aria-expanded', 'false');
        }
    });
}

let activeRoleModal = null;
let modalOpener = null;

function closeRoleModal() {
    if (!activeRoleModal) return;
    activeRoleModal.classList.remove('open');
    activeRoleModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    activeRoleModal = null;
    modalOpener?.focus();
    modalOpener = null;
}

document.querySelectorAll('[data-open-modal]').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = document.getElementById(button.dataset.openModal);
        if (!modal) return;
        closeRoleModal();
        activeRoleModal = modal;
        modalOpener = button;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        modal.querySelector('.modal-close')?.focus();
    });
});

const scheduleModal = document.getElementById('scheduleActivityModal');
const scheduleForm = scheduleModal?.querySelector('form');
const scheduleLauncher = document.querySelector('[data-open-modal="scheduleActivityModal"]');
const scheduleAction = scheduleForm?.querySelector('[data-schedule-action]');
const scheduleRequestId = scheduleForm?.querySelector('[data-schedule-request-id]');
const scheduleSubmit = scheduleForm?.querySelector('[data-schedule-submit]');
const scheduleModalTitle = document.getElementById('scheduleActivityModalTitle');
const scheduleModalDescription = scheduleModal?.querySelector('[data-schedule-modal-description]');

scheduleLauncher?.addEventListener('click', () => {
    scheduleForm?.reset();
    if (scheduleForm?.elements.date_to) {
        scheduleForm.elements.date_to.min = scheduleForm.elements.date_from.min;
    }
    if (scheduleAction) scheduleAction.value = 'create';
    if (scheduleRequestId) scheduleRequestId.value = '';
    if (scheduleModalTitle) scheduleModalTitle.textContent = 'Schedule an Activity';
    if (scheduleModalDescription) scheduleModalDescription.textContent = 'The Super Admin must approve your request before it appears on the shared calendar.';
    if (scheduleSubmit) scheduleSubmit.innerHTML = '<i class="ri-calendar-event-line"></i> Schedule Activity';
});

document.querySelectorAll('[data-edit-schedule]').forEach((button) => {
    button.addEventListener('click', () => {
        if (!scheduleForm || !scheduleLauncher) return;
        let request;
        try {
            request = JSON.parse(button.dataset.editSchedule || '{}');
        } catch {
            return;
        }
        scheduleLauncher.click();
        scheduleAction.value = 'update';
        scheduleRequestId.value = request.id || '';
        scheduleForm.elements.title.value = request.title || '';
        scheduleForm.elements.activity_type.value = request.type || '';
        scheduleForm.elements.date_from.value = request.dateFrom || '';
        scheduleForm.elements.date_to.value = request.dateTo || request.dateFrom || '';
        scheduleForm.elements.date_to.min = request.dateFrom || '';
        scheduleForm.elements.venue.value = request.venue || '';
        scheduleForm.elements.description.value = request.description || '';
        scheduleModalTitle.textContent = 'Edit Schedule Request';
        scheduleModalDescription.textContent = 'Update your request. It will be sent to the Super Admin for approval.';
        scheduleSubmit.innerHTML = '<i class="ri-save-line"></i> Update Request';
    });
});

document.querySelectorAll('[data-close-modal]').forEach((element) => {
    element.addEventListener('click', closeRoleModal);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeRoleModal();
        roleProfileDropdown?.classList.remove('open');
        roleProfileToggle?.setAttribute('aria-expanded', 'false');
    }
});

const portalPagination = new Map();

function renderPortalPagination(container) {
    const state = portalPagination.get(container);
    if (!state) return;
    const { items, emptyItem, controls, pageSize, searchInput } = state;
    const matchingItems = items.filter((item) => item.dataset.searchMatch !== 'false');
    const pageCount = Math.max(1, Math.ceil(matchingItems.length / pageSize));
    state.currentPage = Math.min(state.currentPage, pageCount);
    const startIndex = (state.currentPage - 1) * pageSize;
    const endIndex = Math.min(startIndex + pageSize, matchingItems.length);
    const visibleItems = new Set(matchingItems.slice(startIndex, endIndex));

    items.forEach((item) => { item.hidden = !visibleItems.has(item); });
    if (emptyItem) {
        emptyItem.hidden = matchingItems.length > 0 || Boolean(searchInput?.value.trim());
    }

    const summary = controls.querySelector('[data-pagination-summary]');
    summary.textContent = matchingItems.length
        ? `Showing ${startIndex + 1}–${endIndex} of ${matchingItems.length}`
        : 'Showing 0 of 0';
    controls.querySelector('[data-pagination-previous]').disabled = state.currentPage <= 1;
    controls.querySelector('[data-pagination-next]').disabled = state.currentPage >= pageCount;
    controls.hidden = matchingItems.length === 0;
}

document.querySelectorAll('[data-paginate]').forEach((container) => {
    const isTable = container instanceof HTMLTableElement;
    const itemParent = isTable ? container.tBodies[0] : container;
    if (!itemParent) return;
    const children = [...itemParent.children];
    const emptyItem = children.find((item) => item.hasAttribute('data-pagination-empty')) || null;
    const items = children.filter((item) => item !== emptyItem);
    items.forEach((item) => { item.dataset.searchMatch = 'true'; });

    const controls = document.createElement('nav');
    controls.className = 'table-pagination';
    controls.setAttribute('aria-label', 'List pages');
    controls.innerHTML = '<button type="button" data-pagination-previous>Previous</button><span data-pagination-summary></span><button type="button" data-pagination-next>Next</button>';
    const anchor = isTable ? (container.closest('.table-scroll') || container) : container;
    anchor.insertAdjacentElement('afterend', controls);

    const tableId = isTable ? container.id : '';
    const searchInput = tableId
        ? document.querySelector(`[data-table-search="${tableId}"]`)
        : null;
    const state = {
        items,
        emptyItem,
        controls,
        pageSize: Math.max(1, Number(container.dataset.paginate) || 10),
        searchInput,
        currentPage: 1
    };
    portalPagination.set(container, state);
    controls.querySelector('[data-pagination-previous]').addEventListener('click', () => {
        state.currentPage -= 1;
        renderPortalPagination(container);
    });
    controls.querySelector('[data-pagination-next]').addEventListener('click', () => {
        state.currentPage += 1;
        renderPortalPagination(container);
    });
    renderPortalPagination(container);
});

document.querySelectorAll('[data-table-search]').forEach((input) => {
    const table = document.getElementById(input.dataset.tableSearch);
    if (!table) return;
    const rows = [...table.querySelectorAll('tbody tr')].filter((row) => !row.hasAttribute('data-pagination-empty'));
    const emptyMessage = document.querySelector(`[data-search-empty="${input.dataset.tableSearch}"]`);
    input.addEventListener('input', () => {
        const query = input.value.trim().toLocaleLowerCase();
        let visibleRows = 0;
        rows.forEach((row) => {
            const matches = row.textContent.toLocaleLowerCase().includes(query);
            const paginationState = portalPagination.get(table);
            if (paginationState) {
                row.dataset.searchMatch = String(matches);
            } else {
                row.hidden = !matches;
            }
            if (matches) visibleRows += 1;
        });
        if (emptyMessage) emptyMessage.hidden = query === '' || visibleRows > 0;
        if (portalPagination.has(table)) {
            portalPagination.get(table).currentPage = 1;
            renderPortalPagination(table);
        }
    });
});

document.querySelectorAll('[data-print-file]').forEach((button) => {
    button.addEventListener('click', () => {
        const printWindow = window.open('about:blank', '_blank');
        if (!printWindow) return;
        printWindow.addEventListener('load', () => {
            window.setTimeout(() => {
                try { printWindow.print(); } catch (error) { /* Browser preview may not expose print(). */ }
            }, 500);
        }, { once: true });
        printWindow.location.replace(new URL(button.dataset.printFile, window.location.href).href);
    });
});

document.querySelectorAll('[data-print-report]').forEach((button) => {
    button.addEventListener('click', () => {
        const row = button.closest('tr');
        const table = button.closest('table');
        const printWindow = window.open('', '_blank');
        if (!row || !table || !printWindow) {
            if (!printWindow) alert('Allow pop-ups to print this report.');
            return;
        }

        const headers = [...table.querySelectorAll('thead th')].slice(0, -1);
        const values = [...row.cells].slice(0, headers.length);
        const printDocument = printWindow.document;
        printDocument.open();
        printDocument.write('<!doctype html><html><head><meta charset="utf-8"><title>Research Submission Report</title><style>body{font:15px Arial,sans-serif;color:#17231c;margin:40px}h1{font-size:22px;color:#0f3d24;margin-bottom:6px}.date{color:#6f7b73;font-size:12px;margin-bottom:24px}table{width:100%;border-collapse:collapse}th,td{text-align:left;vertical-align:top;padding:12px;border:1px solid #dce5df}th{width:30%;background:#f2f8f4;color:#0f3d24}@media print{body{margin:18mm}}</style></head><body><h1>Research Submission Report</h1><p class="date">Printed <span id="printDate"></span></p><table><tbody id="reportDetails"></tbody></table></body></html>');
        printDocument.close();

        const date = printDocument.getElementById('printDate');
        date.textContent = new Date().toLocaleString();
        const details = printDocument.getElementById('reportDetails');
        headers.forEach((header, index) => {
            const tableRow = printDocument.createElement('tr');
            const label = printDocument.createElement('th');
            const value = printDocument.createElement('td');
            label.textContent = header.textContent.trim();
            value.textContent = values[index]?.textContent.trim() || '';
            tableRow.append(label, value);
            details.appendChild(tableRow);
        });

        window.setTimeout(() => {
            printWindow.focus();
            printWindow.print();
        }, 250);
    });
});

const rolePalette = ['#15803d', '#16a34a', '#facc15', '#fde047', '#0f3d24', '#86efac'];
const roleStatusColors = {
    approved: '#16a34a',
    completed: '#16a34a',
    declined: '#dc2626',
    rejected: '#dc2626',
    'under-review': '#f97316',
    pending: '#fde047'
};

function roleStatusKey(value) {
    return String(value || '').trim().toLowerCase().replace(/\s+/g, '-');
}

function roleChartColor(label, index) {
    return roleStatusColors[roleStatusKey(label)] || rolePalette[index % rolePalette.length];
}

function drawRoleChart(id, rows, label) {
    const canvas = document.getElementById(id);
    if (!canvas || !window.Chart) return;
    const labels = rows.map((row) => row.label);
    const isResearchLineChart = id === 'roleResearchChart';
    new Chart(canvas, {
        type: isResearchLineChart ? 'line' : 'doughnut',
        data: {
            labels,
            datasets: [isResearchLineChart
                ? {
                    label: 'Research Submissions',
                    data: rows.map((row) => Number(row.total)),
                    borderColor: '#15803d',
                    backgroundColor: 'rgba(21, 128, 61, .12)',
                    pointBackgroundColor: labels.map(roleChartColor),
                    pointBorderColor: labels.map(roleChartColor),
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    tension: .3,
                    fill: false
                }
                : { label, data: rows.map((row) => Number(row.total)), backgroundColor: labels.map(roleChartColor) }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            ...(isResearchLineChart ? {
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: { legend: { display: false } }
            } : {})
        }
    });
}

if (window.rolePortalCharts) {
    drawRoleChart('roleResearchChart', window.rolePortalCharts.research || [], 'Research');
    drawRoleChart('roleExtensionChart', window.rolePortalCharts.extension || [], 'Extension');
    drawRoleChart('roleDocumentChart', window.rolePortalCharts.documents || [], 'Documents');
}

const miniCalendar = document.getElementById('roleMiniCalendar');
if (miniCalendar) {
    const today = new Date();
    const start = new Date(today.getFullYear(), today.getMonth(), 1);
    const first = new Date(start);
    first.setDate(1 - start.getDay());
    const events = window.roleCalendarEvents || [];
    for (let i = 0; i < 42; i += 1) {
        const date = new Date(first);
        date.setDate(first.getDate() + i);
        const key = date.toISOString().slice(0, 10);
        const cell = document.createElement('div');
        cell.className = 'mini-day';
        cell.innerHTML = `<strong>${date.getDate()}</strong>`;
        events.filter((event) => event.start === key).slice(0, 3).forEach((event) => {
            const item = document.createElement('span');
            item.className = 'mini-event';
            item.textContent = event.title;
            cell.appendChild(item);
        });
        miniCalendar.appendChild(cell);
    }
}

document.querySelector('[data-print-page]')?.addEventListener('click', () => window.print());
document.querySelectorAll('[data-export-table]').forEach((button) => {
    button.addEventListener('click', () => {
        const table = document.getElementById(button.dataset.exportTable);
        if (!table) return;
        const csv = [...table.querySelectorAll('tr')].map((row) => [...row.children].map((cell) => `"${cell.textContent.replaceAll('"', '""').trim()}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'ovpre-report.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    });
});
