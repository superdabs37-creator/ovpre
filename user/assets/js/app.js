const sidebar = document.querySelector('#userSidebar');
document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => sidebar?.classList.toggle('open'));

const search = document.querySelector('[data-search]');
const statusFilter = document.querySelector('[data-status]');
function filterFiles() {
    const query = search ? search.value.trim().toLowerCase() : '';
    const status = statusFilter ? statusFilter.value : 'All';
    document.querySelectorAll('[data-file]').forEach((card) => {
        const visible = card.textContent.toLowerCase().includes(query) && (status === 'All' || card.dataset.status === status);
        card.style.display = visible ? '' : 'none';
    });
}
search?.addEventListener('input', filterFiles);
statusFilter?.addEventListener('change', filterFiles);

const fileInput = document.querySelector('#documentFile');
const dropzone = document.querySelector('#dropzone');
const progress = document.querySelector('#uploadProgress');
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
        if (progress) progress.style.width = '40%';
    });
}
document.querySelector('#uploadForm')?.addEventListener('submit', () => {
    if (progress) progress.style.width = '100%';
});

function closeModal() {
    document.querySelectorAll('.modal.open').forEach((modal) => modal.classList.remove('open'));
}
document.querySelectorAll('[data-close-modal]').forEach((item) => item.addEventListener('click', closeModal));
document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeModal(); });

document.querySelectorAll('[data-preview-title]').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelector('#previewTitle').textContent = button.dataset.previewTitle || '';
        document.querySelector('#previewBody').textContent = button.dataset.previewBody || '';
        document.querySelector('#previewModal').classList.add('open');
    });
});

document.querySelectorAll('.read-more').forEach((button) => {
    button.addEventListener('click', () => {
        document.querySelector('#modalTitle').textContent = button.dataset.title || '';
        document.querySelector('#modalContent').textContent = button.dataset.content || '';
        document.querySelector('#readModal').classList.add('open');
    });
});

document.querySelector('[data-announcement-filter]')?.addEventListener('change', (event) => {
    const value = event.target.value;
    document.querySelectorAll('[data-announcement]').forEach((card) => {
        card.style.display = value === 'All' || card.dataset.category === value ? '' : 'none';
    });
});
