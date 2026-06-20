const API = {
    // Same-origin: baseUrl/siteUrl derivam de window.location.origin (produção MVP).
    // Fallback tenhazelo.com.br só se origin indisponível (ex.: contextos não-browser).
    baseUrl: (typeof window !== 'undefined' && window.location && window.location.origin)
        ? `${window.location.origin}/wp-json/zelo/v1`
        : 'https://tenhazelo.com.br/wp-json/zelo/v1',
    siteUrl: (typeof window !== 'undefined' && window.location && window.location.origin)
        ? window.location.origin
        : 'https://tenhazelo.com.br',
    lastSessionError: null,
    lastFetchFromCache: {
        locais: false,
        volunteerOps: false,
        evento: false,
        categorias: false,
        clima: false,
        news: false,
        newsCarousel: false,
        newsDetail: false,
        indoorMap: false
    },

    lastFetchRevalidating: {
        locais: false,
        volunteerOps: false,
        evento: false,
        categorias: false,
        clima: false,
        news: false,
        newsCarousel: false,
        newsDetail: false,
        indoorMap: false
    },

    FETCH_TIMEOUT_MS: 5000,

    readSnapshot(key) {
        try {
            const raw = localStorage.getItem(key);
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (e) {
            console.warn('Snapshot inválido:', key, e);
            return null;
        }
    },

    writeSnapshot(key, data) {
        if (data == null) return;
        try {
            localStorage.setItem(key, JSON.stringify(data));
        } catch (e) {
            console.warn('Falha ao gravar snapshot:', key, e);
        }
    },

    // Cached data for offline support
    cache: {
        locais: null,
        evento: null,
        categorias: null,
        clima: null,
        volunteerOps: null,
        news: null,
        indoorMap: null
    },

    indoorMapSnapshotKey: 'zelo_indoor_map',

    newsSnapshotKey(userId) {
        const uid = userId != null ? String(userId) : '0';
        return `zelo_news_v2_${uid}`;
    },

    newsCarouselSnapshotKey(userId) {
        const uid = userId != null ? String(userId) : '0';
        return `zelo_news_carousel_v1_${uid}`;
    },

    newsItemSnapshotKey(userId, postId) {
        const uid = userId != null ? String(userId) : '0';
        return `zelo_news_item_v1_${uid}_${String(postId)}`;
    },

    clearNewsItemSnapshots(userId) {
        const prefix = `zelo_news_item_v1_${userId != null ? String(userId) : '0'}_`;
        try {
            const keys = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith(prefix)) keys.push(key);
            }
            keys.forEach((key) => localStorage.removeItem(key));
        } catch (e) {
            console.warn('Falha ao limpar snapshots de novidades', e);
        }
    },

    getAuthHeaders() {
        const storedUser = localStorage.getItem('zelo_user');
        if (!storedUser) return {};
        try {
            const user = JSON.parse(storedUser);
            if (user && user.nonce) {
                return { 'X-WP-Nonce': user.nonce };
            }
        } catch (err) {
            console.warn('Falha ao ler usuário para nonce', err);
        }
        return {};
    },

    persistAuthUser(user, nonce) {
        if (!user) return;
        const merged = { ...user, nonce: nonce || user.nonce };
        localStorage.setItem('zelo_user', JSON.stringify(merged));
        return merged;
    },

    /**
     * Valida cookie WP e renova nonce (essencial para PWA em /zelo/).
     * Não envia X-WP-Nonce: o WP devolve 403 (rest_cookie_invalid_nonce) se o nonce
     * no localStorage não corresponder ao cookie de sessão atual.
     */
    async refreshSession() {
        this.lastSessionError = null;
        const url = `${this.baseUrl}/auth/session?_t=${Date.now()}`;
        try {
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'include'
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                this.lastSessionError = {
                    status: response.status,
                    code: data.code || null,
                    message: data.message || null
                };
                return null;
            }
            if (data && data.success && data.user && data.nonce) {
                return this.persistAuthUser(data.user, data.nonce);
            }
        } catch (err) {
            console.warn('Falha ao renovar sessão', err);
            this.lastSessionError = { status: 0, code: 'network_error', message: String(err) };
        }
        return null;
    },

    getSessionErrorMessage() {
        const t = (key) => (typeof i18n !== 'undefined' && i18n.t) ? i18n.t(key) : key;
        const err = this.lastSessionError;
        if (!err) {
            return t('session_error_default');
        }
        if (err.code === 'rest_cookie_invalid_nonce' || err.status === 403) {
            return t('session_error_nonce');
        }
        if (err.status === 401 || err.code === 'zelo_not_logged_in') {
            return t('session_error_cookie');
        }
        if (err.code === 'network_error' || err.status === 0) {
            return t('session_error_network');
        }
        return err.message || t('session_error_default');
    },

    isAnyRevalidating() {
        return Object.values(this.lastFetchRevalidating).some(Boolean);
    },

    hasStaleOrRevalidating() {
        return this.isAnyRevalidating() || Object.values(this.lastFetchFromCache).some(Boolean);
    },

    notifyRevalidation(scope, data) {
        if (typeof window === 'undefined') return;
        window.dispatchEvent(new CustomEvent('zelo:data-revalidated', { detail: { scope, data } }));
    },

    fetchWithTimeout(url, options = {}, timeoutMs) {
        const ms = timeoutMs != null ? timeoutMs : this.FETCH_TIMEOUT_MS;
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), ms);
        return fetch(url, { ...options, signal: controller.signal }).finally(() => clearTimeout(timer));
    },

    async fetchWithStaleFallback(cfg) {
        const {
            url,
            fetchOpts = {},
            snapshotKey,
            staleFlag,
            cacheProp,
            parseResponse,
            persistSnapshot = () => true,
            onFresh,
            emptyFallback = null,
            throwIfNoSnapshot = false,
            authFromResponse
        } = cfg;

        const markFresh = (data) => {
            if (cacheProp) this.cache[cacheProp] = data;
            if (snapshotKey && persistSnapshot(data)) {
                this.writeSnapshot(snapshotKey, data);
            }
            this.lastFetchFromCache[staleFlag] = false;
            this.lastFetchRevalidating[staleFlag] = false;
            if (onFresh) onFresh(data);
            this.notifyRevalidation(staleFlag, data);
            return data;
        };

        const markStale = (cached) => {
            if (cacheProp) this.cache[cacheProp] = cached;
            this.lastFetchFromCache[staleFlag] = true;
            return cached;
        };

        const runFetch = async () => {
            const response = await this.fetchWithTimeout(url, fetchOpts);
            if (authFromResponse) {
                const authResult = authFromResponse(response);
                if (authResult !== undefined) {
                    this.lastFetchRevalidating[staleFlag] = false;
                    return authResult;
                }
            }
            const data = await parseResponse(response);
            return markFresh(data);
        };

        const cached = snapshotKey ? this.readSnapshot(snapshotKey) : null;

        if (cached != null) {
            markStale(cached);
            this.lastFetchRevalidating[staleFlag] = true;
            runFetch().catch((err) => {
                console.warn('Revalidate failed:', staleFlag, err);
                this.lastFetchRevalidating[staleFlag] = false;
            });
            return cached;
        }

        this.lastFetchRevalidating[staleFlag] = true;
        try {
            return await runFetch();
        } catch (err) {
            this.lastFetchRevalidating[staleFlag] = false;
            console.warn('Fetch failed, no snapshot:', staleFlag, err);
            if (throwIfNoSnapshot) throw err;
            return emptyFallback;
        }
    },

    async getLocais(params = {}) {
        params._t = Date.now();
        const query = new URLSearchParams(params).toString();
        const url = `${this.baseUrl}/locais?${query}`;

        return this.fetchWithStaleFallback({
            url,
            snapshotKey: 'zelo_locais',
            staleFlag: 'locais',
            cacheProp: 'locais',
            emptyFallback: null,
            parseResponse: async (response) => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            }
        });
    },

    async getEvento() {
        const url = `${this.baseUrl}/evento?_t=${Date.now()}`;
        return this.fetchWithStaleFallback({
            url,
            snapshotKey: 'zelo_evento',
            staleFlag: 'evento',
            cacheProp: 'evento',
            throwIfNoSnapshot: true,
            parseResponse: async (response) => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            }
        });
    },

    async getCategorias() {
        const url = `${this.baseUrl}/categorias?_t=${Date.now()}`;
        return this.fetchWithStaleFallback({
            url,
            snapshotKey: 'zelo_categorias',
            staleFlag: 'categorias',
            cacheProp: 'categorias',
            emptyFallback: [],
            parseResponse: async (response) => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            }
        });
    },

    async getVolunteerOps(mine = false) {
        const mineParam = mine ? '&mine=1' : '';
        const url = `${this.baseUrl}/ops/voluntarios?_t=${Date.now()}${mineParam}`;
        const cacheKey = mine ? 'zelo_volunteer_ops_mine' : 'zelo_volunteer_ops';

        const result = await this.fetchWithStaleFallback({
            url,
            fetchOpts: {
                headers: this.getAuthHeaders(),
                credentials: 'include'
            },
            snapshotKey: cacheKey,
            staleFlag: 'volunteerOps',
            cacheProp: 'volunteerOps',
            emptyFallback: null,
            authFromResponse: (response) => {
                if (response.status === 401 || response.status === 403) {
                    return { __authError: true, status: response.status };
                }
                return undefined;
            },
            parseResponse: async (response) => {
                if (!response.ok) throw new Error('Ops API unavailable');
                return response.json();
            }
        });

        if (result && this.lastFetchFromCache.volunteerOps) {
            return { ...result, __fromCache: true };
        }
        return result;
    },

    async getClima() {
        const url = `${this.baseUrl}/clima?_t=${Date.now()}`;
        try {
            return await this.fetchWithStaleFallback({
                url,
                snapshotKey: 'zelo_clima',
                staleFlag: 'clima',
                cacheProp: 'clima',
                throwIfNoSnapshot: true,
                persistSnapshot: (data) => !!(data && data.enabled !== false && data.current),
                parseResponse: async (response) => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const err = new Error(data.message || 'Falha ao carregar previsão do tempo');
                        err.code = data.code || null;
                        this.lastClimaError = err;
                        throw err;
                    }
                    this.lastClimaError = null;
                    return data;
                }
            });
        } catch (error) {
            const cached = this.readSnapshot('zelo_clima');
            if (cached) {
                this.cache.clima = cached;
                this.lastFetchFromCache.clima = true;
                return cached;
            }
            throw error;
        }
    },

    async downloadOpsExport(params = {}) {
        const qs = new URLSearchParams({ format: 'pdf', ...params });
        const url = `${this.baseUrl}/ops/export?${qs.toString()}&_t=${Date.now()}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: this.getAuthHeaders(),
            credentials: 'include'
        });
        if (!response.ok) {
            const ct = response.headers.get('content-type') || '';
            let msg = 'Falha ao exportar escala';
            if (ct.indexOf('application/json') !== -1) {
                const data = await response.json().catch(() => ({}));
                msg = data.message || msg;
            }
            throw new Error(msg);
        }
        return response.blob();
    },

    async submitDelegateSupportReport(payload) {
        const url = `${this.baseUrl}/ops/delegate-support-reports`;
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...this.getAuthHeaders() },
            credentials: 'include',
            body: JSON.stringify(payload || {})
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Falha ao enviar registro');
        return data;
    },

    async updateDelegateSupportReport(id, payload) {
        const url = `${this.baseUrl}/ops/delegate-support-reports/${encodeURIComponent(id)}`;
        const response = await fetch(url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', ...this.getAuthHeaders() },
            credentials: 'include',
            body: JSON.stringify(payload || {})
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Falha ao atualizar registro');
        return data;
    },

    async deleteDelegateSupportReport(id) {
        const url = `${this.baseUrl}/ops/delegate-support-reports/${encodeURIComponent(id)}`;
        const response = await fetch(url, {
            method: 'DELETE',
            headers: this.getAuthHeaders(),
            credentials: 'include'
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Falha ao excluir registro');
        return data;
    },

    async getDelegateSupportReports() {
        const url = `${this.baseUrl}/ops/delegate-support-reports?_t=${Date.now()}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: this.getAuthHeaders(),
            credentials: 'include'
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Falha ao carregar registros');
        return data;
    },

    async downloadDelegateSupportExport(format = 'csv') {
        const qs = new URLSearchParams({ format: format || 'csv' });
        const url = `${this.baseUrl}/ops/delegate-support-reports/export?${qs.toString()}&_t=${Date.now()}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: this.getAuthHeaders(),
            credentials: 'include'
        });
        if (!response.ok) {
            const ct = response.headers.get('content-type') || '';
            let msg = 'Falha ao exportar registros';
            if (ct.indexOf('application/json') !== -1) {
                const data = await response.json().catch(() => ({}));
                msg = data.message || msg;
            }
            throw new Error(msg);
        }
        return response.blob();
    },

    async prefetchToSwCache(url) {
        if (!url || typeof caches === 'undefined') return;
        try {
            const u = new URL(url, window.location.href);
            if (u.origin !== window.location.origin) return;
            const res = await fetch(url, { mode: 'cors', credentials: 'include' });
            if (!res.ok) return;
            const cacheName = 'zelo-cache-v' + (typeof window.ZELO_APP_BUILD !== 'undefined' ? window.ZELO_APP_BUILD : '89');
            const cache = await caches.open(cacheName);
            await cache.put(url, res.clone());
        } catch (e) {
            console.warn('Prefetch SW cache failed:', url, e);
        }
    },

    async getIndoorMap(authenticated = false) {
        const url = `${this.baseUrl}/indoor-map?_t=${Date.now()}`;
        const snapKey = this.indoorMapSnapshotKey;
        const fetchOpts = authenticated
            ? { credentials: 'include', headers: this.getAuthHeaders() }
            : { credentials: 'include' };

        const authFromResponse = (r) => {
            if (r.status === 401 || r.status === 403) {
                this.cache.indoorMap = null;
                localStorage.removeItem(snapKey);
                this.lastFetchFromCache.indoorMap = false;
                this.lastFetchRevalidating.indoorMap = false;
                return {};
            }
            return undefined;
        };

        const cached = this.readSnapshot(snapKey);
        const hasUsableCache = cached && cached.image_url;

        const result = await this.fetchWithStaleFallback({
            url,
            fetchOpts,
            snapshotKey: hasUsableCache ? snapKey : null,
            staleFlag: 'indoorMap',
            cacheProp: 'indoorMap',
            emptyFallback: {},
            authFromResponse,
            onFresh: (data) => {
                if (data && data.image_url) {
                    this.prefetchToSwCache(data.image_url);
                }
            },
            parseResponse: async (r) => {
                if (!r.ok) throw new Error('Network response was not ok');
                const data = await r.json();
                return data || {};
            }
        });

        if (result && result.image_url) {
            return result;
        }
        if (hasUsableCache && !result.image_url) {
            return cached;
        }
        return result || {};
    },

    async getOpsLanguages() {
        const url = `${this.baseUrl}/ops/languages`;
        const r = await fetch(url);
        const data = await r.json().catch(() => ({}));
        if (!r.ok) {
            throw new Error(data.message || 'Falha ao carregar idiomas');
        }
        return data.languages || [];
    },

    async patchProfile(payload) {
        const url = `${this.baseUrl}/auth/profile`;
        const r = await fetch(url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', ...this.getAuthHeaders() },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok) {
            throw new Error(data.message || 'Falha ao salvar perfil');
        }
        return data;
    },

    async uploadProfileAvatar(file) {
        const url = `${this.baseUrl}/auth/profile/avatar`;
        const form = new FormData();
        form.append('avatar', file);
        const headers = { ...this.getAuthHeaders() };
        delete headers['Content-Type'];
        const r = await fetch(url, {
            method: 'POST',
            headers,
            credentials: 'include',
            body: form
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok) {
            throw new Error(data.message || 'Falha ao enviar foto');
        }
        return data;
    },

    async registerVolunteer(payload) {
        const url = `${this.baseUrl}/auth/register`;
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok) {
            const msg = data.message || data.code || 'Cadastro falhou';
            throw new Error(msg);
        }
        return data;
    },

    async patchSwapRequest(id, status, extra = {}) {
        const url = `${this.baseUrl}/ops/swap-requests/${encodeURIComponent(id)}`;
        const r = await fetch(url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', ...this.getAuthHeaders() },
            credentials: 'include',
            body: JSON.stringify({ status, ...extra })
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(data.message || 'Falha ao atualizar pedido');
        return data;
    },

    async createSwapRequest(assignmentId, reason) {
        const url = `${this.baseUrl}/ops/swap-requests`;
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...this.getAuthHeaders() },
            credentials: 'include',
            body: JSON.stringify({ assignment_id: assignmentId, reason: reason || '' })
        });
        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(data.message || 'Falha ao criar pedido');
        return data;
    },

    async commitAssignment(assignmentId, status, reason = '', onBehalf = false) {
        const url = `${this.baseUrl}/ops/assignments/${encodeURIComponent(assignmentId)}/commit`;
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...this.getAuthHeaders()
            },
            credentials: 'include',
            body: JSON.stringify({ status, reason, on_behalf: !!onBehalf })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Falha ao confirmar designação');
        return data;
    },

    async checkinVolunteer(assignmentId, onBehalf = false) {
        const url = `${this.baseUrl}/ops/checkin`;
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...this.getAuthHeaders()
            },
            credentials: 'include',
            body: JSON.stringify({ assignment_id: assignmentId, on_behalf: !!onBehalf })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Falha no check-in');
        return data;
    },

    async checkoutVolunteer(assignmentId, onBehalf = false) {
        const url = `${this.baseUrl}/ops/checkout`;
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...this.getAuthHeaders()
            },
            credentials: 'include',
            body: JSON.stringify({ assignment_id: assignmentId, on_behalf: !!onBehalf })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Falha no check-out');
        return data;
    },

    async subscribePush(subscription) {
        const url = `${this.baseUrl}/ops/push/subscribe`;
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...this.getAuthHeaders() },
            credentials: 'include',
            body: JSON.stringify(subscription || {})
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Falha ao activar push');
        return data;
    },

    async unsubscribePush(endpoint) {
        const url = `${this.baseUrl}/ops/push/subscribe`;
        const response = await fetch(url, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', ...this.getAuthHeaders() },
            credentials: 'include',
            body: JSON.stringify(endpoint ? { endpoint } : {})
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Falha ao desactivar push');
        return data;
    },

    async getPushVapidPublic() {
        const url = `${this.baseUrl}/push/vapid-public`;
        const response = await fetch(url, {
            headers: { ...this.getAuthHeaders() },
            credentials: 'include'
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Push indisponível');
        return data;
    },

    async getPushStatus() {
        const url = `${this.baseUrl}/ops/push/status`;
        const response = await fetch(url, {
            headers: { ...this.getAuthHeaders() },
            credentials: 'include'
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Push indisponível');
        return data;
    },

    /** @deprecated use subscribePush */
    async subscribePushStub() {
        return this.subscribePush({});
    },

    async saveScheduleScope(day, shift, rows) {
        const url = `${this.baseUrl}/ops/schedule`;
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                ...this.getAuthHeaders(),
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ day, shift, rows })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const msg = data.message || data.code || 'Falha ao guardar escala';
            throw new Error(typeof msg === 'string' ? msg : 'Falha ao guardar escala');
        }
        if (data.data) {
            this.cache.volunteerOps = data.data;
            this.writeSnapshot('zelo_volunteer_ops', data.data);
        }
        return data;
    },

    async reallocateVolunteer(assignmentId, newLocation, newShift = '') {
        const url = `${this.baseUrl}/ops/reallocate`;
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...this.getAuthHeaders()
            },
            credentials: 'include',
            body: JSON.stringify({
                assignment_id: assignmentId,
                new_location: newLocation,
                new_shift: newShift
            })
        });
        if (!response.ok) throw new Error('Falha na realocação');
        return response.json();
    },

    async getNews(params = {}, userId = null) {
        const sp = new URLSearchParams();
        sp.set('_t', String(Date.now()));
        if (params.page) sp.set('page', String(params.page));
        if (params.per_page) sp.set('per_page', String(params.per_page));
        if (params.notifications_only) sp.set('notifications_only', '1');
        if (params.carousel_only) sp.set('carousel_only', '1');
        const url = `${this.baseUrl}/news?${sp.toString()}`;
        const snapKey = params.carousel_only
            ? this.newsCarouselSnapshotKey(userId)
            : this.newsSnapshotKey(userId);

        try {
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'include',
                headers: this.getAuthHeaders()
            });
            if (response.status === 401 || response.status === 403) {
                if (params.carousel_only) {
                    this.lastFetchFromCache.newsCarousel = false;
                } else {
                    this.cache.news = null;
                    this.lastFetchFromCache.news = false;
                }
                return null;
            }
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();
            if (params.carousel_only) {
                this.lastFetchFromCache.newsCarousel = false;
            } else {
                this.cache.news = data;
                this.lastFetchFromCache.news = false;
            }
            this.writeSnapshot(snapKey, data);
            if (!params.carousel_only && userId != null && Array.isArray(data.items) && data.items.length) {
                this.prefetchNewsItemDetails(data.items, userId);
            }
            return data;
        } catch (error) {
            console.warn('Fetch news falhou, tentando cache', error);
            const cached = this.readSnapshot(snapKey);
            if (cached) {
                if (params.carousel_only) {
                    this.lastFetchFromCache.newsCarousel = true;
                } else {
                    this.cache.news = cached;
                    this.lastFetchFromCache.news = true;
                }
                return cached;
            }
            if (params.carousel_only) {
                this.lastFetchFromCache.newsCarousel = false;
            }
            return null;
        }
    },

    async prefetchNewsItemDetails(items, userId) {
        if (!Array.isArray(items) || !items.length) return;
        const slice = items.slice(0, 20);
        for (const item of slice) {
            if (!item || item.id == null) continue;
            try {
                await this.getNewsItem(item.id, userId);
            } catch (e) {
                /* prefetch best-effort */
            }
        }
    },

    async getNewsItem(id, userId = null) {
        const postId = String(id);
        const snapKey = this.newsItemSnapshotKey(userId, postId);
        const url = `${this.baseUrl}/news/${encodeURIComponent(postId)}?_t=${Date.now()}`;

        try {
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'include',
                headers: this.getAuthHeaders()
            });
            const data = await response.json().catch(() => ({}));
            if (response.status === 401 || response.status === 403) {
                this.lastFetchFromCache.newsDetail = false;
                return null;
            }
            if (!response.ok) {
                throw new Error(data.message || 'Novidade não encontrada');
            }
            this.writeSnapshot(snapKey, data);
            this.lastFetchFromCache.newsDetail = false;
            if (data.featured_image) {
                await this.prefetchToSwCache(data.featured_image);
            }
            return data;
        } catch (error) {
            console.warn('Fetch news item falhou, tentando cache', error);
            const cached = this.readSnapshot(snapKey);
            if (cached) {
                this.lastFetchFromCache.newsDetail = true;
                return cached;
            }
            this.lastFetchFromCache.newsDetail = false;
            const err = new Error('news_offline_unavailable');
            err.code = 'news_offline_unavailable';
            throw err;
        }
    },

    clearOpsRelatedSnapshots(userId = null) {
        localStorage.removeItem('zelo_volunteer_ops');
        localStorage.removeItem('zelo_volunteer_ops_mine');
        localStorage.removeItem(this.indoorMapSnapshotKey);
        if (userId != null) {
            localStorage.removeItem(this.newsSnapshotKey(userId));
            localStorage.removeItem(this.newsCarouselSnapshotKey(userId));
            this.clearNewsItemSnapshots(userId);
        }
        this.cache.volunteerOps = null;
        this.cache.indoorMap = null;
        this.cache.news = null;
        this.lastFetchFromCache.indoorMap = false;
        this.lastFetchFromCache.news = false;
        this.lastFetchFromCache.newsCarousel = false;
    },

    async getVolunteerApprovals() {
        const url = `${this.baseUrl}/ops/volunteer-approvals?_t=${Date.now()}`;
        const response = await fetch(url, {
            headers: { ...this.getAuthHeaders() },
            credentials: 'include'
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || data.code || 'Falha ao carregar cadastros pendentes');
        }
        return data;
    },

    async approveVolunteerRegistration(userId) {
        const url = `${this.baseUrl}/ops/volunteer-approvals/${encodeURIComponent(userId)}/approve`;
        const response = await fetch(url, {
            method: 'POST',
            headers: { ...this.getAuthHeaders() },
            credentials: 'include'
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || data.code || 'Falha ao aprovar cadastro');
        }
        return data;
    },

    async rejectVolunteerRegistration(userId) {
        const url = `${this.baseUrl}/ops/volunteer-approvals/${encodeURIComponent(userId)}/reject`;
        const response = await fetch(url, {
            method: 'POST',
            headers: { ...this.getAuthHeaders() },
            credentials: 'include'
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || data.code || 'Falha ao reprovar cadastro');
        }
        return data;
    }
};
