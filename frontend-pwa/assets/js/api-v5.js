const API = {
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
        clima: false
    },

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
        volunteerOps: null
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

    async getLocais(params = {}) {
        // Add timestamp to prevent caching
        params._t = Date.now();
        const query = new URLSearchParams(params).toString();
        const url = `${this.baseUrl}/locais?${query}`;

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();

            // Update cache
            if ('caches' in self) {
                // We cache the CLEAN url (without timestamp) so offline works with a "latest known" version if strict matching isn't used, 
                // OR we accept that offline might use the last cached timestamped URL if Strategy allows.
                // Better: Cache the data logic in SW handles this. 
                // However, for explicit cache busting:
            }
            this.cache.locais = data;
            this.writeSnapshot('zelo_locais', data);
            this.lastFetchFromCache.locais = false;
            return data;
        } catch (error) {
            console.warn('Network failed, trying cache for locais', error);
            const cached = this.readSnapshot('zelo_locais');
            if (cached) {
                this.cache.locais = cached;
                this.lastFetchFromCache.locais = true;
                return cached;
            }
            return null;
        }
    },

    async getEvento() {
        const url = `${this.baseUrl}/evento?_t=${Date.now()}`;
        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();

            this.cache.evento = data;
            this.writeSnapshot('zelo_evento', data);
            this.lastFetchFromCache.evento = false;
            return data;
        } catch (error) {
            console.warn('Fetch failed, trying cache', error);
            const cached = this.readSnapshot('zelo_evento');
            if (cached) {
                this.cache.evento = cached;
                this.lastFetchFromCache.evento = true;
                return cached;
            }
            throw error;
        }
    },

    async getCategorias() {
        const url = `${this.baseUrl}/categorias?_t=${Date.now()}`;
        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();

            this.cache.categorias = data;
            this.writeSnapshot('zelo_categorias', data);
            this.lastFetchFromCache.categorias = false;
            return data;
        } catch (error) {
            console.warn('Fetch categorias falhou, tentando cache', error);
            const cached = this.readSnapshot('zelo_categorias');
            if (cached) {
                this.cache.categorias = cached;
                this.lastFetchFromCache.categorias = true;
                return cached;
            }
            return [];
        }
    },

    async getVolunteerOps(mine = false) {
        const mineParam = mine ? '&mine=1' : '';
        const url = `${this.baseUrl}/ops/voluntarios?_t=${Date.now()}${mineParam}`;
        try {
            const response = await fetch(url, {
                headers: this.getAuthHeaders(),
                credentials: 'include'
            });
            if (response.status === 401 || response.status === 403) {
                return { __authError: true, status: response.status };
            }
            if (!response.ok) throw new Error('Ops API unavailable');
            const data = await response.json();
            this.cache.volunteerOps = data;
            const cacheKey = mine ? 'zelo_volunteer_ops_mine' : 'zelo_volunteer_ops';
            this.writeSnapshot(cacheKey, data);
            this.lastFetchFromCache.volunteerOps = false;
            return data;
        } catch (error) {
            if (!error || !error.__authError) {
                console.warn('Falha ao carregar operação de voluntários', error);
            }
            const cacheKey = mine ? 'zelo_volunteer_ops_mine' : 'zelo_volunteer_ops';
            const cached = this.readSnapshot(cacheKey);
            if (cached) {
                this.cache.volunteerOps = cached;
                this.lastFetchFromCache.volunteerOps = true;
                return { ...cached, __fromCache: true };
            }
            return null;
        }
    },

    async getClima() {
        const url = `${this.baseUrl}/clima?_t=${Date.now()}`;
        try {
            const response = await fetch(url);
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const err = new Error(data.message || 'Falha ao carregar previsão do tempo');
                err.code = data.code || null;
                this.lastClimaError = err;
                throw err;
            }
            this.lastClimaError = null;
            this.cache.clima = data;
            if (data && data.enabled !== false && data.current) {
                this.writeSnapshot('zelo_clima', data);
            }
            this.lastFetchFromCache.clima = false;
            return data;
        } catch (error) {
            console.warn('Fetch clima falhou, tentando cache', error);
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

    async getIndoorMap() {
        const url = `${this.baseUrl}/indoor-map?_t=${Date.now()}`;
        try {
            const r = await fetch(url);
            if (!r.ok) return {};
            return await r.json();
        } catch (e) {
            return {};
        }
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

    async subscribePushStub() {
        const url = `${this.baseUrl}/ops/push/subscribe`;
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...this.getAuthHeaders() },
            credentials: 'include',
            body: JSON.stringify({})
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Push indisponível');
        return data;
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
    }
};
