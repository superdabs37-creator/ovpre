const sidebar = document.querySelector('#studentSidebar');
const sidebarToggle = document.querySelector('[data-student-sidebar-toggle]');
const profileToggle = document.querySelector('[data-profile-toggle]');
const profileDropdown = document.querySelector('[data-profile-dropdown]');

if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
}

if (profileToggle && profileDropdown) {
    profileToggle.addEventListener('click', () => profileDropdown.classList.toggle('open'));
    document.addEventListener('click', (event) => {
        if (!profileToggle.contains(event.target) && !profileDropdown.contains(event.target)) {
            profileDropdown.classList.remove('open');
        }
    });
}

const palette = ['#15803d', '#facc15', '#16a34a', '#dc2626', '#0f3d24'];
const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { color: '#17231c', boxWidth: 12, usePointStyle: true } } }
};

if (window.Chart && window.studentCharts) {
    const uploadCanvas = document.getElementById('studentUploadChart');
    if (uploadCanvas) {
        const rows = window.studentCharts.monthly || [];
        new Chart(uploadCanvas, {
            type: 'line',
            data: { labels: rows.map((row) => row.label), datasets: [{ label: 'Uploads', data: rows.map((row) => Number(row.total)), borderColor: '#15803d', backgroundColor: 'rgba(250,204,21,.22)', fill: true, tension: .38, pointBackgroundColor: '#facc15' }] },
            options: { ...chartDefaults, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: '#e6ede8' } } } }
        });
    }
    const approvalCanvas = document.getElementById('studentApprovalChart');
    if (approvalCanvas) {
        const rows = window.studentCharts.status || [];
        new Chart(approvalCanvas, {
            type: 'doughnut',
            data: { labels: rows.map((row) => row.label === 'Declined' ? 'Rejected' : row.label), datasets: [{ data: rows.map((row) => Number(row.total)), backgroundColor: palette, borderColor: '#ffffff', borderWidth: 3 }] },
            options: chartDefaults
        });
    }
}

const tableSearch = document.querySelector('[data-table-search]');
const statusFilter = document.querySelector('[data-status-filter]');
function filterRows() {
    const query = tableSearch ? tableSearch.value.trim().toLowerCase() : '';
    const status = statusFilter ? statusFilter.value : 'All';
    document.querySelectorAll('[data-table-row]').forEach((row) => {
        const visible = row.textContent.toLowerCase().includes(query) && (status === 'All' || row.dataset.status === status);
        row.style.display = visible ? '' : 'none';
    });
}
if (tableSearch) tableSearch.addEventListener('input', filterRows);
if (statusFilter) statusFilter.addEventListener('change', filterRows);

document.querySelectorAll('[data-card-search]').forEach((input) => {
    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        document.querySelectorAll('[data-card]').forEach((card) => {
            card.style.display = card.textContent.toLowerCase().includes(query) ? '' : 'none';
        });
    });
});

const fileInput = document.getElementById('documentFile');
const dropzone = document.getElementById('dropzone');
const progress = document.getElementById('uploadProgressBar');
if (dropzone && fileInput) {
    ['dragenter', 'dragover'].forEach((eventName) => dropzone.addEventListener(eventName, (event) => {
        event.preventDefault();
        dropzone.classList.add('dragging');
    }));
    ['dragleave', 'drop'].forEach((eventName) => dropzone.addEventListener(eventName, (event) => {
        event.preventDefault();
        dropzone.classList.remove('dragging');
    }));
    dropzone.addEventListener('drop', (event) => {
        fileInput.files = event.dataTransfer.files;
        fileInput.dispatchEvent(new Event('change'));
    });
    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (!file) return;
        dropzone.querySelector('strong').textContent = file.name;
        dropzone.querySelector('span').textContent = `${Math.ceil(file.size / 1024)} KB selected`;
        if (progress) progress.style.width = '35%';
    });
}
document.getElementById('uploadForm')?.addEventListener('submit', () => {
    if (progress) progress.style.width = '100%';
});

function closeModal() {
    document.querySelectorAll('.modal.open').forEach((modal) => {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    });
    document.body.style.overflow = '';
}
document.querySelectorAll('[data-open-modal]').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = document.getElementById(button.dataset.openModal);
        if (!modal) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    });
});
document.querySelectorAll('[data-close-modal]').forEach((element) => element.addEventListener('click', closeModal));
document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeModal(); });

document.querySelectorAll('[data-preview-title]').forEach((button) => {
    button.addEventListener('click', () => {
        document.getElementById('previewTitle').textContent = button.dataset.previewTitle || '';
        document.getElementById('previewMeta').textContent = button.dataset.previewMeta || '';
        document.getElementById('previewBody').textContent = button.dataset.previewBody || '';
        const modal = document.getElementById('previewModal');
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    });
});

const announcementSearch = document.getElementById('announcementSearch');
const categoryFilter = document.getElementById('categoryFilter');
function filterAnnouncements() {
    const query = announcementSearch ? announcementSearch.value.trim().toLowerCase() : '';
    const category = categoryFilter ? categoryFilter.value : 'All';
    let visible = 0;
    document.querySelectorAll('[data-announcement-card]').forEach((card) => {
        const show = card.textContent.toLowerCase().includes(query) && (category === 'All' || card.dataset.category === category);
        card.style.display = show ? '' : 'none';
        if (show) visible += 1;
    });
    document.getElementById('announcementEmpty')?.classList.toggle('show', visible === 0);
}
announcementSearch?.addEventListener('input', filterAnnouncements);
categoryFilter?.addEventListener('change', filterAnnouncements);

document.querySelectorAll('.read-more-btn').forEach((button) => {
    button.addEventListener('click', () => {
        document.getElementById('modalTitle').textContent = button.dataset.title || '';
        document.getElementById('modalCategory').textContent = button.dataset.category || '';
        document.getElementById('modalDate').textContent = button.dataset.date || '';
        document.getElementById('modalAuthor').textContent = button.dataset.author || '';
        document.getElementById('modalContent').textContent = button.dataset.content || '';
        const modal = document.getElementById('announcementModal');
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    });
});

document.querySelectorAll('[data-export-table]').forEach((button) => {
    button.addEventListener('click', () => {
        const table = document.getElementById('historyTable');
        if (!table) return;
        if (button.dataset.exportTable === 'pdf') {
            window.print();
            return;
        }
        const rows = [...table.querySelectorAll('tr')].map((row) => [...row.children].map((cell) => `"${cell.textContent.replaceAll('"', '""').trim()}"`).join(',')).join('\n');
        const blob = new Blob([rows], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'document-history.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    });
});
