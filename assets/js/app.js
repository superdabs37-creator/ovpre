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
        const isOpen = profileDropdown.classList.toggle('open');
        profileToggle.setAttribute('aria-expanded', String(isOpen));
    });

    document.addEventListener('click', (event) => {
        if (!profileToggle.contains(event.target) && !profileDropdown.contains(event.target)) {
            profileDropdown.classList.remove('open');
            profileToggle.setAttribute('aria-expanded', 'false');
        }
    });
}

const globalSearchForm = document.querySelector('[data-global-search-form]');
const topbarSearch = document.querySelector('[data-global-search]');
const topbarSearchClear = document.querySelector('[data-global-search-clear]');
const topbarSearchCount = document.querySelector('[data-global-search-count]');

function clearGlobalHighlights() {
    document.querySelectorAll('mark.global-search-hit').forEach((mark) => {
        mark.replaceWith(document.createTextNode(mark.textContent));
    });
}

function highlightFirstMatch(element, query) {
    if (!query || !element) return;
    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            const parent = node.parentElement;
            if (!parent || ['SCRIPT', 'STYLE', 'MARK', 'INPUT', 'TEXTAREA', 'SELECT'].includes(parent.tagName)) {
                return NodeFilter.FILTER_REJECT;
            }
            return node.nodeValue.toLowerCase().includes(query) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
        }
    });
    const node = walker.nextNode();
    if (!node) return;

    const text = node.nodeValue;
    const index = text.toLowerCase().indexOf(query);
    const range = document.createRange();
    range.setStart(node, index);
    range.setEnd(node, index + query.length);
    const mark = document.createElement('mark');
    mark.className = 'global-search-hit';
    range.surroundContents(mark);
}

if (globalSearchForm && topbarSearch) {
    const searchableItems = () => Array.from(document.querySelectorAll([
        '.data-table tbody tr',
        '[data-file-card]',
        '[data-announcement-card]',
        '.summary-card',
        '.file-card',
        '.folder-card',
        '.list-card',
        '.notification-item',
        '.panel'
    ].join(','))).filter((item) => !item.closest('.topbar') && !(item.classList.contains('panel') && item.querySelector('.data-table')));

    function runGlobalSearch() {
        clearGlobalHighlights();
        const query = topbarSearch.value.trim().toLowerCase();
        const items = searchableItems();
        let matches = 0;

        globalSearchForm.classList.toggle('has-query', Boolean(query));

        items.forEach((item) => {
            const matched = !query || item.textContent.toLowerCase().includes(query);
            item.classList.toggle('global-search-hidden', !matched);
            if (query && matched) {
                matches += 1;
                highlightFirstMatch(item, query);
            }
        });
        resetAdminTablePagination();
        refreshAdminTablePagination();

        if (topbarSearchCount) {
            topbarSearchCount.textContent = query ? `${matches} result${matches === 1 ? '' : 's'}` : '';
        }
    }

    topbarSearch.addEventListener('input', runGlobalSearch);

    globalSearchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        runGlobalSearch();
        const firstMatch = document.querySelector('.global-search-hit');
        if (firstMatch) {
            firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    if (topbarSearchClear) {
        topbarSearchClear.addEventListener('click', () => {
            topbarSearch.value = '';
            runGlobalSearch();
            topbarSearch.focus();
        });
    }
}

document.querySelectorAll('.add-user-btn, .content-area .primary-btn:not(.action-btn), .content-area .post-btn, .content-area .ghost-btn:not(.action-btn):not(.mini-btn), .content-area .portal-link, .login-form .primary-btn, .modal-card .primary-btn:not(.action-btn), .modal-card .ghost-btn:not(.action-btn), .tool-panel .primary-btn, .tool-panel .ghost-btn, .report-actions .primary-btn, .report-actions .ghost-btn').forEach((button) => {
    button.addEventListener('mouseenter', () => {
        button.classList.remove('is-hovering', 'premium-action-hover');
        window.requestAnimationFrame(() => button.classList.add('is-hovering', 'premium-action-hover'));
    });

    button.addEventListener('animationend', () => {
        button.classList.remove('is-hovering', 'premium-action-hover');
    });
});

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
const statusColors = {
    approved: '#16a34a',
    completed: '#16a34a',
    declined: '#dc2626',
    rejected: '#dc2626',
    'under-review': '#facc15',
    pending: '#fde047'
};

function normalizeStatusLabel(value) {
    return String(value || '').trim().toLowerCase().replace(/\s+/g, '-');
}

function chartColorForLabel(value, fallbackIndex = 0) {
    return statusColors[normalizeStatusLabel(value)] || palette[fallbackIndex % palette.length];
}

function chartColorsForRows(rows) {
    return rows.map((row, index) => {
        const label = row.status || row.label || row.progress_status || row.department_name || row.category_name;
        return chartColorForLabel(label, index);
    });
}

const pieCanvas = document.getElementById('pieChart');
if (pieCanvas && window.Chart) {
    const statusRows = window.irecordCharts?.status || [];
    const labels = statusRows.length ? statusRows.map((row) => row.label) : ['Pending', 'Approved'];
    new Chart(pieCanvas, {
        type: 'pie',
        data: {
            labels,
            datasets: [{
                data: statusRows.length ? statusRows.map((row) => Number(row.total)) : [0, 0],
                backgroundColor: labels.map((label, index) => chartColorForLabel(label, index)),
                borderColor: '#ffffff',
                borderWidth: 3
            }]
        },
        options: chartDefaults
    });
}

const barCanvas = document.getElementById('barChart');
if (barCanvas && window.Chart) {
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
if (lineCanvas && window.Chart) {
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
    if (!canvas || !window.Chart) return;
    const labels = rows.map((row) => row.label || row.department_name || row.category_name || row.status);
    const statusAwareColors = chartColorsForRows(rows);
    const isLine = type === 'line';
    new Chart(canvas, {
        type,
        data: {
            labels,
            datasets: [{
                label,
                data: rows.map((row) => Number(row.total)),
                backgroundColor: isLine ? statusAwareColors.map((color) => `${color}33`) : statusAwareColors,
                borderColor: isLine ? statusAwareColors : '#15803d',
                pointBackgroundColor: statusAwareColors,
                tension: 0.35
            }]
        },
        options: chartDefaults
    });
}

if (window.reportCharts) {
    renderReportChart('reportCategoryChart', 'bar', window.reportCharts.categories || [], 'Documents');
    renderReportChart('reportMonthlyChart', 'bar', window.reportCharts.monthly || [], 'Submissions');
    renderReportChart('reportResearchChart', 'doughnut', window.reportCharts.research || [], 'Research');
    renderReportChart('reportExtensionChart', 'line', window.reportCharts.extension || [], 'Extension');
    renderReportChart('reportDepartmentChart', 'bar', window.reportCharts.departments || [], 'Department Output');
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

function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function closeAnnouncementModal() {
    closeModal(announcementModal);
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
    element.addEventListener('click', () => {
        const modal = element.closest('.modal');
        if (modal) {
            closeModal(modal);
        } else {
            closeAnnouncementModal();
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        const profileModal = document.getElementById('profileEditorModal');
        if (profileModal && profileModal.classList.contains('open')) {
            window.location.href = profileModal.dataset.returnUrl || 'dashboard.php';
            return;
        }
        document.querySelectorAll('.modal.open').forEach(closeModal);
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

document.addEventListener('click', (event) => {
    const clickedDropdown = event.target.closest('.user-actions-dropdown');
    document.querySelectorAll('.user-actions-dropdown[open]').forEach((dropdown) => {
        if (dropdown !== clickedDropdown || event.target.closest('.user-action-menu .action-btn')) {
            dropdown.open = false;
        }
    });
});

const tableSearch = document.querySelector('[data-table-search]');
const statusFilter = document.querySelector('[data-status-filter]');
const categoryFilterCards = document.querySelectorAll('[data-category-filter]');
const recordsPanelTitle = document.getElementById('recordsPanelTitle');
const recordsPanelSubtitle = document.getElementById('recordsPanelSubtitle');
let activeRecordsCategory = 'All';

function filterRows() {
    const rows = document.querySelectorAll('[data-table-row]');
    const query = tableSearch ? tableSearch.value.trim().toLowerCase() : '';
    const status = statusFilter ? statusFilter.value : 'All';

    rows.forEach((row) => {
        const matchesText = row.textContent.toLowerCase().includes(query);
        const matchesStatus = status === 'All' || row.dataset.status === status;
        const matchesCategory = activeRecordsCategory === 'All' || !row.dataset.category || row.dataset.category === activeRecordsCategory;
        row.dataset.localFilterMatch = String(matchesText && matchesStatus && matchesCategory);
    });
    resetAdminTablePagination();
    refreshAdminTablePagination();
}

if (tableSearch) tableSearch.addEventListener('input', filterRows);
if (statusFilter) statusFilter.addEventListener('change', filterRows);

const adminTablePages = new Map();

function resetAdminTablePagination() {
    adminTablePages.forEach((state) => { state.page = 1; });
}

function refreshAdminTablePagination() {
    adminTablePages.forEach((state) => {
        const matches = state.rows.filter((row) => row.dataset.localFilterMatch !== 'false' && !row.classList.contains('global-search-hidden'));
        const pageCount = Math.max(1, Math.ceil(matches.length / state.pageSize));
        state.page = Math.min(state.page, pageCount);
        const first = (state.page - 1) * state.pageSize;
        const visibleRows = new Set(matches.slice(first, first + state.pageSize));

        state.rows.forEach((row) => { row.hidden = !visibleRows.has(row); });
        state.emptyRows.forEach((row) => {
            row.hidden = matches.length > 0 || row.classList.contains('global-search-hidden');
        });

        state.summary.textContent = matches.length
            ? `Showing ${first + 1}–${Math.min(first + state.pageSize, matches.length)} of ${matches.length}`
            : 'Showing 0 of 0';
        state.previous.disabled = state.page <= 1;
        state.next.disabled = state.page >= pageCount;
        state.controls.hidden = matches.length <= state.pageSize;
    });
}

document.querySelectorAll('.content-area table.data-table').forEach((table) => {
    const body = table.tBodies[0];
    if (!body) return;
    const children = [...body.rows];
    const emptyRows = children.filter((row) => row.hasAttribute('data-pagination-empty') || (row.cells.length === 1 && row.cells[0].colSpan > 1));
    const rows = children.filter((row) => !emptyRows.includes(row));
    rows.forEach((row) => { row.dataset.localFilterMatch ??= 'true'; });

    const controls = document.createElement('nav');
    controls.className = 'admin-table-pagination';
    controls.setAttribute('aria-label', 'Table pages');
    controls.innerHTML = '<button type="button" data-table-page-previous>Previous</button><span data-table-page-summary></span><button type="button" data-table-page-next>Next</button>';
    (table.closest('.table-scroll') || table).insertAdjacentElement('afterend', controls);

    const state = {
        rows,
        emptyRows,
        controls,
        summary: controls.querySelector('[data-table-page-summary]'),
        previous: controls.querySelector('[data-table-page-previous]'),
        next: controls.querySelector('[data-table-page-next]'),
        pageSize: 10,
        page: 1
    };
    adminTablePages.set(table, state);
    state.previous.addEventListener('click', () => {
        state.page -= 1;
        refreshAdminTablePagination();
    });
    state.next.addEventListener('click', () => {
        state.page += 1;
        refreshAdminTablePagination();
    });
});
refreshAdminTablePagination();

function setRecordsCategoryFilter(card) {
    activeRecordsCategory = card.dataset.categoryFilter || 'All';
    const selectedTitle = activeRecordsCategory === 'All' ? 'All Records' : `${activeRecordsCategory} Records`;

    categoryFilterCards.forEach((filterCard) => {
        const isActive = filterCard === card;
        filterCard.classList.toggle('active', isActive);
        filterCard.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    if (recordsPanelTitle) {
        recordsPanelTitle.textContent = selectedTitle;
    }

    if (recordsPanelSubtitle) {
        recordsPanelSubtitle.textContent = activeRecordsCategory === 'All'
            ? 'Showing records from every category.'
            : `Showing records filed under ${activeRecordsCategory}.`;
    }

    filterRows();
    document.getElementById('recordsTable')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

categoryFilterCards.forEach((card) => {
    card.addEventListener('click', () => setRecordsCategoryFilter(card));
    card.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        setRecordsCategoryFilter(card);
    });
});

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

document.querySelector('[data-select-all-records]')?.addEventListener('change', (event) => {
    document.querySelectorAll('input[name="ids[]"]').forEach((checkbox) => {
        checkbox.checked = event.target.checked;
    });
});

document.querySelectorAll('[data-export-table]').forEach((button) => {
    button.addEventListener('click', () => {
        const table = document.getElementById(button.dataset.exportTable);
        if (!table) return;
        const rows = [...table.querySelectorAll('tr')].map((row) => [...row.children].map((cell) => {
            const text = cell.innerText.replace(/\s+/g, ' ').trim();
            return `"${text.replaceAll('"', '""')}"`;
        }).join(',')).join('\n');
        const blob = new Blob([rows], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'irecord-export.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    });
});

document.querySelectorAll('.user-status-form').forEach((form) => {
    const select = form.querySelector('select[name="status"]');
    if (!select) return;

    select.addEventListener('change', () => {
        if (select.value !== select.dataset.originalStatus) {
            form.submit();
        }
    });
});

const activityCalendarEl = document.getElementById('activityCalendar');
const activityModal = document.getElementById('activityEditorModal');
const activityForm = document.getElementById('activityEditorForm');

function activityById(id) {
    return (window.activityEditorEvents || window.activityCalendarEvents || []).find((event) => String(event.id) === String(id));
}

function openActivityEditor(eventData) {
    if (!activityModal || !activityForm || !eventData) return;

    const props = eventData.extendedProps || {};
    const approvalStatus = props.approvalStatus || 'Approved';
    document.getElementById('activityModalMode').textContent = props.type || 'Activity';
    document.getElementById('activityModalTitle').textContent = 'Edit Activity';
    document.getElementById('activityAction').value = 'update';
    document.getElementById('activityId').value = eventData.id || '';
    document.getElementById('activityTitle').value = eventData.title || '';
    document.getElementById('activityType').value = props.type || 'Seminar';
    const dateFrom = (eventData.startStr || props.date || '').slice(0, 10);
    const exclusiveEnd = (eventData.endStr || '').slice(0, 10);
    const inclusiveEnd = exclusiveEnd ? new Date(`${exclusiveEnd}T00:00:00`) : null;
    if (inclusiveEnd) inclusiveEnd.setDate(inclusiveEnd.getDate() - 1);
    const dateTo = props.dateTo || (inclusiveEnd ? `${inclusiveEnd.getFullYear()}-${String(inclusiveEnd.getMonth() + 1).padStart(2, '0')}-${String(inclusiveEnd.getDate()).padStart(2, '0')}` : dateFrom);
    document.getElementById('activityDateFrom').value = dateFrom;
    document.getElementById('activityDateTo').value = dateTo;
    document.getElementById('activityDateTo').min = dateFrom;
    document.getElementById('activityVenue').value = props.venue || '';
    document.getElementById('activityDescription').value = props.description || '';
    document.getElementById('archiveActivityButton').style.display = '';
    document.getElementById('createActivityButton').style.display = 'none';
    document.getElementById('saveActivityButton').style.display = '';
    const approveButton = document.getElementById('approveActivityButton');
    const rejectButton = document.getElementById('rejectActivityButton');
    if (approveButton) {
        approveButton.style.display = '';
        approveButton.disabled = approvalStatus === 'Approved';
        approveButton.innerHTML = approvalStatus === 'Approved'
            ? '<i class="ri-check-line"></i> Approved'
            : '<i class="ri-check-line"></i> Approve';
    }
    if (rejectButton) rejectButton.style.display = approvalStatus === 'Rejected' ? 'none' : '';

    activityModal.classList.add('open');
    activityModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function openActivityCreator(dateStr) {
    if (!activityModal || !activityForm) return;

    document.getElementById('activityModalMode').textContent = 'New Activity';
    document.getElementById('activityModalTitle').textContent = 'Add Activity';
    document.getElementById('activityAction').value = 'create';
    document.getElementById('activityId').value = '';
    document.getElementById('activityTitle').value = '';
    document.getElementById('activityType').value = 'Seminar';
    const date = (dateStr || new Date().toISOString().slice(0, 10)).slice(0, 10);
    document.getElementById('activityDateFrom').value = date;
    document.getElementById('activityDateTo').value = date;
    document.getElementById('activityDateTo').min = date;
    document.getElementById('activityVenue').value = '';
    document.getElementById('activityDescription').value = '';
    document.getElementById('archiveActivityButton').style.display = 'none';
    document.getElementById('createActivityButton').style.display = '';
    document.getElementById('saveActivityButton').style.display = 'none';
    const approveButton = document.getElementById('approveActivityButton');
    const rejectButton = document.getElementById('rejectActivityButton');
    if (approveButton) approveButton.style.display = 'none';
    if (rejectButton) rejectButton.style.display = 'none';

    activityModal.classList.add('open');
    activityModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

document.querySelectorAll('[data-create-activity]').forEach((button) => {
    button.addEventListener('click', () => openActivityCreator());
});

const activityDateFrom = document.getElementById('activityDateFrom');
const activityDateTo = document.getElementById('activityDateTo');
activityDateFrom?.addEventListener('change', () => {
    activityDateTo.min = activityDateFrom.value;
    if (activityDateTo.value && activityDateTo.value < activityDateFrom.value) {
        activityDateTo.value = activityDateFrom.value;
    }
});

if (activityCalendarEl && window.FullCalendar) {
    const calendar = new FullCalendar.Calendar(activityCalendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        editable: true,
        selectable: true,
        dayMaxEvents: true,
        nowIndicator: true,
        events: window.activityCalendarEvents || [],
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        buttonText: {
            today: 'today',
            month: 'month',
            week: 'week',
            day: 'day',
            list: 'list'
        },
        dateClick(info) {
            openActivityCreator(info.dateStr);
        },
        eventClick(info) {
            openActivityEditor(info.event);
        },
        eventDrop(info) {
            const formData = new FormData();
            formData.append('csrf_token', window.activityCalendarCsrf || '');
            formData.append('action', 'move');
            formData.append('id', info.event.id);
            formData.append('date', info.event.startStr.slice(0, 10));

            fetch('activities.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then((response) => {
                if (!response.ok) throw new Error('Move failed');
                return response.json();
            }).then((data) => {
                if (!data.ok) throw new Error(data.message || 'Move failed');
            }).catch(() => {
                info.revert();
                alert('The activity could not be moved. Please try again.');
            });
        }
    });
    calendar.render();

    document.querySelectorAll('[data-edit-activity]').forEach((button) => {
        button.addEventListener('click', () => {
            openActivityEditor(activityById(button.dataset.editActivity));
        });
    });
}
