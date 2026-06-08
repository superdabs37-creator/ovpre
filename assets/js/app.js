const sidebar = document.querySelector('#sidebar');
const sidebarToggle = document.querySelector('[data-toggle-sidebar]');
const profileToggle = document.querySelector('[data-profile-toggle]');
const profileDropdown = document.querySelector('[data-profile-dropdown]');

if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });
}

if (profileToggle && profileDropdown) {
    profileToggle.addEventListener('click', () => {
        profileDropdown.classList.toggle('open');
    });

    document.addEventListener('click', (event) => {
        if (!profileToggle.contains(event.target) && !profileDropdown.contains(event.target)) {
            profileDropdown.classList.remove('open');
        }
    });
}

const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: {
                color: '#17231c',
                boxWidth: 12,
                usePointStyle: true
            }
        }
    }
};

const palette = ['#15803d', '#16a34a', '#facc15', '#fde047', '#0f3d24', '#86efac'];

const pieCanvas = document.getElementById('pieChart');
if (pieCanvas) {
    const statusRows = window.irecordCharts?.status || [];
    new Chart(pieCanvas, {
        type: 'pie',
        data: {
            labels: statusRows.length ? statusRows.map((row) => row.label) : ['Pending', 'Approved'],
            datasets: [{
                data: statusRows.length ? statusRows.map((row) => Number(row.total)) : [0, 0],
                backgroundColor: palette,
                borderColor: '#ffffff',
                borderWidth: 3
            }]
        },
        options: chartDefaults
    });
}

const barCanvas = document.getElementById('barChart');
if (barCanvas) {
    const monthlyRows = window.irecordCharts?.monthly || [];
    new Chart(barCanvas, {
        type: 'bar',
        data: {
            labels: monthlyRows.length ? monthlyRows.map((row) => row.label) : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Uploads',
                data: monthlyRows.length ? monthlyRows.map((row) => Number(row.total)) : [0, 0, 0, 0, 0, 0],
                backgroundColor: '#16a34a',
                borderRadius: 10
            }]
        },
        options: {
            ...chartDefaults,
            scales: {
                x: { grid: { display: false }, ticks: { color: '#6f7b73' } },
                y: { grid: { color: '#e6ede8' }, ticks: { color: '#6f7b73' } }
            }
        }
    });
}

const lineCanvas = document.getElementById('lineChart');
if (lineCanvas) {
    const extensionRows = window.irecordCharts?.extension || [];
    new Chart(lineCanvas, {
        type: 'line',
        data: {
            labels: extensionRows.length ? extensionRows.map((row) => row.label) : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Extension Projects',
                data: extensionRows.length ? extensionRows.map((row) => Number(row.total)) : [0, 0, 0, 0, 0, 0],
                borderColor: '#15803d',
                backgroundColor: 'rgba(250, 204, 21, 0.25)',
                fill: true,
                tension: 0.42,
                pointBackgroundColor: '#facc15',
                pointBorderColor: '#0f3d24',
                pointRadius: 4
            }]
        },
        options: {
            ...chartDefaults,
            scales: {
                x: { grid: { display: false }, ticks: { color: '#6f7b73' } },
                y: { grid: { color: '#e6ede8' }, ticks: { color: '#6f7b73' } }
            }
        }
    });
}

function renderReportChart(id, type, rows, label) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    new Chart(canvas, {
        type,
        data: {
            labels: rows.map((row) => row.category_name || row.status),
            datasets: [{ label, data: rows.map((row) => Number(row.total)), backgroundColor: palette, borderColor: '#15803d', tension: 0.35 }]
        },
        options: chartDefaults
    });
}

if (window.reportCharts) {
    renderReportChart('reportCategoryChart', 'bar', window.reportCharts.categories || [], 'Documents');
    renderReportChart('reportResearchChart', 'doughnut', window.reportCharts.research || [], 'Research');
    renderReportChart('reportExtensionChart', 'line', window.reportCharts.extension || [], 'Extension');
}

document.querySelectorAll('[data-file-search]').forEach((input) => {
    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        document.querySelectorAll('[data-file-card]').forEach((card) => {
            card.style.display = card.textContent.toLowerCase().includes(query) ? '' : 'none';
        });
    });
});

const announcementSearch = document.querySelector('#announcementSearch');
const categoryFilter = document.querySelector('#categoryFilter');
const announcementCards = document.querySelectorAll('[data-announcement-card]');
const announcementEmpty = document.querySelector('#announcementEmpty');

function filterAnnouncements() {
    if (!announcementCards.length) return;

    const query = announcementSearch ? announcementSearch.value.trim().toLowerCase() : '';
    const category = categoryFilter ? categoryFilter.value : 'All';
    let visibleCount = 0;

    announcementCards.forEach((card) => {
        const matchesSearch = card.textContent.toLowerCase().includes(query);
        const matchesCategory = category === 'All' || card.dataset.category === category;
        const visible = matchesSearch && matchesCategory;
        card.style.display = visible ? '' : 'none';
        if (visible) visibleCount += 1;
    });

    if (announcementEmpty) {
        announcementEmpty.classList.toggle('show', visibleCount === 0);
    }
}

if (announcementSearch) announcementSearch.addEventListener('input', filterAnnouncements);
if (categoryFilter) categoryFilter.addEventListener('change', filterAnnouncements);

const announcementModal = document.querySelector('#announcementModal');
const modalTitle = document.querySelector('#modalTitle');
const modalCategory = document.querySelector('#modalCategory');
const modalDate = document.querySelector('#modalDate');
const modalAuthor = document.querySelector('#modalAuthor');
const modalContent = document.querySelector('#modalContent');
const modalAttachment = document.querySelector('#modalAttachment');

function closeAnnouncementModal() {
    if (!announcementModal) return;
    announcementModal.classList.remove('open');
    announcementModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

document.querySelectorAll('.read-more-btn').forEach((button) => {
    button.addEventListener('click', () => {
        if (!announcementModal) return;

        modalTitle.textContent = button.dataset.title || '';
        modalCategory.textContent = button.dataset.category || '';
        modalDate.textContent = button.dataset.date || '';
        modalAuthor.textContent = button.dataset.author || '';
        modalContent.textContent = button.dataset.content || '';

        const attachment = button.dataset.attachment || '';
        if (attachment) {
            modalAttachment.href = attachment;
            modalAttachment.classList.remove('hidden');
        } else {
            modalAttachment.classList.add('hidden');
        }

        announcementModal.classList.add('open');
        announcementModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    });
});

document.querySelectorAll('[data-close-modal]').forEach((element) => {
    element.addEventListener('click', closeAnnouncementModal);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeAnnouncementModal();
    }
});

document.querySelectorAll('[data-open-modal]').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = document.getElementById(button.dataset.openModal);
        if (!modal) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    });
});

const tableSearch = document.querySelector('[data-table-search]');
const statusFilter = document.querySelector('[data-status-filter]');

function filterRows() {
    const rows = document.querySelectorAll('[data-table-row]');
    const query = tableSearch ? tableSearch.value.trim().toLowerCase() : '';
    const status = statusFilter ? statusFilter.value : 'All';

    rows.forEach((row) => {
        const matchesText = row.textContent.toLowerCase().includes(query);
        const matchesStatus = status === 'All' || row.dataset.status === status;
        row.style.display = matchesText && matchesStatus ? '' : 'none';
    });
}

if (tableSearch) tableSearch.addEventListener('input', filterRows);
if (statusFilter) statusFilter.addEventListener('change', filterRows);

const uploadForm = document.getElementById('uploadForm');
const uploadProgressBar = document.getElementById('uploadProgressBar');
const documentFile = document.getElementById('documentFile');
const dropzone = document.getElementById('dropzone');

if (documentFile && dropzone) {
    documentFile.addEventListener('change', () => {
        const file = documentFile.files[0];
        if (file) {
            dropzone.querySelector('strong').textContent = file.name;
            dropzone.querySelector('span').textContent = `${Math.round(file.size / 1024)} KB selected`;
        }
    });
}

if (uploadForm && uploadProgressBar) {
    uploadForm.addEventListener('submit', (event) => {
        event.preventDefault();
        let progress = 0;
        uploadProgressBar.style.width = '0%';
        const timer = setInterval(() => {
            progress += 10;
            uploadProgressBar.style.width = `${progress}%`;
            if (progress >= 100) {
                clearInterval(timer);
                alert('Document submitted successfully. Tracking number generated.');
            }
        }, 90);
    });
}

const markAllRead = document.getElementById('markAllRead');
if (markAllRead) {
    markAllRead.addEventListener('click', () => {
        document.querySelectorAll('.notification-card').forEach((card) => {
            card.classList.remove('unread');
            card.classList.add('read');
            const pill = card.querySelector('.status-pill');
            if (pill) {
                pill.textContent = 'Read';
                pill.className = 'status-pill status-read';
            }
        });
    });
}
