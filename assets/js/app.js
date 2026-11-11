const appShell = document.querySelector('.app-shell') || document.querySelector('#appShell');
const sidebar = document.querySelector('#sidebar');
const sidebarToggle = document.querySelector('[data-toggle-sidebar]');
const profileToggle = document.querySelector('[data-profile-toggle]');
const profileDropdown = document.querySelector('[data-profile-dropdown]');

if (sidebarToggle && sidebar) {
    const backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'sidebar-backdrop';
    backdrop.setAttribute('aria-label', 'Close navigation menu');
    sidebar.insertAdjacentElement('afterend', backdrop);

    const setSidebarOpen = (isOpen) => {
        sidebar.classList.toggle('open', isOpen);
        backdrop.classList.toggle('open', isOpen);
        sidebarToggle.setAttribute('aria-expanded', String(isOpen));
    };

    sidebarToggle.addEventListener('click', () => {
        if (window.innerWidth > 900) {
            const isExpanded = appShell ? appShell.classList.toggle('sidebar-expanded') : sidebar.classList.toggle('expanded');
            sidebarToggle.setAttribute('aria-expanded', String(isExpanded));
            try {
                localStorage.setItem('ovpre_admin_sidebar_expanded', isExpanded ? '1' : '0');
            } catch (e) {}
        } else {
            setSidebarOpen(!sidebar.classList.contains('open'));
        }
    });

    backdrop.addEventListener('click', () => setSidebarOpen(false));
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
        if (window.innerWidth <= 900) setSidebarOpen(false);
    }));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (window.innerWidth <= 900) setSidebarOpen(false);
        }
    });

    // Restore desktop sidebar expanded state from localStorage
    try {
        const stored = localStorage.getItem('ovpre_admin_sidebar_expanded');
        if (window.innerWidth > 900) {
            if (stored === '0') {
                if (appShell) appShell.classList.remove('sidebar-expanded');
                sidebar.classList.remove('expanded');
                sidebarToggle.setAttribute('aria-expanded', 'false');
            } else {
                if (appShell) appShell.classList.add('sidebar-expanded');
                sidebar.classList.add('expanded');
                sidebarToggle.setAttribute('aria-expanded', 'true');
            }
        }
    } catch (e) {}
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
        const query = topbarSearch.value.trim();
        if (query) window.location.href = `search.html?q=${encodeURIComponent(query)}`;
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

function renderMonthlySubmissionsChart() {
    const canvas = document.getElementById('reportMonthlyChart');
    if (!canvas || !window.Chart || !window.reportCharts) return;

    const monthlyOverall = window.reportCharts.monthly || [];
    const monthlyByCollege = window.reportCharts.monthlyByCollege || [];
    const collegesList = window.reportCharts.collegesList || [];
    const filterSelect = document.getElementById('monthlyCollegeFilter');
    const subtitle = document.getElementById('monthlySubmissionsSubtitle');

    if (!monthlyByCollege.length) {
        renderReportChart('reportMonthlyChart', 'bar', monthlyOverall, 'Submissions');
        return;
    }

    // Determine unique chronological month labels
    const rawMonths = [];
    monthlyByCollege.forEach((row) => {
        if (row.month_label && !rawMonths.includes(row.month_label)) {
            rawMonths.push(row.month_label);
        }
    });
    monthlyOverall.forEach((row) => {
        if (row.label && !rawMonths.includes(row.label)) {
            rawMonths.push(row.label);
        }
    });
    const months = rawMonths.length ? rawMonths : ['No Data'];

    // Distinct palette for DLSP colleges
    const collegeColorMap = {
        'College of Computer Studies and Technology': '#15803d',
        'College of Engineering': '#2563eb',
        'College of Nursing and Allied Health Sciences': '#0d9488',
        'College of Arts and Sciences': '#7c3aed',
        'College of Business Administration': '#d97706',
        'College of Human Kinetics': '#e11d48',
        'College of Teacher Education': '#ea580c',
        'College of Tourism and Hospitality Management': '#0284c7',
        'College of Accountancy': '#4f46e5',
        'Other / Unassigned': '#64748b'
    };
    const distinctColors = ['#15803d', '#2563eb', '#0d9488', '#7c3aed', '#d97706', '#e11d48', '#ea580c', '#0284c7', '#4f46e5', '#64748b'];

    // Identify active colleges with submissions + ensure all known colleges are selectable
    const activeColleges = [...new Set(monthlyByCollege.map((r) => r.department_name).filter(Boolean))];
    const knownColleges = collegesList.map((c) => c.department_name).filter(Boolean);
    const displayColleges = activeColleges.length ? activeColleges : (knownColleges.length ? knownColleges : ['Colleges']);

    function getDatasets(mode) {
        if (mode === 'total') {
            const data = months.map((month) => {
                const item = monthlyOverall.find((r) => r.label === month);
                if (item) return Number(item.total);
                return monthlyByCollege
                    .filter((r) => r.month_label === month)
                    .reduce((sum, r) => sum + Number(r.total), 0);
            });
            return [{
                label: 'Overall Submissions',
                data,
                backgroundColor: '#15803d',
                borderColor: '#15803d',
                borderRadius: 6,
                borderSkipped: false
            }];
        }

        if (mode !== 'all') {
            const college = mode;
            const color = collegeColorMap[college] || '#15803d';
            const data = months.map((month) => {
                const found = monthlyByCollege.find((r) => r.month_label === month && r.department_name === college);
                return found ? Number(found.total) : 0;
            });
            return [{
                label: college,
                data,
                backgroundColor: color,
                borderColor: color,
                borderRadius: 6,
                borderSkipped: false
            }];
        }

        return displayColleges.map((college, idx) => {
            const color = collegeColorMap[college] || distinctColors[idx % distinctColors.length];
            const data = months.map((month) => {
                const found = monthlyByCollege.find((r) => r.month_label === month && r.department_name === college);
                return found ? Number(found.total) : 0;
            });
            return {
                label: college,
                data,
                backgroundColor: color,
                borderColor: color,
                borderRadius: 4,
                borderSkipped: false,
                stack: 'monthly-college-stack'
            };
        });
    }

    const initialMode = filterSelect ? filterSelect.value : 'all';
    const isStacked = initialMode === 'all';

    if (window._monthlySubmissionsChartInstance) {
        try { window._monthlySubmissionsChartInstance.destroy(); } catch (e) {}
    }

    const chartInstance = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: months,
            datasets: getDatasets(initialMode)
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: isStacked ? 'index' : 'nearest',
                intersect: false
            },
            plugins: {
                legend: {
                    display: isStacked,
                    position: 'bottom',
                    labels: {
                        boxWidth: 8,
                        boxHeight: 8,
                        usePointStyle: true,
                        pointStyle: 'rectRounded',
                        padding: 8,
                        font: { size: 10, weight: 600 },
                        color: '#475569'
                    }
                },
                tooltip: {
                    mode: isStacked ? 'index' : 'nearest',
                    intersect: false,
                    backgroundColor: '#0f3d24',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        footer: (items) => {
                            if (!isStacked) return '';
                            let sum = 0;
                            items.forEach((item) => { sum += item.parsed.y; });
                            return 'Month Total: ' + sum + ' submission' + (sum === 1 ? '' : 's');
                        }
                    }
                }
            },
            scales: {
                x: {
                    stacked: isStacked,
                    grid: { display: false },
                    ticks: { color: '#6f7b73', font: { weight: 600 } }
                },
                y: {
                    stacked: isStacked,
                    beginAtZero: true,
                    grid: { color: '#e6ede8' },
                    ticks: { color: '#6f7b73', precision: 0, font: { weight: 600 } }
                }
            }
        }
    });

    window._monthlySubmissionsChartInstance = chartInstance;

    if (filterSelect) {
        filterSelect.addEventListener('change', () => {
            const mode = filterSelect.value;
            const stacked = mode === 'all';

            if (subtitle) {
                if (mode === 'all') {
                    subtitle.textContent = 'Breakdown per college';
                } else if (mode === 'total') {
                    subtitle.textContent = 'Overall combined total';
                } else {
                    subtitle.textContent = 'Filtered to ' + mode;
                }
            }

            chartInstance.data.datasets = getDatasets(mode);
            chartInstance.options.scales.x.stacked = stacked;
            chartInstance.options.scales.y.stacked = stacked;
            chartInstance.options.interaction.mode = stacked ? 'index' : 'nearest';
            chartInstance.options.plugins.legend.display = stacked;
            chartInstance.options.plugins.tooltip.mode = stacked ? 'index' : 'nearest';
            chartInstance.update();
        });
    }
}

if (window.reportCharts) {
    renderReportChart('reportCategoryChart', 'bar', window.reportCharts.categories || [], 'Documents');
    renderMonthlySubmissionsChart();
    renderReportChart('reportResearchChart', 'doughnut', window.reportCharts.research || [], 'Research');
    renderReportChart('reportExtensionChart', 'line', window.reportCharts.extension || [], 'Extension');
    renderReportChart('reportDepartmentChart', 'bar', window.reportCharts.departments || [], 'Colleges Output');
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
    if (!document.querySelector('.modal.open')) {
        document.body.style.overflow = '';
    }
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
            window.location.href = profileModal.dataset.returnUrl || 'dashboard.html';
            return;
        }
        document.querySelectorAll('.modal.open').forEach(closeModal);
    }
});

document.querySelectorAll('[data-open-modal]').forEach((button) => {
    button.addEventListener('click', () => {
        if (button.hasAttribute('data-dismiss-modal')) {
            const currentModal = button.closest('.modal');
            if (currentModal) closeModal(currentModal);
        }
        const modal = document.getElementById(button.dataset.openModal);
        if (!modal) return;
        modal.querySelectorAll('[data-src]').forEach((el) => {
            if (el.dataset.src && el.getAttribute('src') !== el.dataset.src) {
                el.setAttribute('src', el.dataset.src);
            }
        });
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    });
});

document.querySelectorAll('[data-select-all]').forEach((selectAll) => {
    const tableId = selectAll.dataset.selectAll;
    const table = document.getElementById(tableId);
    const toolbar = document.querySelector(`[data-selection-toolbar="${tableId}"]`);
    if (!table || !toolbar) return;
    const rowCheckboxes = () => Array.from(table.querySelectorAll('[data-row-select]'));
    const countLabel = toolbar.querySelector('[data-selection-count]');
    const openSelected = toolbar.querySelector('[data-open-selected-research]');
    const bulkButtons = toolbar.querySelectorAll('[data-bulk-submit]');
    const updateSelection = () => {
        const checkboxes = rowCheckboxes();
        const selected = checkboxes.filter((checkbox) => checkbox.checked);
        selectAll.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
        selectAll.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
        toolbar.hidden = selected.length === 0;
        if (countLabel) countLabel.textContent = `${selected.length} selected`;
        if (openSelected) openSelected.disabled = selected.length === 0;
        bulkButtons.forEach((button) => { button.disabled = selected.length === 0; });
    };
    selectAll.addEventListener('change', () => {
        rowCheckboxes().forEach((checkbox) => { checkbox.checked = selectAll.checked; });
        updateSelection();
    });
    table.addEventListener('change', (event) => {
        if (event.target.matches('[data-row-select]')) updateSelection();
    });
    if (openSelected) {
        openSelected.addEventListener('click', () => {
            const ids = rowCheckboxes().filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
            if (ids.length) window.location.href = `research.html?ids=${encodeURIComponent(ids.join(','))}`;
        });
    }
    toolbar.querySelectorAll('[data-confirm-delete]').forEach((button) => {
        button.addEventListener('click', (event) => {
            if (!window.confirm('Permanently delete all selected items? This cannot be undone.')) event.preventDefault();
        });
    });
    updateSelection();
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
    adminTablePages.forEach((state, tbl) => {
        if (tbl.id === 'recordsTable' || !tableSearch) {
            state.query = query;
            if (state.searchInput && state.searchInput !== document.activeElement) {
                state.searchInput.value = tableSearch ? tableSearch.value : '';
            }
        }
    });
    resetAdminTablePagination();
    refreshAdminTablePagination();
}

if (tableSearch) tableSearch.addEventListener('input', filterRows);
if (statusFilter) statusFilter.addEventListener('change', filterRows);

const adminTablePages = new Map();

function populateAdminColumnFilters(state, table) {
    if (!state || !table || !state.toolbar) return;
    const toolbar = state.toolbar;
    const exportButton = state.exportButton;
    // Remove existing filter dropdowns
    toolbar.querySelectorAll('.data-table-filter').forEach((el) => el.remove());
    state.filterSelects = {};

    table.querySelectorAll('thead th').forEach((header, columnIndex) => {
        if (!/status|category|department|college|role|stage/i.test(header.textContent)) return;
        let presetOptions = null;
        try { presetOptions = JSON.parse(table.dataset.filterOptions || '{}')[header.textContent.trim()] || null; } catch (e) { presetOptions = null; }
        const values = presetOptions
            ? [...new Set(presetOptions.map((value) => String(value).trim()).filter(Boolean))]
            : [...new Set(state.rows.map((row) => (row.cells[columnIndex]?.textContent || '').trim()).filter(Boolean))];
        if (!presetOptions && (values.length < 1 || values.length > 40)) return;
        const label = document.createElement('label');
        label.className = 'data-table-filter';
        label.append(document.createTextNode(`${header.textContent.trim()}: `));
        const select = document.createElement('select');
        select.setAttribute('aria-label', `Filter by ${header.textContent.trim()}`);
        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = 'All';
        select.append(allOption);
        values.sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' })).forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            select.append(option);
        });
        if (state.columnFilters[columnIndex]) {
            select.value = state.columnFilters[columnIndex];
        }
        select.addEventListener('change', () => {
            state.columnFilters[columnIndex] = select.value;
            state.page = 1;
            refreshAdminTablePagination();
        });
        label.append(select);
        state.filterSelects[columnIndex] = select;
        if (exportButton && toolbar.contains(exportButton)) {
            toolbar.insertBefore(label, exportButton);
        } else {
            toolbar.append(label);
        }
    });
}

window.reloadAdminTablePagination = function(targetTable) {
    const table = targetTable || document.querySelector('.content-area table.data-table');
    if (!table) return;
    let state = adminTablePages.get(table);
    if (!state) {
        if (typeof window.initAdminTablePagination === 'function') {
            window.initAdminTablePagination(table);
            state = adminTablePages.get(table);
        }
    }
    if (!state) return;
    const body = table.tBodies[0];
    if (!body) return;
    const children = [...body.rows];
    const emptyRows = children.filter((row) => row.hasAttribute('data-pagination-empty') || (row.cells.length === 1 && row.cells[0].colSpan > 1));
    state.rows = children.filter((row) => !emptyRows.includes(row));
    state.emptyRows = emptyRows;
    state.rows.forEach((row) => { row.dataset.localFilterMatch ??= 'true'; });
    populateAdminColumnFilters(state, table);
    state.page = 1;
    refreshAdminTablePagination();
};

function resetAdminTablePagination() {
    adminTablePages.forEach((state) => { state.page = 1; });
}

function refreshAdminTablePagination() {
    adminTablePages.forEach((state) => {
        const query = state.query.trim().toLowerCase();
        const matches = state.rows.filter((row) => row.dataset.localFilterMatch !== 'false'
            && !row.classList.contains('global-search-hidden')
            && Object.entries(state.columnFilters).every(([column, value]) => !value || (row.cells[Number(column)]?.textContent || '').trim() === value)
            && (!query || row.textContent.toLowerCase().includes(query)));
        const pageCount = Math.max(1, Math.ceil(matches.length / state.pageSize));
        state.page = Math.min(state.page, pageCount);
        const first = (state.page - 1) * state.pageSize;
        const visibleRows = new Set(matches.slice(first, first + state.pageSize));

        state.rows.forEach((row) => { row.hidden = !visibleRows.has(row); });
        state.emptyRows.forEach((row) => {
            row.hidden = matches.length > 0 || row.classList.contains('global-search-hidden');
            const cell = row.cells[0];
            if (cell) {
                cell.textContent = query ? 'No matching entries found.' : (row.dataset.emptyMessage || cell.textContent);
            }
        });

        state.summary.textContent = matches.length
            ? `Showing ${first + 1}–${Math.min(first + state.pageSize, matches.length)} of ${matches.length} entries`
            : 'Showing 0 of 0 entries';
        state.previous.disabled = state.page <= 1;
        state.next.disabled = state.page >= pageCount;

        const pageButtons = state.pageButtons;
        pageButtons.replaceChildren();
        const startPage = Math.max(1, Math.min(state.page - 2, pageCount - 4));
        const endPage = Math.min(pageCount, Math.max(5, state.page + 2));
        for (let page = startPage; page <= endPage; page += 1) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = String(page);
            button.setAttribute('aria-label', `Page ${page}`);
            button.setAttribute('aria-current', page === state.page ? 'page' : 'false');
            if (page === state.page) button.classList.add('is-current');
            button.addEventListener('click', () => {
                state.page = page;
                refreshAdminTablePagination();
            });
            pageButtons.append(button);
        }
    });
}

window.initAdminTablePagination = function(table) {
    if (!table) return;
    const body = table.tBodies[0];
    if (!body) return;

    // If already initialized and elements exist in DOM, just reload
    const existingState = adminTablePages.get(table);
    const wrapper = table.closest('.table-scroll') || table;
    const prevToolbar = wrapper.previousElementSibling;
    if (existingState && prevToolbar && prevToolbar.classList.contains('data-table-toolbar')) {
        window.reloadAdminTablePagination(table);
        return;
    }

    // Clean up any stale toolbar or pagination next to wrapper
    if (wrapper.previousElementSibling && wrapper.previousElementSibling.classList.contains('data-table-toolbar')) {
        wrapper.previousElementSibling.remove();
    }
    if (wrapper.nextElementSibling && wrapper.nextElementSibling.classList.contains('admin-table-pagination')) {
        wrapper.nextElementSibling.remove();
    }

    const children = [...body.rows];
    const emptyRows = children.filter((row) => row.hasAttribute('data-pagination-empty') || (row.cells.length === 1 && row.cells[0].colSpan > 1));
    const rows = children.filter((row) => !emptyRows.includes(row));
    rows.forEach((row) => { row.dataset.localFilterMatch ??= 'true'; });
    emptyRows.forEach((row) => { row.dataset.emptyMessage = row.cells[0]?.textContent || ''; });

    const toolbar = document.createElement('div');
    toolbar.className = 'data-table-toolbar';
    const columnFilters = {};
    const existingDedicatedSearch = table.id ? document.querySelector(`[data-table-search="${table.id}"]`) : null;
    let tableSearchInput = null;
    if (!existingDedicatedSearch) {
        toolbar.classList.add('has-table-search');
        const searchLabel = document.createElement('label');
        searchLabel.className = 'data-table-search';
        searchLabel.innerHTML = '<i class="ri-search-line" aria-hidden="true"></i><span class="sr-only">Search this table</span>';
        tableSearchInput = document.createElement('input');
        tableSearchInput.type = 'search';
        const placeholderText = table.id === 'recordsTable' ? 'Search records...' : (table.id === 'usersTable' ? 'Search users...' : (table.id === 'researchSubmissionTable' ? 'Search research...' : (table.id === 'extensionSubmissionTable' ? 'Search extension...' : (table.id === 'activitySubmissionTable' ? 'Search activities...' : (table.id === 'systemActivityTable' ? 'Search system activity...' : 'Search this table')))));
        tableSearchInput.placeholder = placeholderText;
        tableSearchInput.setAttribute('aria-label', placeholderText);
        if (tableSearch && table.id === 'recordsTable' && tableSearch.value) {
            tableSearchInput.value = tableSearch.value;
        }
        searchLabel.append(tableSearchInput);
        toolbar.append(searchLabel);
        tableSearchInput.addEventListener('input', () => {
            if (table.id === 'recordsTable' && tableSearch) {
                tableSearch.value = tableSearchInput.value;
                filterRows();
            } else {
                state.query = tableSearchInput.value;
                state.page = 1;
                refreshAdminTablePagination();
            }
        });
        tableSearchInput.addEventListener('search', () => {
            if (tableSearchInput.value === '') {
                tableSearchInput.dispatchEvent(new Event('input'));
            }
        });
    } else {
        existingDedicatedSearch.addEventListener('input', () => {
            state.query = existingDedicatedSearch.value;
            state.page = 1;
            refreshAdminTablePagination();
        });
        existingDedicatedSearch.addEventListener('search', () => {
            if (existingDedicatedSearch.value === '') {
                existingDedicatedSearch.dispatchEvent(new Event('input'));
            }
        });
    }

    const lengthLabel = document.createElement('label');
    lengthLabel.className = 'data-table-length';
    lengthLabel.innerHTML = '<span>Show</span>';
    const lengthSelect = document.createElement('select');
    lengthSelect.setAttribute('aria-label', 'Rows per page');
    [['10', '10'], ['25', '25'], ['50', '50'], ['100', '100'], ['all', 'All']].forEach(([value, label]) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        lengthSelect.append(option);
    });
    lengthLabel.append(lengthSelect, document.createTextNode(' entries'));
    toolbar.append(lengthLabel);
    const exportButton = document.createElement('button');
    exportButton.type = 'button';
    exportButton.className = 'data-table-export';
    exportButton.innerHTML = '<i class="ri-download-2-line" aria-hidden="true"></i> Export CSV';
    exportButton.addEventListener('click', () => {
        const query = state.query.trim().toLowerCase();
        const currentRows = state ? state.rows : rows;
        const exportRows = currentRows.filter((row) => row.dataset.localFilterMatch !== 'false'
            && !row.classList.contains('global-search-hidden')
            && Object.entries(state.columnFilters).every(([column, value]) => !value || (row.cells[Number(column)]?.textContent || '').trim() === value)
            && (!query || row.textContent.toLowerCase().includes(query)));
        const csvCell = (value) => `"${String(value).replace(/"/g, '""')}"`;
        const headers = [...table.tHead.rows[table.tHead.rows.length - 1].cells]
            .map((cell) => cell.innerText.trim());
        const lines = [headers.map(csvCell).join(',')];
        exportRows.forEach((row) => {
            lines.push([...row.cells].map((cell) => csvCell(cell.innerText.trim())).join(','));
        });
        const blob = new Blob([`\uFEFF${lines.join('\r\n')}`], { type: 'text/csv;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${(document.querySelector('.topbar-title h1')?.textContent || 'table').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-')}.csv`;
        link.click();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
    toolbar.append(exportButton);
    wrapper.insertAdjacentElement('beforebegin', toolbar);

    const controls = document.createElement('nav');
    controls.className = 'admin-table-pagination';
    controls.setAttribute('aria-label', 'Table pages');
    controls.innerHTML = '<span class="admin-table-page-summary" data-table-page-summary></span><div class="admin-table-page-buttons"><button type="button" data-table-page-previous aria-label="Previous page">Previous</button><span data-table-page-buttons></span><button type="button" data-table-page-next aria-label="Next page">Next</button></div>';
    wrapper.insertAdjacentElement('afterend', controls);

    const state = {
        rows,
        emptyRows,
        controls,
        summary: controls.querySelector('[data-table-page-summary]'),
        previous: controls.querySelector('[data-table-page-previous]'),
        next: controls.querySelector('[data-table-page-next]'),
        pageButtons: controls.querySelector('[data-table-page-buttons]'),
        columnFilters,
        filterSelects: {},
        toolbar,
        exportButton,
        searchInput: tableSearchInput,
        query: (table.id === 'recordsTable' && tableSearch) ? tableSearch.value : (tableSearchInput ? tableSearchInput.value : (tableSearch ? tableSearch.value : '')),
        pageSize: 10,
        page: 1
    };
    adminTablePages.set(table, state);
    lengthSelect.addEventListener('change', () => {
        state.pageSize = lengthSelect.value === 'all' ? Math.max(rows.length, 1) : Number(lengthSelect.value);
        state.page = 1;
        refreshAdminTablePagination();
    });
    state.previous.addEventListener('click', () => {
        state.page -= 1;
        refreshAdminTablePagination();
    });
    state.next.addEventListener('click', () => {
        state.page += 1;
        refreshAdminTablePagination();
    });

    populateAdminColumnFilters(state, table);

    const extraFiltersSelector = table.dataset.extraFilters;
    const extraFilters = extraFiltersSelector ? document.querySelector(extraFiltersSelector) : null;
    if (extraFilters) {
        if (exportButton && toolbar.contains(exportButton)) {
            toolbar.insertBefore(extraFilters, exportButton);
        } else {
            toolbar.append(extraFilters);
        }
    }

    table.querySelectorAll('thead th').forEach((header, columnIndex) => {
        if (!header.textContent.trim() || /^(manage|review|actions?|select\s*all)$/i.test(header.textContent.trim()) || header.querySelector('[data-select-all]') || header.querySelector('input[type="checkbox"]') || header.classList.contains('table-checkbox-col')) return;
        header.classList.add('data-table-sortable');
        header.tabIndex = 0;
        header.setAttribute('aria-sort', 'none');

        const sortByHeader = () => {
            const direction = header.dataset.sortDirection === 'asc' ? 'desc' : 'asc';
            table.querySelectorAll('thead th[data-sort-direction]').forEach((otherHeader) => {
                otherHeader.removeAttribute('data-sort-direction');
                otherHeader.setAttribute('aria-sort', 'none');
            });
            header.dataset.sortDirection = direction;
            header.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');
            const sortTargetRows = state ? state.rows : rows;
            sortTargetRows.sort((rowA, rowB) => {
                const cellA = rowA.cells[columnIndex];
                const cellB = rowB.cells[columnIndex];
                const valueA = (cellA?.dataset.sortValue || cellA?.innerText || '').trim();
                const valueB = (cellB?.dataset.sortValue || cellB?.innerText || '').trim();
                const numericPattern = /^[-+]?[^\dA-Za-z]*[\d,]+(?:\.\d+)?%?$/;
                let comparison;
                if (numericPattern.test(valueA) && numericPattern.test(valueB)) {
                    const numberA = Number(valueA.replace(/[^\d.+-]/g, '')) || 0;
                    const numberB = Number(valueB.replace(/[^\d.+-]/g, '')) || 0;
                    comparison = numberA - numberB;
                } else {
                    const dateA = Date.parse(valueA);
                    const dateB = Date.parse(valueB);
                    comparison = Number.isNaN(dateA) || Number.isNaN(dateB)
                        ? valueA.localeCompare(valueB, undefined, { numeric: true, sensitivity: 'base' })
                        : dateA - dateB;
                }
                return direction === 'asc' ? comparison : -comparison;
            });
            const appendTargetRows = state ? state.rows : rows;
            appendTargetRows.forEach((row) => body.append(row));
            emptyRows.forEach((row) => body.append(row));
            state.page = 1;
            refreshAdminTablePagination();
        };

        header.addEventListener('click', sortByHeader);
        header.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                sortByHeader();
            }
        });
    });
};
document.querySelectorAll('.content-area table.data-table').forEach(window.initAdminTablePagination);
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
    document.getElementById('createActivityButton').style.display = 'none';
    document.getElementById('saveActivityButton').style.display = 'inline-flex';
    const approveButton = document.getElementById('approveActivityButton');
    const rejectButton = document.getElementById('rejectActivityButton');
    const archiveButton = document.getElementById('archiveActivityButton');
    const deleteButton = document.getElementById('deleteActivityButton');
    const statusBadge = document.getElementById('activityStatusBadge');

    if (statusBadge) {
        if (approvalStatus === 'Approved') {
            statusBadge.style.display = 'inline-flex';
            statusBadge.style.background = '#f0fdf4';
            statusBadge.style.color = '#166534';
            statusBadge.style.border = '1px solid #bbf7d0';
            statusBadge.innerHTML = '<i class="ri-checkbox-circle-line" style="margin-right:4px;"></i> Approved';
        } else if (approvalStatus === 'Rejected') {
            statusBadge.style.display = 'inline-flex';
            statusBadge.style.background = '#fef2f2';
            statusBadge.style.color = '#dc2626';
            statusBadge.style.border = '1px solid #fecaca';
            statusBadge.innerHTML = '<i class="ri-close-circle-line" style="margin-right:4px;"></i> Rejected';
        } else {
            statusBadge.style.display = 'inline-flex';
            statusBadge.style.background = '#fffbeb';
            statusBadge.style.color = '#b45309';
            statusBadge.style.border = '1px solid #fde68a';
            statusBadge.innerHTML = '<i class="ri-time-line" style="margin-right:4px;"></i> Pending Review';
        }
    }

    if (approveButton) {
        if (approvalStatus === 'Approved') {
            approveButton.style.display = 'none';
        } else {
            approveButton.style.display = 'inline-flex';
            approveButton.disabled = false;
            approveButton.innerHTML = '<i class="ri-check-line"></i> Approve';
        }
    }
    if (rejectButton) {
        rejectButton.style.display = (approvalStatus === 'Rejected') ? 'none' : 'inline-flex';
    }
    if (archiveButton) archiveButton.style.display = 'inline-flex';
    if (deleteButton) deleteButton.style.display = 'inline-flex';

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
    document.getElementById('createActivityButton').style.display = 'inline-flex';
    document.getElementById('saveActivityButton').style.display = 'none';
    const statusBadge = document.getElementById('activityStatusBadge');
    if (statusBadge) statusBadge.style.display = 'none';
    const approveButton = document.getElementById('approveActivityButton');
    const rejectButton = document.getElementById('rejectActivityButton');
    const archiveButton = document.getElementById('archiveActivityButton');
    const deleteButton = document.getElementById('deleteActivityButton');
    if (approveButton) approveButton.style.display = 'none';
    if (rejectButton) rejectButton.style.display = 'none';
    if (archiveButton) archiveButton.style.display = 'none';
    if (deleteButton) deleteButton.style.display = 'none';

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
            // Static build: no PHP endpoint. Persist the move through the page hook (localStorage) instead.
            const moveRequest = (typeof window.OVPRE_activityMove === 'function')
                ? Promise.resolve(window.OVPRE_activityMove(info.event.id, info.event.startStr.slice(0, 10)))
                : Promise.resolve({ ok: true });
            moveRequest.then((data) => {
                if (data && data.ok === false) throw new Error(data.message || 'Move failed');
            }).catch(() => {
                info.revert();
                alert('The activity could not be moved. Please try again.');
            });
        }
    });
    calendar.render();
    window.activityCalendarInstance = calendar;

    document.querySelectorAll('[data-edit-activity]').forEach((button) => {
        button.addEventListener('click', () => {
            const parentModal = button.closest('.modal');
            if (parentModal && parentModal !== activityModal) closeModal(parentModal);
            openActivityEditor(activityById(button.dataset.editActivity));
        });
    });
}


/* =========================================================
   SEARCHABLE SELECT ENGINE
   ========================================================= */
function initSearchableSelects() {
    document.querySelectorAll('select.searchable-select, select[data-searchable]').forEach((select) => {
        if (select.dataset.searchableInit === 'true') return;
        select.dataset.searchableInit = 'true';

        // Keep native select accessible for standard form POST submission
        select.style.position = 'absolute';
        select.style.opacity = '0';
        select.style.pointerEvents = 'none';
        select.style.width = '1px';
        select.style.height = '1px';
        select.tabIndex = -1;

        const placeholder = select.dataset.placeholder || 'Unassigned';

        // Build wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'custom-searchable-select';

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'custom-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');

        const label = document.createElement('span');
        label.className = 'custom-select-label';

        const arrow = document.createElement('i');
        arrow.className = 'ri-arrow-down-s-line custom-select-arrow';

        trigger.appendChild(label);
        trigger.appendChild(arrow);
        wrapper.appendChild(trigger);

        // Build dropdown
        const dropdown = document.createElement('div');
        dropdown.className = 'custom-select-dropdown';
        dropdown.setAttribute('role', 'listbox');

        // Search box
        const searchBox = document.createElement('div');
        searchBox.className = 'custom-select-search-box';

        const searchIcon = document.createElement('i');
        searchIcon.className = 'ri-search-line';

        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'custom-select-search-input';
        searchInput.placeholder = select.dataset.searchPlaceholder || 'Type to search reviewer...';
        searchInput.setAttribute('autocomplete', 'off');
        searchInput.setAttribute('spellcheck', 'false');

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'custom-select-clear-search';
        clearBtn.innerHTML = '<i class="ri-close-line"></i>';
        clearBtn.title = 'Clear search';

        searchBox.appendChild(searchIcon);
        searchBox.appendChild(searchInput);
        searchBox.appendChild(clearBtn);
        dropdown.appendChild(searchBox);

        // Options container
        const optionsList = document.createElement('div');
        optionsList.className = 'custom-select-options';

        const emptyMsg = document.createElement('div');
        emptyMsg.className = 'custom-select-empty';
        emptyMsg.innerHTML = '<i class="ri-search-eye-line"></i><span>No matching reviewer found</span>';

        dropdown.appendChild(optionsList);
        dropdown.appendChild(emptyMsg);
        wrapper.appendChild(dropdown);

        // Insert wrapper right after select
        select.parentNode.insertBefore(wrapper, select.nextSibling);

        function updateTriggerLabel() {
            const selectedOpt = select.options[select.selectedIndex];
            if (!selectedOpt || selectedOpt.value === '') {
                label.innerHTML = `<span style="color: #64748b;">${selectedOpt ? selectedOpt.textContent : placeholder}</span>`;
            } else {
                const text = selectedOpt.textContent.trim();
                const parts = text.split(' - ');
                if (parts.length > 1) {
                    const name = parts[0];
                    const role = parts.slice(1).join(' - ');
                    label.innerHTML = `<span>${name}</span> <span class="role-badge">${role}</span>`;
                } else {
                    label.textContent = text;
                }
            }
        }

        function renderOptions() {
            optionsList.innerHTML = '';
            Array.from(select.options).forEach((opt, idx) => {
                const optEl = document.createElement('div');
                optEl.className = 'custom-select-option';
                optEl.dataset.value = opt.value;
                optEl.dataset.index = idx;
                if (opt.selected) {
                    optEl.classList.add('is-selected');
                }

                const text = opt.textContent.trim();
                const parts = text.split(' - ');
                if (parts.length > 1 && opt.value !== '') {
                    const name = parts[0];
                    const role = parts.slice(1).join(' - ');
                    optEl.innerHTML = `<span class="opt-title">${name}</span> <span class="opt-badge">${role}</span> <i class="ri-check-line opt-check"></i>`;
                } else {
                    optEl.innerHTML = `<span class="opt-title">${text}</span> <i class="ri-check-line opt-check"></i>`;
                }

                optEl.addEventListener('click', (e) => {
                    e.stopPropagation();
                    select.selectedIndex = idx;
                    select.value = opt.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    updateTriggerLabel();
                    renderOptions();
                    closeDropdown();
                    trigger.focus();
                });

                optionsList.appendChild(optEl);
            });
        }

        function filterOptions(query) {
            const q = query.trim().toLowerCase();
            clearBtn.style.display = q ? 'block' : 'none';
            let visibleCount = 0;

            optionsList.querySelectorAll('.custom-select-option').forEach((optEl) => {
                const text = optEl.textContent.toLowerCase();
                const matches = text.includes(q);
                optEl.style.display = matches ? 'flex' : 'none';
                if (matches) visibleCount++;
            });

            emptyMsg.style.display = visibleCount === 0 ? 'flex' : 'none';
        }

        function openDropdown() {
            document.querySelectorAll('.custom-searchable-select.is-open').forEach((other) => {
                if (other !== wrapper) other.classList.remove('is-open');
            });

            wrapper.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            searchInput.value = '';
            filterOptions('');
            renderOptions();

            setTimeout(() => {
                searchInput.focus();
                const selectedEl = optionsList.querySelector('.custom-select-option.is-selected');
                if (selectedEl) {
                    selectedEl.scrollIntoView({ block: 'nearest' });
                }
            }, 60);
        }

        function closeDropdown() {
            wrapper.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            if (wrapper.classList.contains('is-open')) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });

        searchInput.addEventListener('input', () => {
            filterOptions(searchInput.value);
        });

        clearBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            searchInput.value = '';
            filterOptions('');
            searchInput.focus();
        });

        // Keyboard navigation
        searchInput.addEventListener('keydown', (e) => {
            const visibleOpts = Array.from(optionsList.querySelectorAll('.custom-select-option:not([style*="display: none"])'));
            const focusedOpt = optionsList.querySelector('.custom-select-option.is-focused');
            let currentIndex = visibleOpts.indexOf(focusedOpt);

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const nextIndex = currentIndex < visibleOpts.length - 1 ? currentIndex + 1 : 0;
                if (focusedOpt) focusedOpt.classList.remove('is-focused');
                if (visibleOpts[nextIndex]) {
                    visibleOpts[nextIndex].classList.add('is-focused');
                    visibleOpts[nextIndex].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prevIndex = currentIndex > 0 ? currentIndex - 1 : visibleOpts.length - 1;
                if (focusedOpt) focusedOpt.classList.remove('is-focused');
                if (visibleOpts[prevIndex]) {
                    visibleOpts[prevIndex].classList.add('is-focused');
                    visibleOpts[prevIndex].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (focusedOpt) {
                    focusedOpt.click();
                } else if (visibleOpts.length === 1) {
                    visibleOpts[0].click();
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closeDropdown();
                trigger.focus();
            }
        });

        // Initialize state
        updateTriggerLabel();
        renderOptions();
    });
}

// Global click to close dropdowns
document.addEventListener('click', (event) => {
    if (!event.target.closest('.custom-searchable-select')) {
        document.querySelectorAll('.custom-searchable-select.is-open').forEach((el) => {
            el.classList.remove('is-open');
            const trig = el.querySelector('.custom-select-trigger');
            if (trig) trig.setAttribute('aria-expanded', 'false');
        });
    }
});

// Run on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSearchableSelects);
} else {
    initSearchableSelects();
}

// Auto-open modal or highlight target row based on URL hash (e.g., #proposal-12 or #project-12)
function handleLocationHashTarget() {
    if (!window.location.hash) return;
    const hash = window.location.hash.substring(1);
    if (!hash) return;

    if (hash.startsWith('proposal-')) {
        const id = hash.replace('proposal-', '');
        const modal = document.getElementById('research-' + id);
        if (modal) {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
        const row = document.getElementById(hash);
        if (row) {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.style.transition = 'background-color 0.4s';
            row.style.backgroundColor = '#dcfce7';
            setTimeout(() => { row.style.backgroundColor = ''; }, 3000);
        }
    } else if (hash.startsWith('project-')) {
        const id = hash.replace('project-', '');
        const modal = document.getElementById('ext-detail-' + id) || document.getElementById('project-' + id);
        if (modal && modal.classList.contains('modal')) {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
        const row = document.getElementById(hash);
        if (row) {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.style.transition = 'background-color 0.4s';
            row.style.backgroundColor = '#e0f2fe';
            setTimeout(() => { row.style.backgroundColor = ''; }, 3000);
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', handleLocationHashTarget);
} else {
    handleLocationHashTarget();
}
window.addEventListener('hashchange', handleLocationHashTarget);
