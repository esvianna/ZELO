const API = {
    baseUrl: (typeof window !== 'undefined' && window.location && window.location.origin)
        ? `${window.location.origin}/wp-json/zelo/v1`
        : 'https://tenhazelo.com.br/wp-json/zelo/v1',
    siteUrl: (typeof window !== 'undefined' && window.location && window.location.origin)
        ? window.location.origin
        : 'https://tenhazelo.com.br',
    lastSessionError: null,

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
        const err = this.lastSessionError;
        if (!err) {
            return 'Não foi possível validar a sessão. Tente entrar novamente.';
        }
        if (err.code === 'rest_cookie_invalid_nonce' || err.status === 403) {
            return 'Sessão desatualizada no navegador (nonce inválido). Saia, limpe os dados do site para tenhazelo.com.br e entre de novo, ou use uma aba anónima.';
        }
        if (err.status === 401 || err.code === 'zelo_not_logged_in') {
            return 'O servidor não recebeu o cookie de login. Use https://tenhazelo.com.br/zelo/ (mesmo domínio do WordPress), confirme que o plugin Zelo 2.5.3+ está ativo e tente novamente.';
        }
        if (err.code === 'network_error' || err.status === 0) {
            return 'Falha de rede ao validar a sessão. Verifique a conexão e tente novamente.';
        }
        return err.message || 'Não foi possível validar a sessão. Tente entrar novamente.';
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
            return data;
        } catch (error) {
            console.warn('Network failed, trying cache for locais');
            return null; // Let app handle fallback or use internal cache
        }
    },

    async getEvento() {
        const url = `${this.baseUrl}/evento?_t=${Date.now()}`;
        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();

            this.cache.evento = data;
            localStorage.setItem('zelo_evento', JSON.stringify(data));

            return data;
        } catch (error) {
            console.warn('Fetch failed, trying cache', error);
            const cached = localStorage.getItem('zelo_evento');
            if (cached) {
                return JSON.parse(cached);
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
            localStorage.setItem('zelo_categorias', JSON.stringify(data));

            return data;
        } catch (error) {
            console.warn('Fetch categorias falhou, tentando cache', error);
            const cached = localStorage.getItem('zelo_categorias');
            if (cached) {
                return JSON.parse(cached);
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
            return data;
        } catch (error) {
            if (!error || !error.__authError) {
                console.warn('Falha ao carregar operação de voluntários', error);
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
                localStorage.setItem('zelo_clima', JSON.stringify(data));
            }
            return data;
        } catch (error) {
            console.warn('Fetch clima falhou, tentando cache', error);
            const cached = localStorage.getItem('zelo_clima');
            if (cached) {
                const parsed = JSON.parse(cached);
                this.cache.clima = parsed;
                return parsed;
            }
            throw error;
        }
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

    async checkinVolunteer(assignmentId) {
        const url = `${this.baseUrl}/ops/checkin`;
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...this.getAuthHeaders()
            },
            credentials: 'include',
            body: JSON.stringify({ assignment_id: assignmentId })
        });
        if (!response.ok) throw new Error('Falha no check-in');
        return response.json();
    },

    async checkoutVolunteer(assignmentId) {
        const url = `${this.baseUrl}/ops/checkout`;
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                ...this.getAuthHeaders()
            },
            credentials: 'include',
            body: JSON.stringify({ assignment_id: assignmentId })
        });
        if (!response.ok) throw new Error('Falha no check-out');
        return response.json();
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
