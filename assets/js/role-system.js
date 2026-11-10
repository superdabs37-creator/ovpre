/**
 * OVPRE I-Record Role System & Multi-Tier Workflow Engine
 * Accurately replicates the original PHP role-portal flows for:
 *  - Super Admin / OVPRE Admin
 *  - Research Director
 *  - Extension Director
 *  - Research Coordinator
 *  - Extension Coordinator
 *  - Faculty Researcher
 *  - Faculty Extensionist
 */

(function () {
    'use strict';

    const ROLE_PORTALS = {
        'Super Admin': {
            title: 'Super Administrator',
            roleName: 'Super Admin',
            portalTitle: 'Executive Analytical Command Center',
            subtitle: 'Institutional executive oversight, policy governance, and final approvals.',
            division: 'Executive Administration',
            avatar: 'SA',
            nav: ['dashboard', 'records', 'research', 'extension', 'announcements', 'activities', 'settings'],
            primaryAction: { label: 'Review Proposals & Records', href: 'research.html', icon: 'ri-file-list-3-line' },
            canAccessSettings: true,
            canAccessRecords: true,
            canReviewResearch: true,
            canReviewExtension: true
        },
        'OVPRE Admin': {
            title: 'OVPRE Administrator',
            roleName: 'OVPRE Admin',
            portalTitle: 'OVPRE Admin Portal',
            subtitle: 'Manage authorized research and extension office operations and institutional records.',
            division: 'Office Administration',
            avatar: 'OA',
            nav: ['dashboard', 'records', 'research', 'extension', 'announcements', 'activities', 'settings'],
            primaryAction: { label: 'Review Proposals & Records', href: 'research.html', icon: 'ri-file-list-3-line' },
            canAccessSettings: true,
            canAccessRecords: true,
            canReviewResearch: true,
            canReviewExtension: true
        },
        'Research Director': {
            title: 'Research Director',
            roleName: 'Research Director',
            portalTitle: 'Research Director Command Portal',
            subtitle: 'Institutional research evaluation, policy enforcement, RIUH ratings, and grant recommendations.',
            division: 'Research Division',
            avatar: 'RD',
            nav: ['dashboard', 'records', 'research', 'announcements', 'activities'],
            primaryAction: { label: 'Review Research Proposals', href: 'research.html', icon: 'ri-flask-line' },
            canAccessSettings: false,
            canAccessRecords: true,
            canReviewResearch: true,
            canReviewExtension: false
        },
        'Extension Director': {
            title: 'Extension Director',
            roleName: 'Extension Director',
            portalTitle: 'Extension Director Command Portal',
            subtitle: 'Institutional extension oversight, community policy guidelines, project evaluation, and resource allocation.',
            division: 'Community Extension Division',
            avatar: 'ED',
            nav: ['dashboard', 'records', 'extension', 'announcements', 'activities'],
            primaryAction: { label: 'Review Extension Proposals', href: 'extension.html', icon: 'ri-community-line' },
            canAccessSettings: false,
            canAccessRecords: true,
            canReviewResearch: false,
            canReviewExtension: true
        },
        'Research Coordinator': {
            title: 'College Research Coordinator',
            roleName: 'Research Coordinator',
            portalTitle: 'College Research Coordinator Portal',
            subtitle: 'College-level research liaison, initial proposal screening, endorsements, and local monitoring.',
            division: 'College Research Coordination',
            avatar: 'RC',
            nav: ['dashboard', 'research', 'announcements', 'activities'],
            primaryAction: { label: 'Review College Proposals', href: 'research.html', icon: 'ri-check-double-line' },
            canAccessSettings: false,
            canAccessRecords: false,
            canReviewResearch: true,
            canReviewExtension: false
        },
        'Extension Coordinator': {
            title: 'College Extension Coordinator',
            roleName: 'Extension Coordinator',
            portalTitle: 'College Extension Coordinator Portal',
            subtitle: 'College-level extension liaison, community project screening, and local monitoring.',
            division: 'College Extension Coordination',
            avatar: 'EC',
            nav: ['dashboard', 'extension', 'announcements', 'activities'],
            primaryAction: { label: 'Review College Projects', href: 'extension.html', icon: 'ri-check-double-line' },
            canAccessSettings: false,
            canAccessRecords: false,
            canReviewResearch: false,
            canReviewExtension: true
        },
        'Faculty Researcher': {
            title: 'Faculty Researcher',
            roleName: 'Faculty Researcher',
            portalTitle: 'Faculty Researcher Portal',
            subtitle: 'Submit research proposals, report milestones, monitor screening, and track grant status.',
            division: 'Faculty Research Proponent',
            avatar: 'FR',
            nav: ['dashboard', 'research', 'announcements', 'activities'],
            primaryAction: { label: 'Submit Research Proposal', href: 'research.html?action=new', icon: 'ri-add-circle-line' },
            canAccessSettings: false,
            canAccessRecords: false,
            canReviewResearch: false,
            canReviewExtension: false
        },
        'Faculty Extensionist': {
            title: 'Faculty Extensionist',
            roleName: 'Faculty Extensionist',
            portalTitle: 'Faculty Extensionist Portal',
            subtitle: 'Submit extension proposals, community milestones, liquidation, and impact documentation.',
            division: 'Faculty Extension Practitioner',
            avatar: 'FE',
            nav: ['dashboard', 'extension', 'announcements', 'activities'],
            primaryAction: { label: 'Submit Extension Proposal', href: 'extension.html?action=new', icon: 'ri-add-circle-line' },
            canAccessSettings: false,
            canAccessRecords: false,
            canReviewResearch: false,
            canReviewExtension: false
        }
    };

    // Default demo names
    const ROLE_DEMO_NAMES = {
        'Super Admin': 'Super Administrator',
        'OVPRE Admin': 'OVPRE Admin Office',
        'Research Director': 'Dr. Aris Ramos',
        'Extension Director': 'Dr. Maricel Gomez',
        'Research Coordinator': 'Prof. Engr. Mark Santos',
        'Extension Coordinator': 'Prof. Elena Cruz',
        'Faculty Researcher': 'Prof. Juan Dela Cruz',
        'Faculty Extensionist': 'Prof. Maria Bautista'
    };

    function normalizeRole(role) {
        if (!role) return 'Super Admin';
        const lower = role.toLowerCase();
        if (lower.includes('research') && lower.includes('director')) return 'Research Director';
        if (lower.includes('extension') && lower.includes('director')) return 'Extension Director';
        if (lower.includes('research') && lower.includes('coord')) return 'Research Coordinator';
        if (lower.includes('extension') && lower.includes('coord')) return 'Extension Coordinator';
        if (lower.includes('faculty') && lower.includes('extension')) return 'Faculty Extensionist';
        if (lower.includes('extensionist')) return 'Faculty Extensionist';
        if (lower.includes('faculty') && lower.includes('research')) return 'Faculty Researcher';
        if (lower.includes('researcher')) return 'Faculty Researcher';
        if (lower.includes('ovpre') || lower.includes('admin office')) return 'OVPRE Admin';
        if (lower.includes('super') || lower.includes('admin')) return 'Super Admin';
        return 'Super Admin';
    }

    function getCurrentUser() {
        try {
            const raw = localStorage.getItem('irecord_user') || sessionStorage.getItem('irecord_user');
            if (raw) {
                const parsed = JSON.parse(raw);
                parsed.role = normalizeRole(parsed.role);
                if (!parsed.fullname) {
                    parsed.fullname = ROLE_DEMO_NAMES[parsed.role] || 'User';
                }
                return parsed;
            }
        } catch (e) {}

        const defaultUser = {
            id: 1,
            username: 'admin',
            fullname: 'Super Administrator',
            role: 'Super Admin',
            department: '',
            logged_in: true
        };
        sessionStorage.setItem('irecord_user', JSON.stringify(defaultUser));
        localStorage.setItem('irecord_user', JSON.stringify(defaultUser));
        return defaultUser;
    }

    window.switchRole = function (targetRole) {
        const normalized = normalizeRole(targetRole);
        const currentUser = getCurrentUser();
        currentUser.role = normalized;
        currentUser.fullname = ROLE_DEMO_NAMES[normalized] || (normalized + ' User');
        if (normalized.includes('Coordinator')) {
            currentUser.department = currentUser.department || 'College of Computer Studies and Technology (CCST)';
        }

        sessionStorage.setItem('irecord_user', JSON.stringify(currentUser));
        localStorage.setItem('irecord_user', JSON.stringify(currentUser));

        // Re-route appropriately if page is restricted
        const currentPage = window.location.pathname.split('/').pop() || 'dashboard.html';
        const roleConf = ROLE_PORTALS[normalized];

        if (currentPage === 'admin-settings.html' && !roleConf.canAccessSettings) {
            window.location.href = 'dashboard.html';
            return;
        }
        if (currentPage === 'admin-documents.html' && !roleConf.canAccessRecords) {
            window.location.href = 'dashboard.html';
            return;
        }

        window.location.reload();
    };

    function initRoleUI() {
        const user = getCurrentUser();
        const roleKey = user.role;
        const config = ROLE_PORTALS[roleKey] || ROLE_PORTALS['Super Admin'];

        // 1. Update Profile identity in TopBar
        const nameDisplay = document.getElementById('userNameDisplay');
        if (nameDisplay) nameDisplay.textContent = user.fullname;

        const dropdownName = document.getElementById('dropdownUserName');
        if (dropdownName) dropdownName.textContent = user.fullname;

        const dropdownRole = document.getElementById('dropdownUserRole');
        if (dropdownRole) {
            const deptText = user.department ? ` • ${user.department.split('(')[1]?.replace(')', '') || user.department}` : '';
            dropdownRole.textContent = `${config.title}${deptText}`;
        }

        const avatarElements = document.querySelectorAll('.avatar');
        avatarElements.forEach(el => {
            el.textContent = config.avatar;
        });

        // 2. Custom Role Switcher inside Profile Dropdown
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileDropdown && !document.getElementById('roleSwitcherBox')) {
            const switcherBox = document.createElement('div');
            switcherBox.id = 'roleSwitcherBox';
            switcherBox.style.cssText = 'padding: 10px 14px; background: #f8fafc; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; margin: 4px 0;';

            let switcherHTML = `
                <div style="font-size: 0.72rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between;">
                    <span>Switch Role / Portal</span>
                    <i class="ri-user-shared-line"></i>
                </div>
                <div style="display: flex; flex-direction: column; gap: 3px;">
            `;

            const rolesList = [
                { role: 'Super Admin', label: 'Super Administrator', icon: 'ri-shield-star-line', color: '#15803d' },
                { role: 'Research Director', label: 'Research Director', icon: 'ri-flask-line', color: '#0284c7' },
                { role: 'Extension Director', label: 'Extension Director', icon: 'ri-community-line', color: '#0d9488' },
                { role: 'Research Coordinator', label: 'Research Coordinator', icon: 'ri-user-settings-line', color: '#7c3aed' },
                { role: 'Extension Coordinator', label: 'Extension Coordinator', icon: 'ri-user-shared-line', color: '#d97706' },
                { role: 'Faculty Researcher', label: 'Faculty Researcher', icon: 'ri-file-text-line', color: '#2563eb' },
                { role: 'Faculty Extensionist', label: 'Faculty Extensionist', icon: 'ri-team-line', color: '#16a34a' }
            ];

            rolesList.forEach(r => {
                const isActive = (r.role === roleKey);
                switcherHTML += `
                    <button type="button" onclick="window.switchRole('${r.role}')" style="display: flex; align-items: center; justify-content: space-between; padding: 6px 8px; border-radius: 6px; border: none; background: ${isActive ? '#dcfce7' : 'transparent'}; color: ${isActive ? '#15803d' : '#334155'}; font-size: 0.8rem; font-weight: ${isActive ? '700' : '500'}; cursor: pointer; text-align: left; transition: background 0.15s;">
                        <span style="display: flex; align-items: center; gap: 6px;">
                            <i class="${r.icon}" style="color: ${r.color};"></i> ${r.label}
                        </span>
                        ${isActive ? '<i class="ri-check-line" style="font-weight: 800; color: #15803d;"></i>' : ''}
                    </button>
                `;
            });

            switcherHTML += `</div>`;
            switcherBox.innerHTML = switcherHTML;

            // Insert before logout link
            const logoutLink = profileDropdown.querySelector('a[href*="logout"]');
            if (logoutLink) {
                profileDropdown.insertBefore(switcherBox, logoutLink);
            } else {
                profileDropdown.appendChild(switcherBox);
            }
        }

        // 3. Filter Sidebar Navigation according to Role Permissions
        const sidebarNav = document.querySelector('.sidebar-nav');
        if (sidebarNav) {
            const navMap = {
                'dashboard.html': 'dashboard',
                'admin-documents.html': 'records',
                'research.html': 'research',
                'extension.html': 'extension',
                'announcements.html': 'announcements',
                'activities.html': 'activities',
                'admin-settings.html': 'settings'
            };

            sidebarNav.querySelectorAll('a').forEach(link => {
                const href = link.getAttribute('href') || '';
                const baseHref = href.split('?')[0];
                const permissionKey = navMap[baseHref];

                if (permissionKey) {
                    if (config.nav.includes(permissionKey)) {
                        link.style.display = 'flex';
                    } else {
                        link.style.display = 'none';
                    }
                }
            });
        }

        // 4. Update Sidebar Footer Title
        const sidebarFooterSpan = document.querySelector('.sidebar-footer span');
        if (sidebarFooterSpan) {
            sidebarFooterSpan.textContent = config.division;
        }

        // 5. Page-Specific Adaptations
        const currentPath = window.location.pathname.split('/').pop() || 'dashboard.html';

        if (currentPath === 'dashboard.html') {
            adaptDashboardPage(config, user);
        } else if (currentPath === 'research.html') {
            adaptResearchPage(config, user);
        } else if (currentPath === 'extension.html') {
            adaptExtensionPage(config, user);
        } else if (currentPath === 'admin-settings.html') {
            adaptSettingsPage(config, user);
        }
    }

    function adaptDashboardPage(config, user) {
        // Welcome panel title and subtitle
        const welcomePanel = document.querySelector('.welcome-panel');
        if (welcomePanel) {
            const h2 = welcomePanel.querySelector('h2');
            const p = welcomePanel.querySelector('p');
            if (h2) {
                let iconClass = 'ri-bar-chart-box-line';
                if (config.roleName.includes('Research')) iconClass = 'ri-flask-line';
                else if (config.roleName.includes('Extension')) iconClass = 'ri-community-line';
                h2.innerHTML = `<i class="${iconClass}" style="color: #15803d;"></i> ${config.portalTitle}`;
            }
            if (p) {
                p.textContent = config.subtitle;
            }

            // Check if hero action button exists or add one
            let heroActionArea = welcomePanel.querySelector('.welcome-hero-action');
            if (!heroActionArea) {
                heroActionArea = document.createElement('div');
                heroActionArea.className = 'welcome-hero-action';
                heroActionArea.style.cssText = 'margin-top: 14px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;';
                welcomePanel.appendChild(heroActionArea);
            }

            heroActionArea.innerHTML = `
                <a href="${config.primaryAction.href}" class="primary-btn" style="display: inline-flex; align-items: center; gap: 8px; background: #15803d; color: #fff; padding: 10px 18px; border-radius: 10px; font-weight: 700; text-decoration: none; box-shadow: 0 4px 12px rgba(21, 128, 61, 0.2);">
                    <i class="${config.primaryAction.icon}"></i> ${config.primaryAction.label}
                </a>
                <span style="font-size: 0.84rem; font-weight: 600; color: #475569; background: #f1f5f9; padding: 6px 14px; border-radius: 20px;">
                    Active Role: <strong style="color: #0f172a;">${config.roleName}</strong>
                </span>
            `;
        }
    }

    function adaptResearchPage(config, user) {
        const welcomePanel = document.querySelector('.welcome-panel');
        if (welcomePanel) {
            const h2 = welcomePanel.querySelector('h2');
            const p = welcomePanel.querySelector('p');

            if (config.roleName === 'Faculty Researcher') {
                if (h2) h2.innerHTML = `<i class="ri-flask-line" style="color: #15803d;"></i> My Research Proposals &amp; Grants`;
                if (p) p.textContent = `Submit institutional research proposals, track college coordinator endorsements, and monitor grant disbursement status.`;
            } else if (config.roleName === 'Research Coordinator') {
                if (h2) h2.innerHTML = `<i class="ri-flask-line" style="color: #15803d;"></i> College Research Screening &amp; Endorsements`;
                if (p) p.textContent = `Screen faculty proposals submitted from your college, verify ethical compliance, and endorse to the Research Director.`;
            } else if (config.roleName === 'Research Director') {
                if (h2) h2.innerHTML = `<i class="ri-flask-line" style="color: #15803d;"></i> Institutional Research Evaluation &amp; Approvals`;
                if (p) p.textContent = `Evaluate endorsed research proposals across all colleges, assign RIUH scores, and recommend executive grant funding.`;
            } else if (config.roleName.includes('Extension')) {
                // Extensionist on research page notice
                const noticeBanner = document.createElement('div');
                noticeBanner.style.cssText = 'background: #eff6ff; border: 1.5px solid #bfdbfe; color: #1e40af; padding: 12px 18px; border-radius: 12px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; font-size: 0.9rem;';
                noticeBanner.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="ri-information-line" style="font-size: 1.25rem;"></i>
                        <span>You are signed in as <strong>${config.roleName}</strong>. Your main focus is Community Extension Services.</span>
                    </div>
                    <a href="extension.html" style="background: #2563eb; color: #fff; padding: 6px 14px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 0.82rem;">
                        Go to Extension Initiatives <i class="ri-arrow-right-line"></i>
                    </a>
                `;
                welcomePanel.insertAdjacentElement('beforebegin', noticeBanner);
            }
        }
    }

    function adaptExtensionPage(config, user) {
        const welcomePanel = document.querySelector('.welcome-panel');
        if (welcomePanel) {
            const h2 = welcomePanel.querySelector('h2');
            const p = welcomePanel.querySelector('p');

            if (config.roleName === 'Faculty Extensionist') {
                if (h2) h2.innerHTML = `<i class="ri-community-line" style="color: #0d9488;"></i> My Community Outreach &amp; Extension Projects`;
                if (p) p.textContent = `Submit community projects, log training milestones, track barangay partnership impact, and submit liquidation reports.`;
            } else if (config.roleName === 'Extension Coordinator') {
                if (h2) h2.innerHTML = `<i class="ri-community-line" style="color: #0d9488;"></i> College Extension Screening &amp; Coordination`;
                if (p) p.textContent = `Screen and endorse extension initiatives from your college, monitor community visits, and review impact documentation.`;
            } else if (config.roleName === 'Extension Director') {
                if (h2) h2.innerHTML = `<i class="ri-community-line" style="color: #0d9488;"></i> Institutional Extension Oversight &amp; Evaluation`;
                if (p) p.textContent = `Evaluate community development programs across all colleges, approve LGU/Barangay MoA clearances, and allocate institutional outreach resources.`;
            } else if (config.roleName.includes('Research') && config.roleName !== 'Super Admin') {
                // Researcher on extension page notice
                const noticeBanner = document.createElement('div');
                noticeBanner.style.cssText = 'background: #eff6ff; border: 1.5px solid #bfdbfe; color: #1e40af; padding: 12px 18px; border-radius: 12px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; font-size: 0.9rem;';
                noticeBanner.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="ri-information-line" style="font-size: 1.25rem;"></i>
                        <span>You are signed in as <strong>${config.roleName}</strong>. Your main focus is Academic Research Proposals.</span>
                    </div>
                    <a href="research.html" style="background: #0284c7; color: #fff; padding: 6px 14px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 0.82rem;">
                        Go to Research Proposals <i class="ri-arrow-right-line"></i>
                    </a>
                `;
                welcomePanel.insertAdjacentElement('beforebegin', noticeBanner);
            }
        }
    }

    function adaptSettingsPage(config, user) {
        if (!config.canAccessSettings) {
            const contentArea = document.querySelector('.content-area');
            if (contentArea) {
                contentArea.innerHTML = `
                    <div style="max-width: 650px; margin: 40px auto; padding: 32px 28px; background: #ffffff; border-radius: 16px; border: 1.5px solid #fecaca; box-shadow: 0 10px 30px rgba(0,0,0,0.06); text-align: center;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: inline-grid; place-items: center; font-size: 1.7rem; margin-bottom: 16px;">
                            <i class="ri-shield-keyhole-line"></i>
                        </div>
                        <h2 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0 0 8px;">Executive Administration Required</h2>
                        <p style="font-size: 0.92rem; color: #64748b; line-height: 1.5; margin: 0 0 24px;">
                            The System Configuration and Security module is reserved for <strong>Super Administrators</strong> and <strong>OVPRE Admin Officers</strong>. You are currently viewing as <strong>${config.roleName}</strong>.
                        </p>
                        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                            <a href="dashboard.html" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 10px; background: #15803d; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 0.9rem;">
                                <i class="ri-arrow-left-line"></i> Return to Dashboard
                            </a>
                            <button type="button" onclick="window.switchRole('Super Admin')" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 10px; background: #facc15; color: #0f172a; border: none; font-weight: 700; font-size: 0.9rem; cursor: pointer;">
                                <i class="ri-shield-star-line"></i> Switch to Super Admin View
                            </button>
                        </div>
                    </div>
                `;
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRoleUI);
    } else {
        initRoleUI();
    }
})();
