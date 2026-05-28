const app = {
    data: {
        locais: [],
        evento: null,
        clima: null,
        categoriesMeta: {},
        volunteerOps: null,
        indoorMap: null,
        currentCategory: null,
        // Pagination & Filter State
        listPage: 1,
        listSearch: '',
        listSort: 'distance', // 'distance' or 'alpha'
        listBairro: '',
        listCidade: '',
        listOpenNow: false,
        itemsPerPage: 10,
        userLocation: null, // {lat, lng}
        installPrompt: null // Store PWA install prompt
    },

    // --- Helpers ---
    calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Radius of the earth in km
        const dLat = this.deg2rad(lat2 - lat1);
        const dLon = this.deg2rad(lon2 - lon1);
        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(this.deg2rad(lat1)) * Math.cos(this.deg2rad(lat2)) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        const d = R * c; // Distance in km
        return d;
    },

    deg2rad(deg) {
        return deg * (Math.PI / 180);
    },

    normalizeHexColor(color, fallback = '#3B82F6') {
        if (typeof color !== 'string') return fallback;
        const value = color.trim();
        return /^#[0-9A-Fa-f]{6}$/.test(value) ? value.toUpperCase() : fallback;
    },

    hexToRgba(hex, alpha = 0.15) {
        const safeHex = this.normalizeHexColor(hex);
        const r = parseInt(safeHex.slice(1, 3), 16);
        const g = parseInt(safeHex.slice(3, 5), 16);
        const b = parseInt(safeHex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    },

    buildCategoryMeta(categories) {
        const fallback = {
            label: i18n.t('places'),
            color: '#3B82F6',
            icon: '\u{1F4CD}'
        };
        const iconBySlug = {
            hospital: '\u{1F3E5}',
            farmacia: '\u{1F48A}',
            emergencia: '\u{1F691}',
            cultura: '\u{1F3DB}\uFE0F',
            compras: '\u{1F6CD}\uFE0F',
            lazer: '\u{1F333}'
        };

        const map = {};
        (categories || []).forEach(item => {
            if (!item || !item.slug) return;
            map[item.slug] = {
                label: item.label || item.slug,
                color: this.normalizeHexColor(item.color, fallback.color),
                icon: iconBySlug[item.slug] || fallback.icon
            };
        });
        map.default = fallback;
        return map;
    },

    getCategoryMeta(slug) {
        const base = this.data.categoriesMeta[slug] || this.data.categoriesMeta.default || {
            label: i18n.t('places'),
            color: '#3B82F6',
            icon: '\u{1F4CD}'
        };
        return {
            icon: base.icon,
            label: base.label,
            color: base.color,
            bg: this.hexToRgba(base.color, 0.15),
            gradient: `linear-gradient(135deg, ${this.hexToRgba(base.color, 0.95)}, ${base.color})`
        };
    },

    getUserLocation() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(
            (position) => {
                this.data.userLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                console.log('User location obtained:', this.data.userLocation);
                // Re-render if current view is list and sorted by distance
                if (this.router.currentView === 'lista' && this.data.listSort === 'distance') {
                    this.renderList(this.data.currentCategory);
                }
            },
            (error) => {
                console.warn('Geolocation error:', error);
            }
        );
    },

    router: {
        currentView: 'home',

        navigate(viewId, params = {}) {
            // Hide current view
            document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));

            // Update Bottom Nav Active State
            document.querySelectorAll('.bottom-nav .nav-item').forEach(el => {
                el.classList.remove('active');
                if (el.dataset.target === viewId) {
                    el.classList.add('active');
                }
            });

            // Show new view
            const view = document.getElementById(`view-${viewId}`);
            if (view) {
                view.classList.add('active');
                this.currentView = viewId;

                // Trigger view specific logic
                if (viewId === 'mapa') {
                    setTimeout(() => {
                        MapManager.init('map-container');
                        MapManager.map.invalidateSize(); // Fix leafleft rendering issue in hidden divs
                        MapManager.setCategoryMeta(app.data.categoriesMeta);
                        MapManager.addMarkers(app.data.locais);
                    }, 100);
                } else if (viewId === 'lista') {
                    app.renderList(params.category);
                } else if (viewId === 'detalhe') {
                    app.renderDetail(params.id);
                } else if (viewId === 'emergencia') {
                    app.renderEmergency();
                } else if (viewId === 'evento') {
                    app.renderEventInfo();
                } else if (viewId === 'tempo') {
                    app.renderWeather();
                } else if (viewId === 'escala') {
                    app.renderVolunteerOps();
                } else if (viewId === 'mapa-evento') {
                    app.renderIndoorEventMap();
                } else if (viewId === 'register') {
                    /* static form */
                }

                // Render Home components if on home
                if (viewId === 'home') {
                    app.renderHomeNotice();
                    app.renderEventBanner();
                    app.renderHomeMap();
                    app.renderHomeVolunteerDashboard();
                    app.toggleHomeVisitorExtrasCollapse();
                }
            }
        },



        // Moved to app root


        debounceTimer: null,
        debounceSearch(query, category) {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                // When searching, reset to page 1
                app.renderList(category, 1, query);

                // Focus management hack: refocs input after render
                setTimeout(() => {
                    const input = document.querySelector('.search-input');
                    if (input) {
                        input.focus();
                        input.setSelectionRange(input.value.length, input.value.length);
                    }
                }, 50);
            }, 300);
        },

        back() {
            this.navigate('home');
        }
    },

    auth: {
        user: null, // {id, name, email, avatar, roles, token}

        init() {
            const storedUser = localStorage.getItem('zelo_user');
            if (storedUser) {
                this.user = JSON.parse(storedUser);
                console.log('User restored:', this.user);
            }
            this.refreshAuthChrome();
        },

        handleOpsAuthFailure() {
            app.data.volunteerOps = null;
            app._opsAuthFailed = true;
            if (!app._opsAuthFailureLogged) {
                console.info('Sessão WordPress expirada ou nonce inválido — faça login novamente para ver a escala.');
                app._opsAuthFailureLogged = true;
            }
        },

        clearOpsAuthFailure() {
            app._opsAuthFailed = false;
            app._opsAuthFailureLogged = false;
        },

        async login(event) {
            event.preventDefault();

            const username = document.getElementById('login-username').value;
            const password = document.getElementById('login-password').value;
            const errorEl = document.getElementById('login-error');
            const submitBtn = document.getElementById('login-submit-btn');

            // Reset UI
            errorEl.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.textContent = i18n.t('loading');

            try {
                // Determine API URL (assuming relative or hardcoded for now based on env)
                // Since this is PWA, we need the WP Backend URL. 
                // Assumption: PWA is hosted on same domain or we have the URL. 
                // For this task, I'll assume relative path '/wp-json/zelo/v1/auth/login' works if hosted on WP,
                // *CRITICAL*: User mentioned "zelo-assistente" plugin. 
                // Let's try to infer URL. If index.html is in a folder, we might need absolute.
                // Let's use specific relative path if on same domain, or absolute if defined.
                // I will use a config var for API base.

                // Determine API URL
                // API.baseUrl typically ends with /zelo/v1. Check to avoid duplication.
                let url;
                if (API.baseUrl && API.baseUrl.includes('/zelo/v1')) {
                    url = `${API.baseUrl}/auth/login`;
                } else {
                    const apiRoot = 'https://tenhazelo.com.br/wp-json';
                    url = `${apiRoot}/zelo/v1/auth/login`;
                }

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        username: username,
                        password: password // *Note*: sending password plain over HTTPS is standard for this simple auth
                    })
                });

                const data = await response.json();

                if (!response.ok) {
                    const msg = data.message || data.code || 'Erro ao fazer login';
                    throw new Error(msg);
                }

                if (data.success) {
                    API.persistAuthUser(data.user, data.nonce);
                    this.user = JSON.parse(localStorage.getItem('zelo_user'));

                    const synced = await API.refreshSession();
                    if (synced) {
                        this.user = synced;
                    }

                    this.updateUI();

                    if (this.user.caps && this.user.caps.view_ops) {
                        app.data.volunteerOps = await app.loadVolunteerOps(true);
                        if (app._opsAuthFailed) {
                            const msg = synced
                                ? 'Login aceito, mas a escala operacional não pôde ser carregada (sessão ou permissão). Saia e entre novamente.'
                                : API.getSessionErrorMessage();
                            throw new Error(msg);
                        }
                        this.clearOpsAuthFailure();
                    } else {
                        app.data.volunteerOps = null;
                    }

                    app.router.navigate('home');

                    // Clear form
                    document.getElementById('login-username').value = '';
                    document.getElementById('login-password').value = '';

                } else {
                    throw new Error(data.message || 'Erro ao fazer login');
                }

            } catch (err) {
                console.error('Login error:', err);
                errorEl.textContent = err.message || 'Erro de conexão. Tente novamente.';
                errorEl.style.display = 'block';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = i18n.t('login_btn');
            }
        },

        async register(event) {
            event.preventDefault();
            const errEl = document.getElementById('register-error');
            const btn = document.getElementById('register-submit-btn');
            const name = document.getElementById('register-name').value.trim();
            const email = document.getElementById('register-email').value.trim();
            const phone = document.getElementById('register-phone').value.trim();
            const pass = document.getElementById('register-password').value;
            errEl.style.display = 'none';
            btn.disabled = true;
            btn.textContent = i18n.t('loading');
            try {
                await API.registerVolunteer({
                    display_name: name,
                    email,
                    phone,
                    password: pass
                });
                errEl.className = 'text-success';
                errEl.style.display = 'block';
                errEl.textContent = 'Cadastro criado. Verifique seu e-mail para o link de confirmação.';
                document.getElementById('register-password').value = '';
            } catch (e) {
                errEl.className = 'text-danger';
                errEl.textContent = e.message || 'Erro';
                errEl.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Criar conta';
            }
        },

        logout() {
            this.user = null;
            localStorage.removeItem('zelo_user');
            app.data.volunteerOps = null;
            this.clearOpsAuthFailure();
            this.refreshAuthChrome();
            app.router.navigate('home');
        },

        handleIconClick() {
            if (this.user) {
                app.router.navigate('profile');
            } else {
                app.router.navigate('login');
            }
        },

        refreshAuthChrome() {
            const iconContainer = document.getElementById('user-auth-indicator');

            if (this.user && iconContainer) {
                const avatarUrl = this.user.avatar || 'images/default-avatar.png';
                iconContainer.innerHTML = `<img src="${avatarUrl}" alt="">`;
                iconContainer.setAttribute('title', this.user.name || i18n.t('my_profile'));
            } else if (iconContainer) {
                iconContainer.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>`;
                iconContainer.setAttribute('title', 'Entrar');
            }

            const pName = document.getElementById('profile-name');
            const pEmail = document.getElementById('profile-email');
            const pRole = document.getElementById('profile-role');
            const pAvatar = document.getElementById('profile-avatar');

            if (this.user) {
                const avatarUrl = this.user.avatar || 'images/default-avatar.png';
                if (pName) pName.textContent = this.user.name;
                if (pEmail) pEmail.textContent = this.user.email;
                if (pRole) pRole.textContent = app.getOpsRoleLabel() || this.user.roles[0] || i18n.t('visitor_role');
                if (pAvatar) pAvatar.src = avatarUrl;
            }

            app.updateBottomNavForVolunteer();
        },

        updateUI() {
            this.refreshAuthChrome();
        },

        async forceUpdate() {
            if (!confirm('Isso irá recarregar o aplicativo e baixar a versão mais recente. Deseja continuar?')) return;

            console.log('Forcing update...');

            // 1. Unregister SW
            if ('serviceWorker' in navigator) {
                const registrations = await navigator.serviceWorker.getRegistrations();
                for (const registration of registrations) {
                    await registration.unregister();
                }
            }

            // 2. Clear Caches
            if ('caches' in self) {
                const keys = await caches.keys();
                for (const key of keys) {
                    await caches.delete(key);
                }
            }

            // 3. Clear Local Storage (Optional, maybe keep user login?)
            // localStorage.removeItem('zelo_user'); // Keep user logged in if possible
            // localStorage.removeItem('zelo_locais'); // Clear data cache

            // 4. Reload
            window.location.reload(true);
        }
    },


    async init() {
        console.log('Zelo App Initializing...');

        const buildEl = document.getElementById('app-build-version');
        if (buildEl && typeof window.ZELO_APP_BUILD !== 'undefined') {
            buildEl.textContent = 'v' + String(window.ZELO_APP_BUILD);
        }

        // Mock data loading or real API
        try {
            // Init Auth
            this.auth.init();

            // Load initial data
            const [locais, evento, categorias, clima] = await Promise.all([
                API.getLocais(), // Fetch all initially
                API.getEvento(),
                API.getCategorias(),
                API.getClima().catch(() => null)
            ]);

            this.data.locais = locais || [];
            this.data.evento = evento || {};
            this.data.clima = clima || null;
            this.data.categoriesMeta = this.buildCategoryMeta(categorias || []);

            if (this.auth.user && this.auth.user.caps && this.auth.user.caps.view_ops) {
                const synced = await API.refreshSession();
                if (synced) {
                    this.auth.user = synced;
                    // force=true: após refreshSession ok, ignorar _opsAuthFailed de tentativa anterior
                    this.data.volunteerOps = await this.loadVolunteerOps(true);
                    if (!this._opsAuthFailed) {
                        this.auth.clearOpsAuthFailure();
                    }
                } else {
                    this.auth.handleOpsAuthFailure();
                }
            }

            console.log('Data loaded', this.data);

            this.auth.refreshAuthChrome();

            this.renderEventBanner();
            this.renderHomeMap();
            this.renderHomeVolunteerDashboard();
            this.toggleHomeVisitorExtrasCollapse();

        } catch (err) {
            console.error('Failed to load data', err);
            // Show offline message if needed
        }

        const verifyParams = new URLSearchParams(window.location.search);
        if (verifyParams.get('zelo_verified') === '1') {
            verifyParams.delete('zelo_verified');
            const qs = verifyParams.toString();
            const cleanUrl = window.location.pathname + (qs ? `?${qs}` : '') + window.location.hash;
            history.replaceState({}, '', cleanUrl);
            this.router.navigate('email-verified');
        } else {
            this.router.navigate('home');
        }

        // Request location
        this.getUserLocation();

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && this.router.currentView === 'tempo' && navigator.onLine) {
                if (this.shouldRefreshWeather()) {
                    this.refreshWeather();
                }
            }
        });
    },

    // --- Render Methods ---
    renderHomeNotice() {
        const container = document.getElementById('home-notice-container');
        // The API structure nests this under info_uteis -> home_notice
        const noticeData = app.data.evento?.info_uteis?.home_notice;

        if (!container) return;

        if (!noticeData) {
            container.innerHTML = '';
            return;
        }

        // Check if active (API returns boolean true/false or 1/0)
        // Ensure truthy check handles string '1' or int 1 or boolean true
        const isActive = noticeData.active == 1 || noticeData.active === true || noticeData.active === 'true';

        if (!isActive) {
            container.innerHTML = '';
            return;
        }

        const type = noticeData.type || 'info'; // info, warning, critical
        const text = noticeData.text || 'Aviso do Evento';
        const link = noticeData.link || '#';

        // Icon mapping
        let icon = '📢'; // Default
        if (type === 'warning') icon = '⚠️';
        if (type === 'critical') icon = '🚨';

        const html = `
            <a href="${link}" class="home-notice-card" onclick="${link === '#' ? 'return false;' : ''}">
                <div class="home-notice-icon ${type}">
                    ${icon}
                </div>
                <div class="home-notice-content">
                    <div class="home-notice-title">${i18n.t('emergency_title')}</div>
                    <div class="home-notice-text">${text}</div>
                </div>
                <div class="home-notice-arrow">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </div>
            </a>
        `;

        container.innerHTML = html;
    },

    renderEventBanner() {
        const container = document.getElementById('home-event-banner');
        if (!container) return;

        const evt = this.data.evento;
        if (!evt || !evt.name_evento) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'block';
        const heroImage = evt.foto || '';
        const bgStyle = heroImage ? `background-image: url('${heroImage}');` : '';
        const location = evt.local || evt.endereco || '';

        container.innerHTML = `
            <div class="event-banner-inner" style="${bgStyle}">
                <div class="event-banner-content">
                    <div class="event-banner-tag">${i18n.t('event_tag')}</div>
                    <div class="event-banner-title">${evt.name_evento}</div>
                    <div class="event-banner-footer">
                        <span class="event-banner-subtitle">${location}</span>
                        <span class="event-banner-btn">${i18n.t('view_details')}</span>
                    </div>
                </div>
            </div>
        `;
    },

    renderHomeMap() {
        const mapEl = document.getElementById('home-map-preview');
        const addressEl = document.getElementById('home-event-location-text');
        if (!mapEl) return;

        const evt = this.data.evento;
        const coords = evt?.coordenadas || { lat: evt?.lat, lng: evt?.lng };

        // Update address text
        if (addressEl) {
            addressEl.textContent = evt?.endereco || evt?.local || i18n.t('view_map');
        }

        // Skip if no coordinates or map already initialized
        if (!coords.lat || !coords.lng) return;
        if (this._homeMiniMap || mapEl._leaflet_id) return;

        if (this._homeMapInitTimer) {
            clearTimeout(this._homeMapInitTimer);
        }

        this._homeMapInitTimer = setTimeout(() => {
            this._homeMapInitTimer = null;
            const el = document.getElementById('home-map-preview');
            if (!el || el._leaflet_id || this._homeMiniMap) return;

            const miniMap = L.map('home-map-preview', {
                center: [coords.lat, coords.lng],
                zoom: 14,
                zoomControl: false,
                dragging: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                boxZoom: false,
                keyboard: false,
                attributionControl: false
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: ''
            }).addTo(miniMap);

            // Add event marker
            if (evt.foto) {
                const icon = L.divIcon({
                    html: `<img src="${evt.foto}" class="event-marker-logo" style="width:36px;height:36px;">`,
                    iconSize: [36, 36],
                    className: ''
                });
                L.marker([coords.lat, coords.lng], { icon }).addTo(miniMap);
            } else {
                L.marker([coords.lat, coords.lng]).addTo(miniMap);
            }

            this._homeMiniMap = miniMap;
            setTimeout(() => miniMap.invalidateSize(), 200);
        }, 300);
    },

    handleHomeSearch(query) {
        const resultsEl = document.getElementById('home-search-results');
        if (!resultsEl) return;

        if (!query || query.length < 2) {
            resultsEl.style.display = 'none';
            resultsEl.innerHTML = '';
            return;
        }

        const term = query.toLowerCase();
        const matches = this.data.locais.filter(i =>
            i.name.toLowerCase().includes(term) ||
            (i.address && i.address.toLowerCase().includes(term))
        ).slice(0, 8);

        if (matches.length === 0) {
            resultsEl.style.display = 'block';
            resultsEl.innerHTML = `<div style="padding:1rem;text-align:center;color:#999;">${i18n.t('no_places_found')}</div>`;
            return;
        }

        resultsEl.style.display = 'block';
        resultsEl.innerHTML = matches.map(item => {
            const cat = this.getCategoryMeta(item.category);
            return `
                <div class="home-search-result-item" onclick="app.handleSearchResultClick(${item.id})">
                    <div class="home-search-result-icon" style="background:${cat.bg}">${cat.icon}</div>
                    <div class="home-search-result-info">
                        <h4>${item.name}</h4>
                        <p>${item.address || ''}</p>
                    </div>
                </div>
            `;
        }).join('');
    },

    handleSearchResultClick(id) {
        // Close results
        const resultsEl = document.getElementById('home-search-results');
        const inputEl = document.getElementById('home-search-input');
        if (resultsEl) resultsEl.style.display = 'none';
        if (inputEl) inputEl.value = '';

        // Navigate
        this.router.navigate('detalhe', { id: id });
    },

    renderList(category, page = 1, search = '', sort = null, bairro = null, cidade = null, openNow = null) {
        const container = document.getElementById('list-container');
        const title = document.getElementById('list-title');

        const selectedCategoryMeta = this.getCategoryMeta(category);
        title.textContent = category ? selectedCategoryMeta.label : i18n.t('places');


        // Update State (if params provided)
        if (category !== this.data.currentCategory) {
            this.data.currentCategory = category;
            this.data.listPage = 1;
            this.data.listSearch = '';
            this.data.listSort = 'distance';
            this.data.listBairro = '';
            this.data.listCidade = '';
            this.data.listOpenNow = false;
        } else {
            this.data.listPage = page;
            if (search !== null) this.data.listSearch = search;
            if (sort !== null) this.data.listSort = sort;
            if (bairro !== null) this.data.listBairro = bairro;
            if (cidade !== null) this.data.listCidade = cidade;
            if (openNow !== null) this.data.listOpenNow = openNow;
        }

        let items = this.data.locais;

        // 0. Pre-process items to extract metadata (Bairro, Cidade) if not present
        const cleanFilterValue = (val) => {
            if (!val) return null;
            const cleaned = val.trim();
            // Discard if pure numeric (likely house number or part of CEP)
            if (/^\d+$/.test(cleaned.replace(/[\s.-]/g, ''))) return null;
            // Discard if too short or likely junk
            if (cleaned.length < 3) return null;
            if (['PR', 'Brasil', 'Brazil', 'Brasil', 'SP', 'SC', 'RJ'].includes(cleaned)) return null;
            return cleaned;
        };

        items.forEach(item => {
            if (!item._bairro || !item._cidade) {
                const parts = item.address ? item.address.split(' - ') : [];
                
                // Stable logic: Part 1 is usually "Bairro, Cidade"
                // Extract from parts[1], if invalid try parts[2]
                let target = parts[1] || '';
                if (!cleanFilterValue(target.split(',')[0]) && parts[2]) {
                    target = parts[2];
                }

                const fragments = target.split(',');
                if (fragments.length >= 2) {
                    item._bairro = cleanFilterValue(fragments[0]);
                    item._cidade = cleanFilterValue(fragments[1]);
                } else {
                    const val = cleanFilterValue(fragments[0]);
                    if (val) item._cidade = val;
                }
            }
        });

        // 1. Extract Unique Options for Filters (based on current category items)
        const categoryItems = category ? items.filter(i => i.category === category) : items;
        const bairros = [...new Set(categoryItems.map(i => i._bairro).filter(Boolean))].sort();
        const cidades = [...new Set(categoryItems.map(i => i._cidade).filter(Boolean))].sort();

        // 2. Filter Pipeline
        let filtered = categoryItems;

        // Search
        if (this.data.listSearch) {
            const term = this.data.listSearch.toLowerCase();
            filtered = filtered.filter(i =>
                i.name.toLowerCase().includes(term) ||
                (i.address && i.address.toLowerCase().includes(term))
            );
        }

        // Bairro
        if (this.data.listBairro) {
            filtered = filtered.filter(i => i._bairro === this.data.listBairro);
        }

        // Cidade
        if (this.data.listCidade) {
            filtered = filtered.filter(i => i._cidade === this.data.listCidade);
        }

        // Open Now
        if (this.data.listOpenNow) {
            filtered = filtered.filter(i => this.isItemOpen(i));
        }

        // 3. Sort
        if (this.data.listSort === 'distance') {
            filtered.sort((a, b) => {
                let distA, distB;

                // Prefer user location if available
                if (this.data.userLocation && a.lat && a.lng && b.lat && b.lng) {
                    distA = this.calculateDistance(this.data.userLocation.lat, this.data.userLocation.lng, a.lat, a.lng);
                    distB = this.calculateDistance(this.data.userLocation.lat, this.data.userLocation.lng, b.lat, b.lng);
                    // Add temporary property for display (optional, or just rely on sort)
                    a._userDistance = distA;
                    b._userDistance = distB;
                } else {
                    // Fallback to server distance
                    distA = parseFloat((a.distance || '9999').replace(',', '.'));
                    distB = parseFloat((b.distance || '9999').replace(',', '.'));
                }

                return distA - distB;
            });
        } else if (this.data.listSort === 'alpha') {
            filtered.sort((a, b) => a.name.localeCompare(b.name));
        }

        // 4. Pagination
        const totalItems = filtered.length;
        const totalPages = Math.ceil(totalItems / this.data.itemsPerPage);

        if (this.data.listPage > totalPages) this.data.listPage = totalPages || 1;
        if (this.data.listPage < 1) this.data.listPage = 1;

        const start = (this.data.listPage - 1) * this.data.itemsPerPage;
        const end = start + this.data.itemsPerPage;
        const paginatedItems = filtered.slice(start, end);

        // --- Render ---

        let html = `
            <div class="search-container">
                <!-- Text Search -->
                <div style="display:flex; width:100%;">
                     <input type="text" 
                        class="search-input" 
                        placeholder="${i18n.t('search_placeholder')}" 
                        value="${this.data.listSearch}"
                        oninput="app.router.debounceSearch(this.value, '${category}')">
                </div>

                <!-- Filters Row -->
                <div class="filter-row">
                    <!-- Sort -->
                    <select class="filter-select" onchange="app.renderList('${category}', 1, null, this.value)">
                        <option value="distance" ${this.data.listSort === 'distance' ? 'selected' : ''}>${i18n.t('near_me')}</option>
                        <option value="alpha" ${this.data.listSort === 'alpha' ? 'selected' : ''}>${i18n.t('az')}</option>
                    </select>

                    <!-- Bairro -->
                    <select class="filter-select" onchange="app.renderList('${category}', 1, null, null, this.value)">
                        <option value="">${i18n.t('neighborhood')}</option>
                        ${bairros.map(b => `<option value="${b}" ${this.data.listBairro === b ? 'selected' : ''}>${b}</option>`).join('')}
                    </select>

                     <!-- Cidade -->
                    <select class="filter-select" onchange="app.renderList('${category}', 1, null, null, null, this.value)">
                        <option value="">${i18n.t('city')}</option>
                        ${cidades.map(c => `<option value="${c}" ${this.data.listCidade === c ? 'selected' : ''}>${c}</option>`).join('')}
                    </select>

                    <!-- Open Now Toggle -->
                     <div class="filter-toggle ${this.data.listOpenNow ? 'active' : ''}" 
                          onclick="app.renderList('${category}', 1, null, null, null, null, !${this.data.listOpenNow})">
                        <span>${i18n.t('open_now')}</span>
                     </div>
                </div>
            </div>
        `;

        if (totalItems === 0) {
            html += `<div class="loading">${i18n.t('no_places_found')}</div>`;

            // Allow clearing filters
            html += `<div style="text-align:center; margin-top:1rem;">
                        <button class="call-btn" onclick="app.renderList('${category}', 1, '', 'distance', '', '', false)">${i18n.t('clear_filters')}</button>
                     </div>`;

            container.innerHTML = html;
            return;
        }

        // List Items
        html += paginatedItems.map(item => {
            // Determine distance text
            let distText = '';
            if (this.data.userLocation && item._userDistance) {
                distText = item._userDistance.toFixed(1) + ' km';
            } else if (item.distance) {
                distText = item.distance + ' km';
            }

            const meta = this.getCategoryMeta(item.category);
            const is24h = item.is_24h == '1';
            const addressShort = item.address ? (item.address.length > 50 ? item.address.substring(0, 50) + '...' : item.address) : '';

            return `
            <div class="rich-list-card" onclick="app.router.navigate('detalhe', {id: ${item.id}})">
                <div class="rich-list-card-icon" style="background:${meta.bg}">
                    ${item.image_url ? 
                        `<img src="${item.image_url}" style="width:100%; height:100%; object-fit:cover; border-radius:14px;">` : 
                        `<span>${meta.icon}</span>`
                    }
                </div>
                <div class="rich-list-card-body">
                    <h3 class="rich-list-card-name">${item.name}</h3>
                    <p class="rich-list-card-address">${addressShort}</p>
                    <div class="rich-list-card-tags">
                        <span class="rich-tag" style="background:${meta.bg}; color:${meta.color}">${meta.label}</span>
                        ${is24h ? `<span class="rich-tag open-tag">${i18n.t('open_status')}</span>` : ''}
                        ${distText ? `<span class="rich-tag dist-tag">${distText}</span>` : ''}
                    </div>
                </div>
                <svg class="rich-list-card-arrow" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </div>
            `;
        }).join('');

        // Pagination Controls
        if (totalPages > 1) {
            html += `
                <div class="pagination">
                    <button class="page-btn" 
                            ${this.data.listPage === 1 ? 'disabled' : ''} 
                            onclick="app.renderList('${category}', ${this.data.listPage - 1})">
                        ${i18n.t('previous')}
                    </button>
                    
                    <span class="page-info">${i18n.t('page_of', this.data.listPage, totalPages)}</span>
                    
                    <button class="page-btn active" 
                            ${this.data.listPage === totalPages ? 'disabled' : ''} 
                            onclick="app.renderList('${category}', ${this.data.listPage + 1})">
                        ${i18n.t('next')}
                    </button>
                </div>
            `;
        }

        container.innerHTML = html;
    },

    isItemOpen(item) {
        if (item.is_24h == '1') return true;
        if (!item.hours) return false;

        const now = new Date();
        const dayIdx = now.getDay(); // 0-6 (Sun-Sat)
        const currentTime = now.getHours() * 60 + now.getMinutes();

        // Map day index to strings the Google data might use
        const dayMap = [
            ['domingo', 'sunday'],
            ['segunda', 'monday'],
            ['terça', 'tuesday'],
            ['quarta', 'wednesday'],
            ['quinta', 'thursday'],
            ['sexta', 'friday'],
            ['sábado', 'saturday']
        ];
        
        const possibleDays = dayMap[dayIdx];
        const days = item.hours.split(';').map(d => d.trim().toLowerCase());
        
        // Find line starting with one of the possible day names
        const dayInfo = days.find(line => possibleDays.some(d => line.startsWith(d)));

        if (!dayInfo) return false;

        // extract time range: "08:00 - 18:00" or "7:00 AM - 10:00 PM"
        const firstColon = dayInfo.indexOf(':');
        if (firstColon === -1) return false;
        
        const timePart = dayInfo.substring(firstColon + 1);
        if (!timePart || timePart.includes('fechado') || timePart.includes('closed')) return false;

        // Split by various dashes
        const ranges = timePart.split(/[–—-]/).map(t => t.trim());
        if (ranges.length < 2) return false;

        return this._checkTimeRange(ranges[0], ranges[1], currentTime);
    },

    _checkTimeRange(startStr, endStr, currentTime) {
        const parse = (s) => {
            if (!s) return 0;
            let time = s.trim().toUpperCase();
            let modifier = null;
            
            // Check for AM/PM
            if (time.includes('AM')) {
                modifier = 'AM';
                time = time.replace('AM', '').trim();
            } else if (time.includes('PM')) {
                modifier = 'PM';
                time = time.replace('PM', '').trim();
            }

            let [h, m] = time.split(':').map(Number);
            if (isNaN(h)) return 0;
            if (m === undefined || isNaN(m)) m = 0;

            if (modifier === 'PM' && h < 12) h += 12;
            if (modifier === 'AM' && h === 12) h = 0;

            return h * 60 + m;
        };
        const start = parse(startStr);
        const end = parse(endStr);

        if (end < start) { // Overnights (ex: 10:00 PM - 06:00 AM)
            return currentTime >= start || currentTime <= end;
        }
        return currentTime >= start && currentTime <= end;
    },

    renderDetail(id) {
        const container = document.getElementById('detail-container');
        const item = this.data.locais.find(i => i.id == id);

        if (!item) {
            container.innerHTML = i18n.t('no_places_found');
            return;
        }

        // --- Data Parsing ---
        const meta = this.getCategoryMeta(item.category);

        // Website extraction
        let website = null;
        let descriptionClean = item.description || '';
        const urlRegex = /(https?:\/\/[^\s]+)/g;
        const urlMatch = descriptionClean.match(urlRegex);
        if (urlMatch) {
            website = urlMatch[0];
            descriptionClean = descriptionClean.replace(urlRegex, '').replace('Site:', '').trim();
        }

        // Hours parsing
        const daysMap = {
            'Monday': 'Segunda', 'Tuesday': 'Terça', 'Wednesday': 'Quarta',
            'Thursday': 'Quinta', 'Friday': 'Sexta', 'Saturday': 'Sábado', 'Sunday': 'Domingo'
        };

        let hoursHtml = `<div class="text-muted" style="padding:0.5rem 0;">${i18n.t('closed_status')}</div>`;
        const isOpen = this.isItemOpen(item);
        let statusText = isOpen ? i18n.t('open_status') : i18n.t('closed_status');
        let statusClass = isOpen ? 'open-tag' : 'closed-tag';

        if (item.is_24h == '1') {
            hoursHtml = `<div class="schedule-table"><div class="schedule-row"><span class="day">Todos os dias</span><span class="hours">24 Horas</span></div></div>`;
            statusText = i18n.t('open_status');
            statusClass = '';
        } else if (item.hours) {
            const hoursArr = item.hours.split(';').map(h => h.trim());
            statusText = i18n.t('check_hours');
            statusClass = 'closed';

            hoursHtml = '<div class="schedule-table">';
            const todayEng = new Date().toLocaleDateString('en-US', { weekday: 'long' });

            hoursHtml += hoursArr.map(hStr => {
                const [dayEng, timeRange] = hStr.split(': ', 2);
                if (!daysMap[dayEng]) return '';
                const isToday = dayEng === todayEng;
                const dayPt = daysMap[dayEng];
                const timeClean = timeRange ? timeRange.replace('Closed', 'Fechado').replace('Open 24 hours', '24 Horas') : '';
                return `<div class="schedule-row ${isToday ? 'today' : ''}"><span class="day">${dayPt}</span><span class="hours">${timeClean}</span></div>`;
            }).join('');
            hoursHtml += '</div>';
        }

        // Map and share links
        const mapLink = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(item.lat + ',' + item.lng)}&travelmode=driving`;
        const wazeLink = `https://waze.com/ul?ll=${item.lat},${item.lng}&navigate=yes`;
        const categoryLabel = meta.label || i18n.t('places');

        // --- Render ---
        const heroBg = item.image_url ? `background-image: url('${item.image_url}');` : `background: ${meta.gradient};`;
        
        container.innerHTML = `
            <!-- Hero Section -->
            <div class="detail-hero-section" style="${heroBg}">
                ${!item.image_url ? `<div class="detail-hero-icon">${meta.icon}</div>` : ''}
                <div class="detail-hero-badges">
                    <span class="badge category" style="background:rgba(255,255,255,0.2); color:white;">${categoryLabel}</span>
                    <span class="badge status ${statusClass}" style="background:rgba(255,255,255,0.2); color:white;">${statusText}</span>
                </div>
                <h1 class="detail-hero-title">${item.name}</h1>
                <div class="detail-hero-address">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="white" stroke="none"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                    <span>${item.address || ''}</span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="detail-actions-row">
                <a href="${mapLink}" target="_blank" class="detail-action-btn primary-action">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>
                    <span>${i18n.t('directions')}</span>
                </a>
                ${item.phone ? `
                <a href="tel:${item.phone}" class="detail-action-btn outline-action">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.362 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0122 16.92z"></path></svg>
                    <span>${i18n.t('call_now')}</span>
                </a>` : ''}
                <button class="detail-action-btn outline-action" onclick="app.sharePlace('${item.name.replace(/'/g, "\\'")}', '${item.address ? item.address.replace(/'/g, "\\'") : ''}')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                    <span>${i18n.t('share')}</span>
                </button>
            </div>

            <div class="detail-grid">
                <!-- Left Column -->
                <div>
                    <!-- Map -->
                    <div class="info-card">
                        <div class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon></svg>
                            ${i18n.t('location_title')}
                        </div>
                        <div class="map-preview" id="detail-map-preview" style="cursor: pointer;">
                            <!-- Map rendered by JS -->
                        </div>
                        <div style="display:flex; gap:8px; margin-top:0.75rem;">
                            <a href="${mapLink}" target="_blank" class="action-btn primary small" style="flex:1; text-align:center; text-decoration:none;">
                                Google Maps
                            </a>
                            <a href="${wazeLink}" target="_blank" class="action-btn outline small" style="flex:1; text-align:center; text-decoration:none; background:#33ccff; color:white; border-color:#33ccff;">
                                Waze
                            </a>
                        </div>
                    </div>

                    <!-- Info -->
                    ${descriptionClean ? `
                    <div class="info-card">
                        <div class="card-title">
                            <span>\u{2139}\uFE0F</span> ${i18n.t('quick_info')}
                        </div>
                        <p style="font-size:0.95rem; color:var(--text-secondary); line-height:1.5;">${descriptionClean}</p>
                    </div>` : ''}
                </div>

                <!-- Right Column -->
                <div>
                    <!-- Hours -->
                    <div class="info-card">
                        <div class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            ${i18n.t('hours_of_operation')}
                        </div>
                        ${hoursHtml}
                    </div>

                    <!-- Contact Info -->
                    <div class="info-card">
                        <div class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="M7 15h0M2 9.5h20"></path></svg>
                            ${i18n.t('quick_info')}
                        </div>
                        <div class="info-list">
                            ${item.phone ? `
                            <div class="info-item">
                                <label>${i18n.t('phone')}</label>
                                <a href="tel:${item.phone}">${item.phone}</a>
                            </div>` : ''}
                            ${website ? `
                            <div class="info-item">
                                <label>${i18n.t('website')}</label>
                                <a href="${website}" target="_blank">${i18n.t('visit_website')}</a>
                            </div>` : ''}
                            <div class="info-item">
                                <label>${i18n.t('parking')}</label>
                                <span>${i18n.t('parking_available')}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Initialize Mini Map
        if (item.lat && item.lng) {
            setTimeout(() => {
                const mapEl = document.getElementById('detail-map-preview');
                if (mapEl) {
                    const miniMap = L.map('detail-map-preview', {
                        center: [item.lat, item.lng],
                        zoom: 15,
                        zoomControl: false,
                        dragging: false,
                        scrollWheelZoom: false,
                        doubleClickZoom: false,
                        boxZoom: false,
                        keyboard: false,
                        attributionControl: false
                    });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: ''
                    }).addTo(miniMap);

                    const icon = MapManager.createIcon(this.getCategoryMeta(item.category).color);
                    L.marker([item.lat, item.lng], { icon: icon }).addTo(miniMap);

                    mapEl.addEventListener('click', () => {
                        window.open(mapLink, '_blank');
                    });
                }
            }, 100);
        }
    },

    // Share functionality
    async sharePlace(name, address) {
        const shareData = {
            title: name,
            text: `${name} - ${address}`,
            url: window.location.href
        };

        if (navigator.share) {
            try {
                await navigator.share(shareData);
            } catch (err) {
                console.log('Share cancelled');
            }
        } else {
            // Fallback: copy to clipboard
            try {
                await navigator.clipboard.writeText(`${name} - ${address}`);
                alert(i18n.t('link_copied') || 'Copiado!');
            } catch (err) {
                console.error('Copy failed', err);
            }
        }
    },

    canViewOps() {
        return !!(this.auth.user && this.auth.user.caps && this.auth.user.caps.view_ops);
    },

    canReallocateOps() {
        return !!(this.auth.user && this.auth.user.caps && this.auth.user.caps.reallocate_ops);
    },

    canManageOps() {
        return !!(this.auth.user && this.auth.user.caps && this.auth.user.caps.manage_ops);
    },

    usesMineOnlyOps() {
        return this.canViewOps() && !this.canReallocateOps() && !this.canManageOps();
    },

    async loadVolunteerOps(force = false) {
        if (!this.canViewOps()) {
            return null;
        }
        if (this._opsAuthFailed && !force) {
            return null;
        }
        if (this._volunteerOpsPromise && !force) {
            return this._volunteerOpsPromise;
        }
        const mineOnly = this.usesMineOnlyOps();
        this._volunteerOpsPromise = (async () => {
            const data = await API.getVolunteerOps(mineOnly);
            if (data && data.__authError) {
                this.auth.handleOpsAuthFailure();
                return null;
            }
            if (data) {
                this.data.volunteerOps = data;
                this._opsAuthFailed = false;
            }
            return data;
        })().finally(() => {
            this._volunteerOpsPromise = null;
        });
        return this._volunteerOpsPromise;
    },

    getOpsRoleLabel() {
        if (!this.auth.user || !this.auth.user.roles || !this.auth.user.roles.length) {
            return '';
        }
        const role = this.auth.user.roles[0];
        const map = {
            zelo_voluntario: i18n.t('role_volunteer'),
            zelo_homem_chave: i18n.t('role_keyman'),
            zelo_supervisor_grupo: i18n.t('role_group_supervisor'),
            zelo_supervisor_app: i18n.t('role_app_supervisor'),
            administrator: i18n.t('role_admin')
        };
        return map[role] || role;
    },

    escapeHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    },

    getOpsStatusLabel(status) {
        const s = status || 'pending';
        if (s === 'checked_in') return i18n.t('ops_status_checked_in');
        if (s === 'checked_out') return i18n.t('ops_status_checked_out');
        return i18n.t('ops_status_pending');
    },

    getOpsStatusBadge(assignmentId) {
        const status = this.getCheckinStatus(assignmentId).status || 'pending';
        const cls = status === 'checked_in' ? 'ops-status-checked_in'
            : status === 'checked_out' ? 'ops-status-checked_out'
            : 'ops-status-pending';
        return `<span class="ops-status-badge ${cls}">${this.escapeHtml(this.getOpsStatusLabel(status))}</span>`;
    },

    updateBottomNavForVolunteer() {
        const nav = document.getElementById('nav-profile-or-ops');
        if (!nav) return;
        const opsMode = this.canViewOps();
        nav.classList.toggle('nav-item--ops-mode', opsMode);
        nav.dataset.target = opsMode ? 'escala' : 'profile';
        const labelProfile = nav.querySelector('.nav-label-profile');
        const labelOps = nav.querySelector('.nav-label-ops');
        if (labelProfile) labelProfile.hidden = opsMode;
        if (labelOps) labelOps.hidden = !opsMode;
    },

    navToProfileOrOps() {
        if (this.canViewOps()) {
            this.openVolunteerOps();
        } else {
            this.auth.handleIconClick();
        }
    },

    toggleHomeVisitorExtrasCollapse() {
        const el = document.getElementById('home-visitor-extras');
        if (!el) return;
        if (this.canViewOps() && this.auth.user) {
            el.removeAttribute('open');
        } else {
            el.setAttribute('open', '');
        }
    },

    toggleHomeWelcome() {
        const el = document.querySelector('.home-welcome');
        if (!el) return;
        el.hidden = !!(this.auth.user && this.canViewOps());
    },

    async renderHomeVolunteerDashboard() {
        this.toggleHomeWelcome();

        const container = document.getElementById('home-volunteer-dashboard');
        if (!container) return;

        if (!this.auth.user || !this.canViewOps()) {
            container.hidden = true;
            container.innerHTML = '';
            return;
        }

        container.hidden = false;
        const name = this.escapeHtml(this.auth.user.name || '');
        const roleLabel = this.escapeHtml(this.getOpsRoleLabel());

        if (this._opsAuthFailed) {
            container.innerHTML = `
                <h2>${i18n.t('ops_dashboard_title')}, ${name}</h2>
                <p class="volunteer-role-label">${roleLabel}</p>
                <p class="text-muted" style="margin:0.75rem 0;">${i18n.t('ops_session_expired')}</p>
                <button type="button" class="btn-block" onclick="app.router.navigate('login')">${i18n.t('login_btn')}</button>
            `;
            return;
        }

        if (!this.data.volunteerOps) {
            container.innerHTML = `<div class="loading">${i18n.t('loading')}</div>`;
            await this.loadVolunteerOps();
        }

        const ops = this.data.volunteerOps;
        const uid = this.auth.user.id;
        const schedule = (ops && Array.isArray(ops.schedule)) ? ops.schedule : [];
        const myRows = schedule.filter((i) => Number(i.wp_user_id) === uid);

        let assignmentsHtml = '';
        if (!myRows.length) {
            assignmentsHtml = `<p class="text-muted">${i18n.t('ops_no_assignments')}</p>`;
        } else {
            assignmentsHtml = myRows.map((item) => {
                const status = this.getCheckinStatus(item.id);
                const idEsc = String(item.id).replace(/'/g, "\\'");
                const checkinBtn = status.status === 'pending'
                    ? `<button type="button" class="btn-block btn-block--compact" onclick="app.doCheckin('${idEsc}')">${i18n.t('ops_quick_checkin')}</button>`
                    : '';
                return `
                    <div class="home-volunteer-assignment">
                        <div class="home-volunteer-assignment-head">
                            <strong>${this.escapeHtml(this.getOpsDayLabel(item.day))} · ${this.escapeHtml(item.shift || '')}</strong>
                            ${this.getOpsStatusBadge(item.id)}
                        </div>
                        <p style="margin:0.25rem 0;font-size:0.9rem;"><strong>${i18n.t('ops_location')}:</strong> ${this.escapeHtml(item.location || '-')}</p>
                        <p style="margin:0;font-size:0.85rem;color:#64748b;">${this.escapeHtml(item.start || '')}${item.end ? ' – ' + this.escapeHtml(item.end) : ''}</p>
                        ${checkinBtn}
                    </div>
                `;
            }).join('');
        }

        container.innerHTML = `
            <h2>${i18n.t('ops_dashboard_title')}, ${name}</h2>
            <p class="volunteer-role-label">${roleLabel}</p>
            <h3 style="font-size:0.95rem;margin:0 0 0.5rem;">${i18n.t('ops_my_assignments')}</h3>
            ${assignmentsHtml}
            <div class="home-volunteer-dashboard-actions">
                <button type="button" class="btn-block" onclick="app.openVolunteerOps()">${i18n.t('ops_view_full_schedule')}</button>
            </div>
            <a href="#" class="profile-link" onclick="app.router.navigate('profile'); return false;">${i18n.t('my_profile')}</a>
        `;
    },

    async renderIndoorEventMap() {
        const el = document.getElementById('indoor-map-container');
        if (!el) return;
        el.innerHTML = '<div class="loading">Carregando mapa interno...</div>';
        const cfg = await API.getIndoorMap();
        this.data.indoorMap = cfg;
        if (!cfg || !cfg.image_url) {
            el.innerHTML = '<p class="text-muted" style="padding:1rem;">Mapa interno ainda não configurado (admin: JSON avançado ou campo indoor_map).</p>';
            return;
        }
        const pts = Array.isArray(cfg.points) ? cfg.points : [];
        const w = cfg.width || 1000;
        const h = cfg.height || 700;
        let html = `<div class="indoor-map-wrap" style="position:relative;width:100%;max-width:100%;">`;
        html += `<img src="${cfg.image_url}" alt="Mapa" style="width:100%;height:auto;display:block;border-radius:8px;" />`;
        pts.forEach((p) => {
            const x = typeof p.x === 'number' ? p.x : parseFloat(p.x) || 0;
            const y = typeof p.y === 'number' ? p.y : parseFloat(p.y) || 0;
            const label = (p.label || p.type || '').replace(/</g, '');
            html += `<button type="button" class="indoor-dot" title="${label}" style="position:absolute;left:${x * 100}%;top:${y * 100}%;transform:translate(-50%,-50%);width:14px;height:14px;border-radius:50%;background:#137fec;border:2px solid #fff;cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,.3);" onclick="alert('${label.replace(/'/g, "\\'")}')"></button>`;
        });
        html += `</div><p class="text-muted" style="font-size:0.8rem;margin-top:0.5rem;">${w}×${h}px · ${pts.length} pontos</p>`;
        el.innerHTML = html;
    },

    async requestSwap(assignmentId) {
        const reason = prompt('Motivo (opcional):') || '';
        try {
            await API.createSwapRequest(assignmentId, reason);
            alert('Pedido de substituição enviado.');
            await this.loadVolunteerOps();
            this.renderVolunteerOps();
        } catch (e) {
            alert(e.message || 'Falha ao enviar pedido.');
        }
    },

    async resolveSwap(id, status, replacementName, replacementUid) {
        try {
            const res = await API.patchSwapRequest(id, status, {
                replacement_volunteer_name: replacementName || '',
                replacement_user_id: replacementUid || 0
            });
            if (res.data) this.data.volunteerOps = res.data;
            this.renderVolunteerOps();
        } catch (e) {
            alert(e.message || 'Falha.');
        }
    },

    resolveSwapPrompt(id) {
        const name = prompt('Nome do substituto (opcional):') || '';
        const rid = parseInt(prompt('ID WordPress do substituto (0 se não aplicar):') || '0', 10) || 0;
        this.resolveSwap(id, 'approved', name, rid);
    },

    async openVolunteerOps() {
        if (!this.auth.user) {
            this.router.navigate('login');
            return;
        }
        if (!this.canViewOps()) {
            alert('Seu perfil não possui acesso à escala operacional.');
            return;
        }
        if (this._opsAuthFailed) {
            this.router.navigate('login');
            return;
        }
        await this.loadVolunteerOps();
        this.router.navigate('escala');
    },

    getOpsDayLabel(day) {
        const map = { sexta: 'Sexta', sabado: 'Sábado', domingo: 'Domingo' };
        return map[day] || day;
    },

    getCheckinStatus(assignmentId) {
        const checkins = this.data.volunteerOps?.checkins || {};
        return checkins[assignmentId] || { status: 'pending' };
    },

    async doCheckin(assignmentId) {
        try {
            const result = await API.checkinVolunteer(assignmentId);
            if (this.data.volunteerOps) this.data.volunteerOps.checkins = result.checkins || {};
            this.renderVolunteerOps();
            if (this.router.currentView === 'home') {
                this.renderHomeVolunteerDashboard();
            }
        } catch (err) {
            alert('Não foi possível confirmar chegada.');
        }
    },

    async doCheckout(assignmentId) {
        try {
            const result = await API.checkoutVolunteer(assignmentId);
            if (this.data.volunteerOps) this.data.volunteerOps.checkins = result.checkins || {};
            this.renderVolunteerOps();
            if (this.router.currentView === 'home') {
                this.renderHomeVolunteerDashboard();
            }
        } catch (err) {
            alert('Não foi possível confirmar saída.');
        }
    },

    async doReallocate(assignmentId) {
        if (!this.canReallocateOps()) return;
        const newLocation = prompt('Informe o novo local de atuação:');
        if (!newLocation) return;
        try {
            const result = await API.reallocateVolunteer(assignmentId, newLocation);
            this.data.volunteerOps = result.data || this.data.volunteerOps;
            this.renderVolunteerOps();
        } catch (err) {
            alert('Falha na realocação.');
        }
    },

    renderVolunteerOps() {
        const container = document.getElementById('volunteer-ops-container');
        if (!container) return;

        if (!this.auth.user) {
            container.innerHTML = '<div class="loading">Faça login para acessar a escala operacional.</div>';
            return;
        }
        if (!this.canViewOps()) {
            container.innerHTML = '<div class="loading">Seu perfil não possui permissão para visualizar a escala.</div>';
            return;
        }

        const ops = this.data.volunteerOps;
        if (!ops || !Array.isArray(ops.schedule)) {
            container.innerHTML = '<div class="loading">Carregando escala operacional...</div>';
            return;
        }

        const mineOnly = this.usesMineOnlyOps();
        const selectedDay = document.getElementById('ops-day-filter')?.value || '';
        const selectedShift = document.getElementById('ops-shift-filter')?.value || '';
        const selectedLanguage = (document.getElementById('ops-language-filter')?.value || '').toLowerCase();

        const uid = this.auth.user.id;
        const myRows = ops.schedule.filter((i) => Number(i.wp_user_id) === uid);
        let myHtml = '';
        if (!mineOnly) {
            if (myRows.length) {
                myHtml = `<div class="ops-mine-block" style="margin-bottom:1rem;padding:0.75rem;background:#f0f9ff;border-radius:8px;border:1px solid #bae6fd;"><h3 style="margin:0 0 0.5rem;">${i18n.t('ops_my_assignments')}</h3><ul style="margin:0;padding-left:1.2rem;">${myRows.map((i) => `<li><strong>${this.getOpsDayLabel(i.day)}</strong> ${i.shift} — ${i.volunteer_name || ''} @ ${i.location || '-'}</li>`).join('')}</ul></div>`;
            } else {
                myHtml = `<div class="ops-mine-block text-muted" style="margin-bottom:1rem;">${i18n.t('ops_no_wp_user')}</div>`;
            }
        }

        let swapPanel = '';
        const swaps = ops.swap_requests || [];
        if (this.canManageOps() || this.canReallocateOps()) {
            const pend = swaps.filter((s) => s.status === 'pending');
            swapPanel = `<div class="ops-swap-panel" style="margin-bottom:1rem;"><h3>Pedidos de substituição</h3>${pend.length ? pend.map((s) => `<div class="ops-schedule-card" style="padding:0.75rem;"><code>${s.id}</code> — designação <strong>${s.assignment_id}</strong> (solicitante ${s.requester_id})<div class="ops-swap-actions"><button type="button" class="ops-btn ops-btn--active" onclick="app.resolveSwapPrompt('${s.id}')">Aprovar</button><button type="button" class="ops-btn" onclick="app.resolveSwap('${s.id}','rejected','',0)">Recusar</button></div></div>`).join('') : '<p class="text-muted">Nenhum pedido pendente.</p>'}</div>`;
        }

        let histBlock = '';
        if (this.canManageOps() && Array.isArray(ops.history) && ops.history.length) {
            histBlock = `<div style="margin-bottom:1rem;"><h3>Últimas alterações</h3><ul style="font-size:0.85rem;">${ops.history.slice(0, 15).map((h) => `<li><code>${h.type || ''}</code> ${h.at || ''} — assignment ${h.assignment_id || ''}</li>`).join('')}</ul></div>`;
        }

        let items = ops.schedule.slice();
        if (selectedDay) items = items.filter(i => i.day === selectedDay);
        if (selectedShift) items = items.filter(i => i.shift === selectedShift);
        if (selectedLanguage) {
            items = items.filter(i => (i.languages || []).some(lang => String(lang).toLowerCase().includes(selectedLanguage)));
        }

        const governance = ops.governance || {};
        const governanceHtml = !mineOnly ? Object.entries(governance).map(([day, data]) => `
            <div class="ops-governance-card">
                <h4>${this.getOpsDayLabel(day)}</h4>
                <p><strong>Grupo A:</strong> ${data.group_a_supervisor || '-'}</p>
                <p><strong>Grupo B:</strong> ${data.group_b_supervisor || '-'}</p>
                <p><strong>Supervisor App:</strong> ${data.app_supervisor || '-'}</p>
                <p><strong>Homens-chave:</strong> ${data.keymen ? Object.entries(data.keymen).map(([shift, person]) => `${shift}: ${person}`).join(' | ') : '-'}</p>
            </div>
        `).join('') : '';

        const scheduleHtml = items.map(item => {
            const status = this.getCheckinStatus(item.id);
            const isCheckedIn = status.status === 'checked_in';
            const isCheckedOut = status.status === 'checked_out';
            const mineRow = Number(item.wp_user_id) === uid;
            const swapBtn = mineRow ? `<button type="button" class="ops-btn ops-btn--accent" onclick="app.requestSwap('${String(item.id).replace(/'/g, "\\'")}')">Pedir substituição</button>` : '';
            return `
                <div class="ops-schedule-card">
                    <div class="ops-card-head">
                        <h4>${item.volunteer_name || 'Voluntário'}</h4>
                        <span class="ops-tag">${this.getOpsDayLabel(item.day)} • ${item.shift}</span>
                        ${this.getOpsStatusBadge(item.id)}
                    </div>
                    <p><strong>Local:</strong> ${item.location || '-'}</p>
                    <p><strong>Horário:</strong> ${item.start || '-'} às ${item.end || '-'}</p>
                    <p><strong>Idiomas:</strong> ${(item.languages || []).join(', ') || '-'}</p>
                    <div class="ops-actions">
                        <button type="button" class="ops-btn${isCheckedIn ? ' ops-btn--active' : ''}" onclick="app.doCheckin('${String(item.id).replace(/'/g, "\\'")}')">Check-in</button>
                        <button type="button" class="ops-btn${isCheckedOut ? ' ops-btn--active' : ''}" onclick="app.doCheckout('${String(item.id).replace(/'/g, "\\'")}')">Check-out</button>
                        ${this.canReallocateOps() ? `<button type="button" class="ops-btn" onclick="app.doReallocate('${String(item.id).replace(/'/g, "\\'")}')">Realocar</button>` : ''}
                        ${swapBtn}
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = `
            ${myHtml}
            ${swapPanel}
            ${histBlock}
            <div class="ops-filters">
                <select id="ops-day-filter" onchange="app.renderVolunteerOps()">
                    <option value="">Todos os dias</option>
                    <option value="sexta" ${selectedDay === 'sexta' ? 'selected' : ''}>Sexta</option>
                    <option value="sabado" ${selectedDay === 'sabado' ? 'selected' : ''}>Sábado</option>
                    <option value="domingo" ${selectedDay === 'domingo' ? 'selected' : ''}>Domingo</option>
                </select>
                <select id="ops-shift-filter" onchange="app.renderVolunteerOps()">
                    <option value="">Todos os turnos</option>
                    <option value="A1" ${selectedShift === 'A1' ? 'selected' : ''}>A1</option>
                    <option value="B1" ${selectedShift === 'B1' ? 'selected' : ''}>B1</option>
                    <option value="A2" ${selectedShift === 'A2' ? 'selected' : ''}>A2</option>
                    <option value="B2" ${selectedShift === 'B2' ? 'selected' : ''}>B2</option>
                </select>
                <input id="ops-language-filter" value="${selectedLanguage}" oninput="app.renderVolunteerOps()" placeholder="Filtrar idioma">
            </div>
            ${!mineOnly ? `<h3>${i18n.t('ops_governance_title')}</h3>
            <div class="ops-governance-grid">${governanceHtml || '<p class="text-muted">Sem dados de governança.</p>'}</div>` : ''}
            <h3 style="margin-top:1rem;">${mineOnly ? i18n.t('ops_my_schedule') : i18n.t('ops_detailed_schedule')}</h3>
            <div class="ops-schedule-grid">${scheduleHtml || '<div class="loading">Nenhuma designação encontrada para os filtros selecionados.</div>'}</div>
        `;
    },

    renderEmergency() {
        const container = document.getElementById('emergency-container');
        const phones = this.data.evento.telefones_emergencia || [];

        container.innerHTML = phones.map(p => `
            <div class="contact-row">
                <span>${p.nome}</span>
                <a href="tel:${p.numero}" class="call-btn">${p.numero}</a>
            </div>
        `).join('');
    },

    shouldRefreshWeather() {
        const c = this.data.clima;
        if (!c || !c.updated_at) return true;
        const updated = new Date(c.updated_at).getTime();
        if (Number.isNaN(updated)) return true;
        return (Date.now() - updated) > 30 * 60 * 1000;
    },

    async refreshWeather() {
        if (this._weatherRefreshing) return;
        this._weatherRefreshing = true;
        const container = document.getElementById('weather-container');
        try {
            const data = await API.getClima();
            this.data.clima = data;
            if (this.router.currentView === 'tempo') {
                this.renderWeather();
            }
        } catch (err) {
            console.warn('Falha ao atualizar clima', err);
            if (this.router.currentView === 'tempo' && container) {
                if (this.data.clima && this.data.clima.current) {
                    this.renderWeather();
                } else {
                    const key = (err && err.code === 'zelo_weather_no_coords') ? 'weather_no_coords' : 'weather_unavailable';
                    this.renderWeatherError(container, key);
                }
            }
        } finally {
            this._weatherRefreshing = false;
        }
    },

    getWeatherIconSvg(iconKey, size) {
        const s = size || 48;
        const icons = {
            clear: `<svg xmlns="http://www.w3.org/2000/svg" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>`,
            partly_cloudy: `<svg xmlns="http://www.w3.org/2000/svg" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="M20 12h2"/><path d="m19.07 4.93-1.41 1.41"/><path d="M15.947 12.65a4 4 0 0 0-5.925-4.128"/><path d="M13 22H7a5 5 0 1 1 4.9-6H13a3 3 0 0 1 0 6Z"/></svg>`,
            cloudy: `<svg xmlns="http://www.w3.org/2000/svg" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>`,
            fog: `<svg xmlns="http://www.w3.org/2000/svg" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14h16M4 18h16M4 10h16M4 6h10"/></svg>`,
            drizzle: `<svg xmlns="http://www.w3.org/2000/svg" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M8 19v1M8 21v1M12 19v1M12 21v1M16 19v1M16 21v1"/></svg>`,
            rain: `<svg xmlns="http://www.w3.org/2000/svg" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M8 19v2M8 21v2M12 19v2M12 21v2M16 19v2M16 21v2"/></svg>`,
            snow: `<svg xmlns="http://www.w3.org/2000/svg" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M8 15h.01M8 19h.01M12 17h.01M12 21h.01M16 15h.01M16 19h.01"/></svg>`,
            thunder: `<svg xmlns="http://www.w3.org/2000/svg" width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="m13 17-3-5h4l-3 5"/></svg>`
        };
        return icons[iconKey] || icons.cloudy;
    },

    formatWeatherUpdatedAt(iso) {
        if (!iso) return '';
        try {
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return '';
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return '';
        }
    },

    renderWeatherError(container, messageKey) {
        container.innerHTML = `
            <div class="info-card weather-error-card">
                <p>${this.escapeHtml(i18n.t(messageKey))}</p>
                <button type="button" class="btn-block outline" onclick="app.refreshWeather()">${this.escapeHtml(i18n.t('weather_retry'))}</button>
            </div>
        `;
    },

    renderWeather() {
        const container = document.getElementById('weather-container');
        if (!container) return;

        const renderFromData = (data) => {
            if (!data || data.enabled === false) {
                this.renderWeatherError(container, 'weather_disabled');
                return;
            }
            if (!data.current) {
                this.renderWeatherError(container, 'weather_unavailable');
                return;
            }

            const cur = data.current;
            const loc = data.location || {};
            const offline = !navigator.onLine;
            const stale = !!data.stale;
            const updatedLabel = this.formatWeatherUpdatedAt(data.updated_at);
            const badges = [];
            if (offline) {
                badges.push(`<span class="weather-badge weather-badge-offline">${this.escapeHtml(i18n.t('weather_offline'))}</span>`);
            }
            if (stale || offline) {
                badges.push(`<span class="weather-badge weather-badge-stale">${this.escapeHtml(i18n.t('weather_stale'))}</span>`);
            }

            const hourly = (data.hourly_today || []).map((slot) => `
                <div class="weather-hour-chip">
                    <span class="weather-hour-time">${this.escapeHtml(slot.time)}</span>
                    <span class="weather-hour-icon">${this.getWeatherIconSvg(slot.icon, 22)}</span>
                    <span class="weather-hour-temp">${slot.temp_c != null ? slot.temp_c + '°' : '—'}</span>
                    ${slot.precip_pct > 0 ? `<span class="weather-hour-precip">${slot.precip_pct}%</span>` : ''}
                </div>
            `).join('');

            const daily = (data.daily || []).map((day) => `
                <div class="weather-day-row">
                    <span class="weather-day-label">${this.escapeHtml(day.day_label || day.date)}</span>
                    <span class="weather-day-icon">${this.getWeatherIconSvg(day.icon, 28)}</span>
                    <span class="weather-day-precip">${day.precip_pct > 0 ? day.precip_pct + '%' : ''}</span>
                    <span class="weather-day-temps">
                        <span class="weather-temp-max">${day.temp_max_c != null ? day.temp_max_c + '°' : '—'}</span>
                        <span class="weather-temp-min">${day.temp_min_c != null ? day.temp_min_c + '°' : ''}</span>
                    </span>
                </div>
            `).join('');

            container.innerHTML = `
                <div class="weather-hero">
                    <div class="weather-hero-main">
                        <div class="weather-hero-icon">${this.getWeatherIconSvg(cur.icon, 56)}</div>
                        <div class="weather-hero-temp">${cur.temp_c != null ? cur.temp_c : '—'}°</div>
                    </div>
                    <p class="weather-hero-label">${this.escapeHtml(cur.label || '')}</p>
                    <div class="weather-hero-stats">
                        <span>${this.escapeHtml(i18n.t('weather_feels_like'))}: ${cur.feels_like_c != null ? cur.feels_like_c + '°' : '—'}</span>
                        <span>${this.escapeHtml(i18n.t('weather_humidity'))}: ${cur.humidity_pct != null ? cur.humidity_pct + '%' : '—'}</span>
                        <span>${this.escapeHtml(i18n.t('weather_wind'))}: ${cur.wind_kmh != null ? cur.wind_kmh + ' km/h' : '—'}</span>
                    </div>
                </div>
                <div class="weather-meta">
                    <div>
                        <strong>${this.escapeHtml(loc.name || '')}</strong>
                        ${loc.address ? `<p class="weather-meta-address">${this.escapeHtml(loc.address)}</p>` : ''}
                    </div>
                    <div class="weather-meta-right">
                        ${updatedLabel ? `<span class="weather-updated">${this.escapeHtml(i18n.t('weather_updated'))} ${this.escapeHtml(updatedLabel)}</span>` : ''}
                        <div class="weather-badges">${badges.join('')}</div>
                    </div>
                </div>
                ${hourly ? `
                <div class="info-card weather-section-card">
                    <div class="card-title">${this.escapeHtml(i18n.t('weather_hourly'))}</div>
                    <div class="weather-hourly-scroll">${hourly}</div>
                </div>` : ''}
                ${daily ? `
                <div class="info-card weather-section-card">
                    <div class="card-title">${this.escapeHtml(i18n.t('weather_week'))}</div>
                    <div class="weather-daily-list">${daily}</div>
                </div>` : ''}
                <p class="weather-attribution">${this.escapeHtml(i18n.t('weather_attribution'))}</p>
            `;
        };

        if (this.data.clima && this.data.clima.current) {
            renderFromData(this.data.clima);
            if (navigator.onLine && this.shouldRefreshWeather()) {
                this.refreshWeather();
            }
            return;
        }

        container.innerHTML = `<div class="loading">${this.escapeHtml(i18n.t('loading'))}</div>`;

        if (navigator.onLine) {
            this.refreshWeather();
            return;
        }

        if (this.data.clima) {
            renderFromData(this.data.clima);
        } else {
            this.renderWeatherError(container, 'weather_unavailable');
        }
    },

    renderEventInfo() {
        const container = document.getElementById('event-container');
        const evt = this.data.evento;

        if (!evt) return;

        // Fallback for image
        const heroImage = evt.foto || 'images/convention-center.jpg'; // Placeholder

        // Helper to safely get nested properties
        const info = evt.info_uteis || {};

        container.innerHTML = `
            <div class="event-hero" style="background-image: url('${heroImage}');">
                <div class="hero-overlay">
                    <h1>${evt.name_evento}</h1>
                </div>
            </div>

            <div class="event-grid">
                <!-- Left Column -->
                <div class="event-main">
                    
                    <!-- Location -->
                    <div class="info-card">
                        <div class="card-title text-primary">LOCALIZAÇÃO</div>
                        <h3>${evt.local || 'Centro de Convenções'}</h3>
                        <p>${evt.endereco}</p>
                        
                        <div style="display: flex; gap: 10px; margin-top: 1rem;">
                            <button class="action-btn outline small" onclick="navigator.clipboard.writeText('${evt.endereco}')">
                                📋 Copiar Endereço
                            </button>
                            <button class="action-btn primary small" onclick="window.open('https://maps.google.com/?q=${evt.endereco}', '_blank')">
                                🗺️ Abrir no Mapa
                            </button>
                        </div>
                    </div>

                    <!-- How to get there -->
                    <div class="info-card">
                        <div class="card-title">🚍 Como chegar</div>
                        <div class="transport-grid">
                            ${info.trans_shuttle && info.trans_shuttle.active ? `
                            <div class="transport-item">
                                <div class="icon">🚌</div>
                                <h4>${info.trans_shuttle.title}</h4>
                                <p>${info.trans_shuttle.desc}</p>
                            </div>` : ''}

                            ${info.trans_public && info.trans_public.active ? `
                            <div class="transport-item">
                                <div class="icon">🚇</div>
                                <h4>${info.trans_public.title}</h4>
                                <p>${info.trans_public.desc}</p>
                            </div>` : ''}

                            ${info.trans_taxi && info.trans_taxi.active ? `
                            <div class="transport-item">
                                <div class="icon">🚕</div>
                                <h4>${info.trans_taxi.title}</h4>
                                <p>${info.trans_taxi.desc}</p>
                            </div>` : ''}
                        </div>
                    </div>

                    <!-- Map Place -->
                    <div class="map-preview" id="event-map-preview">
                        <!-- Mini map will be rendered here -->
                    </div>

                </div>

                <!-- Right Column (Sidebar) -->
                <div class="event-sidebar">
                    
                    <!-- Useful Info -->
                    <div class="info-card highlight-blue">
                        <h3 style="color:white; margin-bottom:1rem;">📶 Informações úteis</h3>
                        <div style="margin-bottom: 1rem;">
                            <div style="font-size:0.8rem; opacity:0.8;">WI-FI DO EVENTO (SSID)</div>
                            <div style="font-weight:bold; font-size:1.1rem;">${info.wifi_ssid || 'Não divulgado'}</div>
                        </div>
                        <div>
                            <div style="font-size:0.8rem; opacity:0.8;">SENHA</div>
                            <div style="font-weight:bold; font-size:1.1rem;">${info.wifi_pass || '-'}</div>
                        </div>
                    </div>

                    <!-- Credenciamento -->
                    <div class="info-card">
                        <div class="card-title">🆔 Credenciamento</div>
                        <div class="timeline-item">
                            <div class="time">Horário</div>
                            <div class="desc">${info.cred_hours || 'Consulte a programação'}</div>
                        </div>
                        <div class="timeline-item">
                            <div class="time">Documentos</div>
                            <div class="desc">${info.cred_docs || 'Documento com foto'}</div>
                        </div>
                    </div>

                    <!-- Safety -->
                    <div class="info-card highlight-red">
                        <div class="card-title" style="color: #d63384;">⛑️ Segurança</div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                            <span>Posto Médico</span>
                            <strong>${info.medical_loc || 'A definir'}</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span>Emergência</span>
                            <strong style="color:var(--danger-color);">${info.emergency_phone || '192'}</strong>
                        </div>
                    </div>

                    <!-- Support -->
                    <div class="info-card">
                        <div class="card-title">Suporte ao Visitante</div>
                        <p style="font-size:0.9rem; color:#666; margin-bottom:1rem;">Precisa de ajuda? Fale conosco.</p>
                        <button class="btn-block outline" onclick="window.open('${info.support_chat || '#'}', '_blank')">
                            Chat de Suporte
                        </button>
                        <button class="btn-block" style="background:#f0f0f0; color:#333;" onclick="window.location.href='mailto:${evt.contatos.email}'">
                             📧 Enviar E-mail
                        </button>
                    </div>

                </div>
            </div>
        `;

        // Initialize Mini Map
        if (evt.coordenadas && evt.coordenadas.lat && evt.coordenadas.lng) {
            setTimeout(() => {
                const mapEl = document.getElementById('event-map-preview');
                if (mapEl) {
                    // Check if map instance already exists on this element to avoid init error
                    if (mapEl._leaflet_id) return;

                    const miniMap = L.map('event-map-preview', {
                        center: [evt.coordenadas.lat, evt.coordenadas.lng],
                        zoom: 15,
                        zoomControl: false,
                        dragging: false,
                        scrollWheelZoom: false,
                        doubleClickZoom: false,
                        boxZoom: false,
                        keyboard: false,
                        attributionControl: false
                    });

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: ''
                    }).addTo(miniMap);

                    L.marker([evt.coordenadas.lat, evt.coordenadas.lng]).addTo(miniMap);

                    // Click to open Google Maps
                    mapEl.addEventListener('click', () => {
                        window.open(`https://maps.google.com/?q=${evt.endereco}`, '_blank');
                    });
                    // Force resize to ensure tiles load
                    setTimeout(() => { miniMap.invalidateSize(); }, 200);
                }
            }, 500); // Increased timeout to ensure DOM is ready
        }

        // Load map if coordinates exist
        // Note: evt.lat/lng might need to be verified from API response structure. 
        // Assuming they exist or using defaults for demo.
        if (evt.lat || (evt.contatos && evt.contatos.lat)) {
            setTimeout(() => {
                const lat = evt.lat || -25.4284;
                const lng = evt.lng || -49.2733;
                const mapEl = document.getElementById('event-map-preview');
                if (mapEl) {
                    const map = L.map('event-map-preview', {
                        center: [lat, lng],
                        zoom: 14,
                        heading: true,
                        zoomControl: false
                    });
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
                    L.marker([lat, lng]).addTo(map);
                }
            }, 200);
        }
    }
};

// Start app
document.addEventListener('DOMContentLoaded', () => {
    app.init();

    // PWA Install Prompt Logic
    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent Chrome 67 and earlier from automatically showing the prompt
        e.preventDefault();
        // Stash the event so it can be triggered later.
        app.data.installPrompt = e;

        // Show the install button
        const installBtn = document.getElementById('install-btn');
        if (installBtn) {
            installBtn.style.display = 'block';

            installBtn.addEventListener('click', () => {
                // Hide the app provided install promotion
                installBtn.style.display = 'none';
                // Show the install prompt
                app.data.installPrompt.prompt();
                // Wait for the user to respond to the prompt
                app.data.installPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the A2HS prompt');
                    } else {
                        console.log('User dismissed the A2HS prompt');
                    }
                    app.data.installPrompt = null;
                });
            });
        }
    });

    // Optional: Hide button if already installed (handled by browser not firing event, but good to be safe)
    window.addEventListener('appinstalled', (evt) => {
        console.log('a2hs installed');
        const installBtn = document.getElementById('install-btn');
        if (installBtn) installBtn.style.display = 'none';
    });

    // Network Status Logic
    const updateNetworkStatus = () => {
        const statusEl = document.getElementById('network-status');
        if (navigator.onLine) {
            statusEl.textContent = 'Online';
            statusEl.classList.remove('offline');
        } else {
            statusEl.textContent = 'Offline';
            statusEl.classList.add('offline');
        }
    };

    window.addEventListener('online', updateNetworkStatus);
    window.addEventListener('offline', updateNetworkStatus);

    // Initial check
    updateNetworkStatus();

    // Update External Links
    const linkLostPwd = document.getElementById('link-lost-password');
    const linkEditProfile = document.getElementById('link-edit-profile');
    if (linkLostPwd && API.siteUrl) {
        linkLostPwd.href = `${API.siteUrl}/wp-login.php?action=lostpassword`;
    }
    if (linkEditProfile && API.siteUrl) {
        linkEditProfile.href = `${API.siteUrl}/wp-admin/profile.php`;
    }
});
