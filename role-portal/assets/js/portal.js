const roleSidebar = document.getElementById('roleSidebar');
document.querySelector('[data-role-sidebar]')?.addEventListener('click', () => roleSidebar?.classList.toggle('open'));

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

document.querySelectorAll('[data-close-modal]').forEach((element) => {
    element.addEventListener('click', closeRoleModal);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeRoleModal();
});

document.querySelectorAll('[data-table-search]').forEach((input) => {
    const table = document.getElementById(input.dataset.tableSearch);
    if (!table) return;
    const rows = [...table.querySelectorAll('tbody tr')];
    const emptyMessage = document.querySelector(`[data-search-empty="${input.dataset.tableSearch}"]`);
    input.addEventListener('input', () => {
        const query = input.value.trim().toLocaleLowerCase();
        let visibleRows = 0;
        rows.forEach((row) => {
            const matches = row.textContent.toLocaleLowerCase().includes(query);
            row.hidden = !matches;
            if (matches) visibleRows += 1;
        });
        if (emptyMessage) emptyMessage.hidden = query === '' || visibleRows > 0;
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
