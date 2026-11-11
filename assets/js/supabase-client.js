/**
 * OVPRE I-Record - Supabase Client Bridge & Realtime Engine
 * ------------------------------------------------------------------
 * Connects HTML5, CSS3, JavaScript (ES6+), Remix Icon, and Font Awesome
 * directly to the Supabase Cloud Database, Storage, and Realtime Engine.
 */
(function () {
    'use strict';

    const SUPABASE_CONFIG = {
        url: 'https://yolquvpmptfjblfldbuh.supabase.co',
        anonKey: 'sb_publishable_0tb6TAlGiK3xHBjZkNuCZw_l6ImNFmC',
        storageBucket: 'irecord-records'
    };

    let client = null;
    if (window.supabase && typeof window.supabase.createClient === 'function') {
        try {
            client = window.supabase.createClient(SUPABASE_CONFIG.url, SUPABASE_CONFIG.anonKey, {
                realtime: {
                    params: {
                        eventsPerSecond: 10
                    }
                }
            });
            console.info('[OVPRE Supabase] Connected successfully to ' + SUPABASE_CONFIG.url);
        } catch (e) {
            console.warn('[OVPRE Supabase] Init error:', e.message);
        }
    }

    // Client Session / Role Helper
    function getCurrentUser() {
        try {
            if (window.OVPRE && OVPRE.user) return OVPRE.user();
            const raw = localStorage.getItem('irecord_user') || sessionStorage.getItem('irecord_user');
            if (raw) return JSON.parse(raw);
        } catch (e) {}
        return { fullname: 'System User', role: 'Super Admin', id: 1 };
    }

    const clientId = 'client_' + Math.random().toString(36).substring(2, 9);
    let globalRealtimeChannel = null;

    const OVPRE_SUPABASE = (window.OVPRE_SUPABASE = {
        config: SUPABASE_CONFIG,
        client: client,
        clientId: clientId,
        isReady: function () {
            return client !== null;
        },

        // --- MAP SUPABASE SCHEMAS TO LOCAL RECORD SCHEMAS ---
        mapResearchToLocal: function (r) {
            return {
                id: r.id,
                title: r.title,
                researchers: r.researchers || 'Faculty Researcher',
                college: r.college || 'College of Computer Studies and Technology',
                funding_source: r.funding_source || 'Institutional Research Grant',
                budget: Number(r.grant_amount || 0),
                approved_budget: Number(r.grant_amount || 0),
                stage: r.workflow_stage || (r.status === 'Approved' ? 'Completed' : 'Stage 1: Coordinator Screening'),
                status: r.status || 'Pending Coordinator Review',
                progress_status: r.progress_status || (r.status === 'Approved' ? 'Completed' : 'Planning'),
                created_at: r.created_at ? r.created_at.slice(0, 10) : new Date().toISOString().slice(0, 10),
                abstract: r.abstract || '',
                document_filename: r.document_filename || (r.title ? r.title.replace(/[^a-zA-Z0-9]/g, '_').slice(0, 24) + '_Doc.pdf' : 'document.pdf'),
                document_url: r.document_url || '',
                archived: Boolean(r.archived_at),
                coordinator_remarks: r.coordinator_remarks || '',
                director_remarks: r.director_remarks || '',
                executive_remarks: r.executive_remarks || ''
            };
        },

        mapExtensionToLocal: function (e) {
            return {
                id: e.id,
                title: e.project_title || e.title,
                coordinator: e.coordinator || 'Faculty Lead',
                partner: e.community_partner || e.partner || 'Local Community Partner',
                budget: Number(e.budget || e.approved_budget || 0),
                approved_budget: Number(e.approved_budget || e.budget || 0),
                college: e.college || 'College of Nursing and Allied Health Sciences',
                stage: e.workflow_stage || (e.status === 'Approved' ? 'Completed' : 'Proposal'),
                status: e.status || 'Pending Coordinator Review',
                progress_status: e.progress_status || (e.status === 'Approved' ? 'Completed' : 'Ongoing'),
                start_date: e.start_date || (e.created_at ? e.created_at.slice(0, 10) : '2026-08-22'),
                end_date: e.end_date || '2027-02-22',
                created_at: e.created_at ? e.created_at.slice(0, 10) : new Date().toISOString().slice(0, 10),
                location: e.location || '',
                description: e.description || '',
                coordinator_remarks: e.coordinator_remarks || '',
                director_remarks: e.director_remarks || '',
                executive_remarks: e.executive_remarks || '',
                document_filename: e.document_filename || (e.project_title ? e.project_title.replace(/[^a-zA-Z0-9]/g, '_').slice(0, 24) + '_Doc.pdf' : 'document.pdf'),
                document_url: e.document_url || '',
                archived: Boolean(e.archived_at)
            };
        },

        mapAnnouncementToLocal: function (a) {
            return {
                id: a.id,
                title: a.title,
                content: a.content || '',
                category: a.category || 'General Notice',
                audience: a.audience || 'All Faculty',
                priority: a.priority || 'Normal',
                date: a.created_at ? new Date(a.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Nov 11, 2026',
                expiry: a.expiry_date || '',
                archived: a.status === 'Archived' || Boolean(a.archived_at)
            };
        },

        // --- RESEARCH PROPOSALS ---
        fetchResearchProjects: async function () {
            if (!client) return null;
            try {
                const { data, error } = await client
                    .from('research_projects')
                    .select('*')
                    .order('id', { ascending: false });
                if (error) throw error;
                return data;
            } catch (err) {
                console.warn('[OVPRE Supabase] fetchResearchProjects failed:', err.message);
                return null;
            }
        },

        createResearchProject: async function (project) {
            if (!client) return null;
            try {
                const payload = {
                    title: project.title,
                    researchers: project.researchers || project.lead,
                    abstract: project.abstract || '',
                    funding_source: project.funding_source || '',
                    status: project.status || 'Pending Coordinator Review',
                    workflow_stage: project.stage || 'Proposal',
                    grant_amount: parseFloat(project.budget) || 0,
                    archived_at: project.archived ? new Date().toISOString() : null
                };
                const { data, error } = await client
                    .from('research_projects')
                    .insert([payload])
                    .select();
                if (error) throw error;
                const created = data ? data[0] : null;
                if (created) {
                    OVPRE_SUPABASE.broadcastChange('research_projects', 'INSERT', created, {
                        title: project.title,
                        actionText: 'submitted a new research proposal'
                    });
                }
                return created;
            } catch (err) {
                console.warn('[OVPRE Supabase] createResearchProject failed:', err.message);
                return null;
            }
        },

        updateResearchProject: async function (id, updates) {
            if (!client) return null;
            try {
                const { data, error } = await client
                    .from('research_projects')
                    .update(updates)
                    .eq('id', id)
                    .select();
                if (error) throw error;
                const updated = data ? data[0] : null;
                if (updated) {
                    OVPRE_SUPABASE.broadcastChange('research_projects', 'UPDATE', updated, {
                        title: updated.title,
                        actionText: updates.status ? `updated status to ${updates.status}` : 'updated project details'
                    });
                }
                return updated;
            } catch (err) {
                console.warn('[OVPRE Supabase] updateResearchProject failed:', err.message);
                return null;
            }
        },

        archiveResearchProject: async function (id, archived) {
            return await this.updateResearchProject(id, {
                archived_at: archived ? new Date().toISOString() : null
            });
        },

        // --- EXTENSION PROJECTS ---
        fetchExtensionProjects: async function () {
            if (!client) return null;
            try {
                const { data, error } = await client
                    .from('extension_projects')
                    .select('*')
                    .order('id', { ascending: false });
                if (error) throw error;
                return data;
            } catch (err) {
                console.warn('[OVPRE Supabase] fetchExtensionProjects failed:', err.message);
                return null;
            }
        },

        createExtensionProject: async function (project) {
            if (!client) return null;
            try {
                const payload = {
                    project_title: project.title || project.project_title,
                    coordinator: project.coordinator || project.lead,
                    community_partner: project.partner || project.community_partner,
                    budget: parseFloat(project.budget) || 0,
                    location: project.location || '',
                    status: project.status || 'Pending Coordinator Review',
                    workflow_stage: project.stage || 'Proposal',
                    archived_at: project.archived ? new Date().toISOString() : null
                };
                const { data, error } = await client
                    .from('extension_projects')
                    .insert([payload])
                    .select();
                if (error) throw error;
                const created = data ? data[0] : null;
                if (created) {
                    OVPRE_SUPABASE.broadcastChange('extension_projects', 'INSERT', created, {
                        title: project.title,
                        actionText: 'submitted a new extension project'
                    });
                }
                return created;
            } catch (err) {
                console.warn('[OVPRE Supabase] createExtensionProject failed:', err.message);
                return null;
            }
        },

        updateExtensionProject: async function (id, updates) {
            if (!client) return null;
            try {
                const { data, error } = await client
                    .from('extension_projects')
                    .update(updates)
                    .eq('id', id)
                    .select();
                if (error) throw error;
                const updated = data ? data[0] : null;
                if (updated) {
                    OVPRE_SUPABASE.broadcastChange('extension_projects', 'UPDATE', updated, {
                        title: updated.project_title || updated.title,
                        actionText: updates.status ? `updated status to ${updates.status}` : 'updated project details'
                    });
                }
                return updated;
            } catch (err) {
                console.warn('[OVPRE Supabase] updateExtensionProject failed:', err.message);
                return null;
            }
        },

        archiveExtensionProject: async function (id, archived) {
            return await this.updateExtensionProject(id, {
                archived_at: archived ? new Date().toISOString() : null
            });
        },

        // --- ANNOUNCEMENTS ---
        fetchAnnouncements: async function () {
            if (!client) return null;
            try {
                const { data, error } = await client
                    .from('announcements')
                    .select('*')
                    .order('id', { ascending: false });
                if (error) throw error;
                return data;
            } catch (err) {
                console.warn('[OVPRE Supabase] fetchAnnouncements failed:', err.message);
                return null;
            }
        },

        createAnnouncement: async function (ann) {
            if (!client) return null;
            try {
                const payload = {
                    title: ann.title,
                    content: ann.content,
                    audience: ann.audience || 'All',
                    status: ann.status || 'Published',
                    archived_at: ann.archived ? new Date().toISOString() : null
                };
                const { data, error } = await client
                    .from('announcements')
                    .insert([payload])
                    .select();
                if (error) throw error;
                const created = data ? data[0] : null;
                if (created) {
                    OVPRE_SUPABASE.broadcastChange('announcements', 'INSERT', created, {
                        title: ann.title,
                        actionText: 'published an announcement'
                    });
                }
                return created;
            } catch (err) {
                console.warn('[OVPRE Supabase] createAnnouncement failed:', err.message);
                return null;
            }
        },

        updateAnnouncement: async function (id, updates) {
            if (!client) return null;
            try {
                const { data, error } = await client
                    .from('announcements')
                    .update(updates)
                    .eq('id', id)
                    .select();
                if (error) throw error;
                const updated = data ? data[0] : null;
                if (updated) {
                    OVPRE_SUPABASE.broadcastChange('announcements', 'UPDATE', updated, {
                        title: updated.title,
                        actionText: updates.status ? `announcement status is now ${updates.status}` : 'updated announcement'
                    });
                }
                return updated;
            } catch (err) {
                console.warn('[OVPRE Supabase] updateAnnouncement failed:', err.message);
                return null;
            }
        },

        archiveAnnouncement: async function (id, archived) {
            return await this.updateAnnouncement(id, {
                status: archived ? 'Archived' : 'Published',
                archived_at: archived ? new Date().toISOString() : null
            });
        },

        // --- ACTIVITIES ---
        fetchActivities: async function () {
            if (!client) return null;
            try {
                const { data, error } = await client
                    .from('activities')
                    .select('*')
                    .order('date', { ascending: true });
                if (error) throw error;
                return data;
            } catch (err) {
                console.warn('[OVPRE Supabase] fetchActivities failed:', err.message);
                return null;
            }
        },

        // --- FILE STORAGE UPLOAD ---
        uploadDocument: async function (file, folder) {
            if (!client) return null;
            try {
                const cleanName = Date.now() + '_' + file.name.replace(/[^a-zA-Z0-9._-]/g, '_');
                const filePath = (folder ? folder + '/' : '') + cleanName;
                const { data, error } = await client.storage
                    .from(SUPABASE_CONFIG.storageBucket)
                    .upload(filePath, file, { cacheControl: '3600', upsert: true });
                if (error) throw error;
                const { data: publicData } = client.storage
                    .from(SUPABASE_CONFIG.storageBucket)
                    .getPublicUrl(filePath);
                return {
                    path: filePath,
                    url: publicData?.publicUrl || ''
                };
            } catch (err) {
                console.warn('[OVPRE Supabase] uploadDocument failed:', err.message);
                return null;
            }
        },

        // --- REALTIME ENGINE & BROADCASTING ---
        broadcastChange: function (tableName, eventType, record, meta) {
            if (!globalRealtimeChannel) return;
            const user = getCurrentUser();
            const message = {
                senderId: clientId,
                table: tableName,
                eventType: eventType,
                record: record,
                meta: meta || {},
                user: {
                    fullname: user.fullname || 'Institutional User',
                    role: user.role || 'Member'
                },
                timestamp: Date.now()
            };

            try {
                globalRealtimeChannel.send({
                    type: 'broadcast',
                    event: 'ovpre_table_change',
                    payload: message
                });
                console.info('[OVPRE Realtime] Broadcast sent:', tableName, eventType);
            } catch (err) {
                console.warn('[OVPRE Realtime] Broadcast error:', err.message);
            }
        },

        // Handle incoming changes from either Broadcast OR PostgreSQL Changes
        handleIncomingRealtimeEvent: function (payload, source) {
            if (!payload) return;
            // Ignore own broadcast echoes
            if (payload.senderId && payload.senderId === clientId) return;

            const tableName = payload.table || (payload.table_name) || (payload.schema ? payload.table : null);
            const eventType = payload.eventType || payload.type || 'UPDATE';
            const record = payload.record || payload.new || payload.data;
            const meta = payload.meta || {};
            const senderUser = payload.user || { fullname: 'Portal User', role: 'Collaborator' };

            console.info(`[OVPRE Realtime] Received live change via ${source}:`, tableName, eventType, record);

            // Notify UI via non-intrusive toast
            if (window.OVPRE && OVPRE.toast && (meta.actionText || record?.title || record?.project_title)) {
                const desc = meta.title || record?.title || record?.project_title || tableName;
                const action = meta.actionText || (eventType === 'INSERT' ? 'added a new record' : 'updated record');
                OVPRE.toast(`⚡ Live Update: ${senderUser.fullname} (${senderUser.role}) ${action}: "${desc.slice(0, 32)}..."`, 'info');
            }

            // Sync Table & Local Storage according to tableName
            if (tableName === 'research_projects') {
                OVPRE_SUPABASE.syncResearchProjects();
            } else if (tableName === 'extension_projects') {
                OVPRE_SUPABASE.syncExtensionProjects();
            } else if (tableName === 'announcements') {
                OVPRE_SUPABASE.syncAnnouncements();
            } else if (tableName === 'activities') {
                if (typeof window.loadActivities === 'function' && typeof window.renderAll === 'function') {
                    window.renderAll();
                }
            }

            // Dispatch global event for custom page listeners
            window.dispatchEvent(new CustomEvent('ovpre:realtime:change', {
                detail: { tableName, eventType, record, senderUser }
            }));
        },

        // Background Remote Sync helpers that seamlessly merge cloud records into localStorage
        syncResearchProjects: async function (callback) {
            const data = await this.fetchResearchProjects();
            if (data && Array.isArray(data) && data.length) {
                const mapped = data.map(this.mapResearchToLocal);
                try {
                    const raw = localStorage.getItem('ovpre_research_projects');
                    let localList = raw ? JSON.parse(raw) : [];
                    // Merge Supabase records with local, updating existing IDs
                    mapped.forEach(cloudItem => {
                        const idx = localList.findIndex(p => p.id === cloudItem.id);
                        if (idx !== -1) {
                            localList[idx] = Object.assign({}, localList[idx], cloudItem);
                        } else {
                            localList.unshift(cloudItem);
                        }
                    });
                    localStorage.setItem('ovpre_research_projects', JSON.stringify(localList));
                } catch (e) {}

                if (typeof window.renderTable === 'function' && window.location.pathname.includes('research.html')) {
                    window.renderTable();
                }
                if (typeof callback === 'function') callback(data);
                window.dispatchEvent(new CustomEvent('ovpre:realtime:research'));
            }
        },

        syncExtensionProjects: async function (callback) {
            const data = await this.fetchExtensionProjects();
            if (data && Array.isArray(data) && data.length) {
                const mapped = data.map(this.mapExtensionToLocal);
                try {
                    const raw = localStorage.getItem('ovpre_extension_projects');
                    let localList = raw ? JSON.parse(raw) : [];
                    mapped.forEach(cloudItem => {
                        const idx = localList.findIndex(p => p.id === cloudItem.id);
                        if (idx !== -1) {
                            localList[idx] = Object.assign({}, localList[idx], cloudItem);
                        } else {
                            localList.unshift(cloudItem);
                        }
                    });
                    localStorage.setItem('ovpre_extension_projects', JSON.stringify(localList));
                } catch (e) {}

                if (typeof window.renderTable === 'function' && window.location.pathname.includes('extension.html')) {
                    window.renderTable();
                }
                if (typeof callback === 'function') callback(data);
                window.dispatchEvent(new CustomEvent('ovpre:realtime:extension'));
            }
        },

        syncAnnouncements: async function (callback) {
            const data = await this.fetchAnnouncements();
            if (data && Array.isArray(data) && data.length) {
                const mapped = data.map(this.mapAnnouncementToLocal);
                try {
                    const raw = localStorage.getItem('ovpre_announcements');
                    let localList = raw ? JSON.parse(raw) : [];
                    mapped.forEach(cloudItem => {
                        const idx = localList.findIndex(a => a.id === cloudItem.id);
                        if (idx !== -1) {
                            localList[idx] = Object.assign({}, localList[idx], cloudItem);
                        } else {
                            localList.unshift(cloudItem);
                        }
                    });
                    localStorage.setItem('ovpre_announcements', JSON.stringify(localList));
                } catch (e) {}

                if (typeof window.renderAnnouncements === 'function' && window.location.pathname.includes('announcements.html')) {
                    window.renderAnnouncements();
                }
                if (typeof callback === 'function') callback(data);
                window.dispatchEvent(new CustomEvent('ovpre:realtime:announcements'));
            }
        },

        // --- REALTIME HUB INITIALIZER ---
        initRealtimeHub: function () {
            if (!client) {
                console.warn('[OVPRE Realtime] Client not initialized. Running in local mode.');
                return;
            }

            try {
                globalRealtimeChannel = client.channel('ovpre-realtime-global', {
                    config: {
                        broadcast: { self: false }
                    }
                });

                // 1. Listen for instant Broadcast events across browser sessions
                globalRealtimeChannel.on('broadcast', { event: 'ovpre_table_change' }, function (msg) {
                    OVPRE_SUPABASE.handleIncomingRealtimeEvent(msg.payload, 'Broadcast Channel');
                });

                // 2. Listen for PostgreSQL Changes on replicated database tables
                ['research_projects', 'extension_projects', 'announcements', 'activities'].forEach(function (table) {
                    globalRealtimeChannel.on('postgres_changes', { event: '*', schema: 'public', table: table }, function (payload) {
                        OVPRE_SUPABASE.handleIncomingRealtimeEvent({
                            table: table,
                            eventType: payload.eventType,
                            record: payload.new || payload.old
                        }, 'PostgreSQL Replication');
                    });
                });

                globalRealtimeChannel.subscribe(function (status, err) {
                    console.info('[OVPRE Realtime Hub] Channel Status:', status);
                    if (status === 'SUBSCRIBED') {
                        OVPRE_SUPABASE.renderRealtimeBadge(true);
                    } else if (status === 'CHANNEL_ERROR' || status === 'TIMED_OUT') {
                        console.warn('[OVPRE Realtime Hub] Subscription issue:', err);
                    }
                });

                // Perform initial cloud sync
                setTimeout(function () {
                    if (window.location.pathname.includes('research.html')) {
                        OVPRE_SUPABASE.syncResearchProjects();
                    } else if (window.location.pathname.includes('extension.html')) {
                        OVPRE_SUPABASE.syncExtensionProjects();
                    } else if (window.location.pathname.includes('announcements.html')) {
                        OVPRE_SUPABASE.syncAnnouncements();
                    }
                }, 100);

            } catch (err) {
                console.warn('[OVPRE Realtime Hub] Init exception:', err.message);
            }
        },

        // Render Live Realtime badge in topbar
        renderRealtimeBadge: function (isLive) {
            const topbar = document.querySelector('.topbar') || document.querySelector('.panel-heading');
            if (!topbar || document.getElementById('ovpreRealtimeBadge')) return;

            const badge = document.createElement('div');
            badge.id = 'ovpreRealtimeBadge';
            badge.className = 'realtime-status-badge';
            badge.title = 'Connected to Supabase Realtime Engine. Cloud updates sync automatically.';
            badge.style.marginRight = '8px';
            badge.innerHTML = '<span class="realtime-dot"></span> <span>Live Realtime</span>';

            const searchForm = topbar.querySelector('.topbar-search');
            if (searchForm) {
                topbar.insertBefore(badge, searchForm);
            } else {
                topbar.appendChild(badge);
            }
        }
    });

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            OVPRE_SUPABASE.initRealtimeHub();
        });
    } else {
        OVPRE_SUPABASE.initRealtimeHub();
    }
})();
