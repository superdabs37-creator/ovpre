/**
 * OVPRE I-Record - Supabase Client Bridge
 * ------------------------------------------------------------------
 * Connects HTML5, CSS3, JavaScript (ES6+), Remix Icon, and Font Awesome
 * directly to the Supabase Cloud Database & Storage.
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
            client = window.supabase.createClient(SUPABASE_CONFIG.url, SUPABASE_CONFIG.anonKey);
            console.info('[OVPRE Supabase] Connected successfully to ' + SUPABASE_CONFIG.url);
        } catch (e) {
            console.warn('[OVPRE Supabase] Init error:', e.message);
        }
    }

    const OVPRE_SUPABASE = (window.OVPRE_SUPABASE = {
        config: SUPABASE_CONFIG,
        client: client,
        isReady: function () {
            return client !== null;
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
                    grant_amount: parseFloat(project.budget) || 0
                };
                const { data, error } = await client
                    .from('research_projects')
                    .insert([payload])
                    .select();
                if (error) throw error;
                return data ? data[0] : null;
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
                return data ? data[0] : null;
            } catch (err) {
                console.warn('[OVPRE Supabase] updateResearchProject failed:', err.message);
                return null;
            }
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
                    workflow_stage: project.stage || 'Proposal'
                };
                const { data, error } = await client
                    .from('extension_projects')
                    .insert([payload])
                    .select();
                if (error) throw error;
                return data ? data[0] : null;
            } catch (err) {
                console.warn('[OVPRE Supabase] createExtensionProject failed:', err.message);
                return null;
            }
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
                    status: ann.status || 'Published'
                };
                const { data, error } = await client
                    .from('announcements')
                    .insert([payload])
                    .select();
                if (error) throw error;
                return data ? data[0] : null;
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
                return data ? data[0] : null;
            } catch (err) {
                console.warn('[OVPRE Supabase] updateAnnouncement failed:', err.message);
                return null;
            }
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

        // --- REALTIME SUBSCRIPTION ---
        subscribeToTable: function (tableName, onEvent) {
            if (!client) return null;
            try {
                const channel = client
                    .channel('ovpre-realtime-' + tableName)
                    .on('postgres_changes', { event: '*', schema: 'public', table: tableName }, function (payload) {
                        console.info('[OVPRE Realtime] Event on ' + tableName + ':', payload.eventType);
                        if (typeof onEvent === 'function') onEvent(payload);
                    })
                    .subscribe();
                return channel;
            } catch (err) {
                console.warn('[OVPRE Supabase] Realtime error:', err.message);
                return null;
            }
        }
    });
})();
