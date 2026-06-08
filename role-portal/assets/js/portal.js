const roleSidebar = document.getElementById('roleSidebar');
document.querySelector('[data-role-sidebar]')?.addEventListener('click', () => roleSidebar?.classList.toggle('open'));

function drawRoleChart(id, rows, label) {
    const canvas = document.getElementById(id);
    if (!canvas || !window.Chart) return;
    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: rows.map((row) => row.label),
            datasets: [{ label, data: rows.map((row) => Number(row.total)), backgroundColor: ['#facc15', '#15803d', '#1d4ed8', '#dc2626', '#b45309'] }]
        },
        options: { responsive: true, maintainAspectRatio: false }
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
