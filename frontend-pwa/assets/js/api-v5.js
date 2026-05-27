const API = {
    baseUrl: 'https://tenhazelo.com.br/wp-json/zelo/v1',
    siteUrl: 'https://tenhazelo.com.br', // Base WP URL for links

    // Cached data for offline support
    cache: {
        locais: null,
        evento: null,
        categorias: null,
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
                credentials: 'same-origin'
            });
            if (response.status === 401 || response.status === 403) {
                const err = new Error('Ops API auth required');
                err.status = response.status;
                throw err;
            }
            if (!response.ok) throw new Error('Ops API unavailable');
            const data = await response.json();
            this.cache.volunteerOps = data;
            return data;
        } catch (error) {
            console.warn('Falha ao carregar operação de voluntários', error);
            return null;
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
            credentials: 'same-origin',
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
            credentials: 'same-origin',
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
            credentials: 'same-origin',
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
            credentials: 'same-origin',
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
            credentials: 'same-origin',
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
