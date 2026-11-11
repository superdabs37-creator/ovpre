/**
 * OVPRE I-Record - Static Bridge
 * ------------------------------------------------------------------
 * Small helper layer that sits next to the ORIGINAL assets/js/app.js so the
 * static HTML build behaves exactly like the PHP build:
 *   - app.js builds the data-table toolbar (search / show N entries / column
 *     filters / Export CSV), sorting, pagination, sidebar toggle, profile
 *     dropdown, [data-open-modal] / [data-close-modal], bulk selection, etc.
 *   - this file adds the bits that PHP did on the server (flash notices,
 *     status classes, number/date formatting, localStorage "database",
 *     role flags) and re-binds dynamic content that is rendered by JS.
 *
 * Load order on every page:  page script  ->  app.js  ->  static-bridge.js  ->  role-system.js
 */
(function () {
    'use strict';

    const OVPRE = (window.OVPRE = window.OVPRE || {});

    /* ---------------------------------------------------------------
       Formatting helpers (PHP equivalents)
       --------------------------------------------------------------- */
    OVPRE.h = function (value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    // statusClass(): strtolower(str_replace([' ', '/'], '-', $status))
    OVPRE.statusClass = function (status) {
        return String(status || '').toLowerCase().replace(/[ /]/g, '-');
    };

    // number_format($n, 2)
    OVPRE.money = function (value) {
        return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    // date('M d, Y', ...)
    OVPRE.date = function (value) {
        if (!value) return '';
        const d = new Date(String(value).length <= 10 ? value + 'T00:00:00' : value);
        if (Number.isNaN(d.getTime())) return String(value);
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    };

    /* ---------------------------------------------------------------
       localStorage "database"
       --------------------------------------------------------------- */
    OVPRE.store = {
        get(key, seed) {
            try {
                const raw = localStorage.getItem(key);
                if (raw) return JSON.parse(raw);
            } catch (e) { /* fall through to seed */ }
            const fresh = JSON.parse(JSON.stringify(seed === undefined ? [] : seed));
            try { localStorage.setItem(key, JSON.stringify(fresh)); } catch (e) { /* ignore */ }
            return fresh;
        },
        set(key, value) {
            try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* ignore */ }
            return value;
        },
        nextId(list) {
            return list.reduce((max, item) => Math.max(max, Number(item.id) || 0), 0) + 1;
        }
    };

    /* ---------------------------------------------------------------
       Current user + role flags (same normalisation as role-system.js)
       --------------------------------------------------------------- */
    function normalizeRole(role) {
        if (!role) return 'Super Admin';
        const lower = String(role).toLowerCase();
        if (lower.includes('research') && lower.includes('director')) return 'Research Director';
        if (lower.includes('extension') && lower.includes('director')) return 'Extension Director';
        if (lower.includes('research') && lower.includes('coord')) return 'Research Coordinator';
        if (lower.includes('extension') && lower.includes('coord')) return 'Extension Coordinator';
        if (lower.includes('faculty') && lower.includes('extension')) return 'Faculty Extensionist';
        if (lower.includes('extensionist')) return 'Faculty Extensionist';
        if (lower.includes('faculty') && lower.includes('research')) return 'Faculty Researcher';
        if (lower.includes('researcher')) return 'Faculty Researcher';
        if (lower.includes('ovpre') || lower.includes('admin office')) return 'OVPRE Admin';
        return 'Super Admin';
    }

    OVPRE.user = function () {
        let user = null;
        try {
            user = JSON.parse(localStorage.getItem('irecord_user') || sessionStorage.getItem('irecord_user') || 'null');
        } catch (e) { user = null; }
        user = user || { id: 1, fullname: 'Super Administrator', role: 'Super Admin', department: '' };
        user.role = normalizeRole(user.role);
        return user;
    };

    OVPRE.roleFlags = function () {
        const user = OVPRE.user();
        const role = user.role;
        const isSuperAdmin = role === 'Super Admin';
        const isOVPREAdmin = role === 'OVPRE Admin';
        return {
            user,
            role,
            isSuperAdmin,
            isOVPREAdmin,
            isAdmin: isSuperAdmin || isOVPREAdmin,
            isExecutive: isSuperAdmin || isOVPREAdmin,
            isResearchDirector: role === 'Research Director',
            isExtensionDirector: role === 'Extension Director',
            isDirector: role === 'Research Director' || role === 'Extension Director',
            isResearchCoordinator: role === 'Research Coordinator',
            isExtensionCoordinator: role === 'Extension Coordinator',
            isCoordinator: role === 'Research Coordinator' || role === 'Extension Coordinator',
            isFacultyResearcher: role === 'Faculty Researcher',
            isFacultyExtensionist: role === 'Faculty Extensionist',
            isFaculty: role === 'Faculty Researcher' || role === 'Faculty Extensionist'
        };
    };

    /* ---------------------------------------------------------------
       Modals ( .modal / .modal-card / [data-open-modal] / [data-close-modal] )
       --------------------------------------------------------------- */
    OVPRE.openModal = function (idOrEl) {
        const modal = typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl;
        if (!modal) return null;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        return modal;
    };

    OVPRE.closeModal = function (idOrEl) {
        const modal = typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl;
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.modal.open')) document.body.style.overflow = '';
    };

    // Delegated so buttons / modals that are rendered later by JS also work.
    document.addEventListener('click', function (event) {
        const opener = event.target.closest('[data-open-modal]');
        if (opener) {
            if (opener.hasAttribute('data-dismiss-modal')) {
                const current = opener.closest('.modal');
                if (current) OVPRE.closeModal(current);
            }
            const target = document.getElementById(opener.getAttribute('data-open-modal'));
            if (target) {
                target.querySelectorAll('[data-src]').forEach(function (el) {
                    if (el.dataset.src && el.getAttribute('src') !== el.dataset.src) el.setAttribute('src', el.dataset.src);
                });
                OVPRE.openModal(target);
            }
            return;
        }
        const closer = event.target.closest('[data-close-modal]');
        if (closer) {
            const modal = closer.closest('.modal');
            if (modal) OVPRE.closeModal(modal);
        }
    });

    /* ---------------------------------------------------------------
       Flash notice (PHP flash() -> <div class="notice success|error">)
       --------------------------------------------------------------- */
    OVPRE.toast = function (message, type) {
        type = type || 'success';
        const host = document.querySelector('.content-area') || document.body;
        let box = document.getElementById('ovpreFlash');
        if (!box) {
            box = document.createElement('div');
            box.id = 'ovpreFlash';
            host.insertBefore(box, host.firstChild);
        }
        const icon = type === 'success' ? 'ri-checkbox-circle-line' : 'ri-information-line';
        box.innerHTML = '<div class="notice ' + OVPRE.h(type) + '"><i class="' + icon + '"></i> ' + OVPRE.h(message) + '</div>';
        window.clearTimeout(OVPRE._toastTimer);
        OVPRE._toastTimer = window.setTimeout(function () { box.innerHTML = ''; }, 5000);
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    /* ---------------------------------------------------------------
       Data table helper - call after JS re-renders <tbody> rows
       --------------------------------------------------------------- */
    OVPRE.reloadTable = function (idOrEl) {
        const table = typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl;
        if (table && typeof window.reloadAdminTablePagination === 'function') {
            window.reloadAdminTablePagination(table);
        }
    };

    /* ---------------------------------------------------------------
       Download helper (CSV / JSON exports used by a few pages)
       --------------------------------------------------------------- */
    OVPRE.download = function (filename, content, mime) {
        const blob = new Blob([content], { type: mime || 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    };
})();
