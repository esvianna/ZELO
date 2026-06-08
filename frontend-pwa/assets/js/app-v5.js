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

    _dataStale: {
        locais: false,
        ops: false,
        clima: false,
        evento: false
    },

    // --- Helpers ---
    renderStaleBadge(scope) {
        const key = scope === 'ops' ? 'data_stale_ops' : scope === 'locais' ? 'data_stale_locais' : 'data_stale_generic';
        return `<span class="zelo-stale-badge" role="status">${this.escapeHtml(i18n.t(key))}</span>`;
    },

    syncStaleFlags() {
        if (typeof API === 'undefined' || !API.lastFetchFromCache) return;
        this._dataStale.locais = !!API.lastFetchFromCache.locais;
        this._dataStale.ops = !!API.lastFetchFromCache.volunteerOps;
        this._dataStale.clima = !!API.lastFetchFromCache.clima;
        this._dataStale.evento = !!API.lastFetchFromCache.evento;
    },

    async cacheUserAvatar(url) {
        if (!url || typeof caches === 'undefined') return;
        try {
            const u = new URL(url, window.location.href);
            if (u.origin !== window.location.origin) {
                return;
            }
            const res = await fetch(url, { mode: 'cors', credentials: 'include' });
            if (!res.ok) return;
            const cacheName = 'zelo-cache-v' + (typeof window.ZELO_APP_BUILD !== 'undefined' ? window.ZELO_APP_BUILD : '89');
            const cache = await caches.open(cacheName);
            await cache.put(url, res);
        } catch (e) {
            console.warn('Avatar cache skipped', e);
        }
    },

    getAvatarUrl() {
        const fallback = 'images/default-avatar.png';
        if (!this.auth.user || !this.auth.user.avatar) return fallback;
        return this.auth.user.avatar;
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
        lastParams: {},
        ROUTE_STORAGE_KEY: 'zelo_last_route',
        ROUTE_NON_PERSISTENT: new Set(['login', 'register', 'email-verified']),

        isPersistableView(viewId) {
            return !!(viewId && !this.ROUTE_NON_PERSISTENT.has(viewId) && document.getElementById(`view-${viewId}`));
        },

        canAccessView(viewId) {
            if (!viewId || !document.getElementById(`view-${viewId}`)) return false;
            if (viewId === 'escala') {
                return app.canViewOps() && !app._opsAuthFailed;
            }
            if (viewId === 'profile') {
                return !!app.auth.user;
            }
            return this.isPersistableView(viewId);
        },

        saveRoute(viewId, params) {
            if (!this.isPersistableView(viewId)) return;
            try {
                sessionStorage.setItem(this.ROUTE_STORAGE_KEY, JSON.stringify({
                    viewId,
                    params: params || {}
                }));
            } catch (e) {
                console.warn('Route persist', e);
            }
            this.syncHash(viewId, params);
        },

        syncHash(viewId, params) {
            if (!viewId || this.ROUTE_NON_PERSISTENT.has(viewId)) return;
            let hash = viewId;
            const sp = new URLSearchParams();
            if (params && params.category) sp.set('category', params.category);
            if (params && params.id != null && params.id !== '') sp.set('id', String(params.id));
            const qs = sp.toString();
            if (qs) hash += '?' + qs;
            const next = '#' + hash;
            if (window.location.hash !== next) {
                const url = window.location.pathname + window.location.search + next;
                history.replaceState(null, '', url);
            }
        },

        parseHashRoute() {
            const raw = (window.location.hash || '').replace(/^#/, '').trim();
            if (!raw) return null;
            const qi = raw.indexOf('?');
            const viewId = (qi >= 0 ? raw.slice(0, qi) : raw).toLowerCase();
            const params = {};
            if (qi >= 0) {
                new URLSearchParams(raw.slice(qi + 1)).forEach((val, key) => {
                    params[key] = key === 'id' && /^\d+$/.test(val) ? parseInt(val, 10) : val;
                });
            }
            return document.getElementById(`view-${viewId}`) ? { viewId, params } : null;
        },

        loadStoredRoute() {
            try {
                const raw = sessionStorage.getItem(this.ROUTE_STORAGE_KEY);
                if (!raw) return null;
                const data = JSON.parse(raw);
                if (!data || !data.viewId || !document.getElementById(`view-${data.viewId}`)) return null;
                return { viewId: data.viewId, params: data.params || {} };
            } catch (e) {
                return null;
            }
        },

        resolveInitialRoute() {
            return this.parseHashRoute() || this.loadStoredRoute() || { viewId: 'home', params: {} };
        },

        navigate(viewId, params = {}, options = {}) {
            if (viewId !== 'mapa-evento') {
                document.body.classList.remove('indoor-diagram-fullscreen');
            }
            if (viewId !== 'mapa-evento' && app._indoorComboboxAbort) {
                app._indoorComboboxAbort();
                app._indoorComboboxAbort = null;
            }
            this.lastParams = params || {};
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
                } else if (viewId === 'avisos') {
                    app.renderAvisos();
                } else if (viewId === 'escala') {
                    app.renderVolunteerOps();
                } else if (viewId === 'mapa-evento') {
                    if (!app.data.indoorMapUi) app.data.indoorMapUi = {};
                    if (window.matchMedia('(max-width: 768px)').matches && !app.data.indoorMapUi._userPickedTab) {
                        app.data.indoorMapUi.tab = 'guide';
                    }
                    app.renderIndoorEventMap();
                } else if (viewId === 'register') {
                    app.loadRegisterLanguages();
                } else if (viewId === 'profile') {
                    app.renderProfileLanguages();
                    app.populateProfileForm();
                }

                // Render Home components if on home
                if (viewId === 'home') {
                    app.renderHomeWeatherWidget();
                    app.renderHomeNotice();
                    app.renderEventBanner();
                    app.renderHomeMap();
                    app.renderHomeVolunteerDashboard();
                    app.toggleHomeVisitorExtrasCollapse();
                    app.updateNotificationsBadge();
                }

                if (options.persist !== false) {
                    this.saveRoute(viewId, params);
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
                    const msg = data.message || data.code || i18n.t('auth_login_error');
                    throw new Error(msg);
                }

                if (data.success) {
                    API.persistAuthUser(data.user, data.nonce);
                    this.user = JSON.parse(localStorage.getItem('zelo_user'));

                    const synced = await API.refreshSession();
                    if (synced) {
                        this.user = synced;
                        app.cacheUserAvatar(synced.avatar);
                    }

                    this.updateUI();

                    if (this.user.caps && this.user.caps.view_ops) {
                        app.data.volunteerOps = await app.loadVolunteerOps(true);
                        if (app._opsAuthFailed) {
                            const msg = synced
                                ? i18n.t('auth_login_ops_failed')
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
                    throw new Error(data.message || i18n.t('auth_login_error'));
                }

            } catch (err) {
                console.error('Login error:', err);
                errorEl.textContent = err.message || i18n.t('auth_connection_error');
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
            const langContainer = document.getElementById('register-languages');
            const language_ids = app.getSelectedLanguageIdsFromContainer(langContainer);
            errEl.style.display = 'none';
            btn.disabled = true;
            btn.textContent = i18n.t('loading');
            try {
                await API.registerVolunteer({
                    display_name: name,
                    email,
                    phone,
                    password: pass,
                    language_ids
                });
                errEl.className = 'text-success';
                errEl.style.display = 'block';
                errEl.textContent = i18n.t('auth_register_success');
                document.getElementById('register-password').value = '';
            } catch (e) {
                errEl.className = 'text-danger';
                errEl.textContent = e.message || i18n.t('error_generic');
                errEl.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = i18n.t('auth_create_account');
            }
        },

        logout() {
            this.user = null;
            localStorage.removeItem('zelo_user');
            localStorage.removeItem('zelo_volunteer_ops');
            localStorage.removeItem('zelo_volunteer_ops_mine');
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
                const avatarUrl = app.getAvatarUrl();
                iconContainer.innerHTML = `<img src="${avatarUrl}" alt="" onerror="this.onerror=null;this.src='images/default-avatar.png';">`;
                iconContainer.setAttribute('title', this.user.name || i18n.t('my_profile'));
            } else if (iconContainer) {
                iconContainer.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>`;
                iconContainer.setAttribute('title', i18n.t('auth_sign_in'));
            }

            const pRole = document.getElementById('profile-role');
            const pAvatar = document.getElementById('profile-avatar');

            if (this.user) {
                const avatarUrl = app.getAvatarUrl();
                if (pRole) pRole.textContent = app.getOpsRoleLabel() || this.user.roles[0] || i18n.t('visitor_role');
                if (pAvatar) {
                    pAvatar.src = avatarUrl;
                    pAvatar.onerror = function () {
                        this.onerror = null;
                        this.src = 'images/default-avatar.png';
                    };
                }
                app.populateProfileForm();
            }

            app.updateBottomNavForVolunteer();
            app.updateNotificationsBadge();
            app.renderProfileLanguages();
        },

        updateUI() {
            this.refreshAuthChrome();
        },

        async forceUpdate() {
            if (!confirm(i18n.t('confirm_force_update'))) return;

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
            this._languageCatalogCache = null;
            window.location.reload(true);
        }
    },

    _languageCatalogCache: null,

    async getLanguageCatalog() {
        if (this._languageCatalogCache !== null) {
            return this._languageCatalogCache;
        }
        try {
            const langs = await API.getOpsLanguages();
            this._languageCatalogCache = Array.isArray(langs) ? langs : [];
            return this._languageCatalogCache;
        } catch (e) {
            console.warn('Idiomas', e);
            return [];
        }
    },

    async fillLanguageCheckboxes(containerEl, selectedIds = []) {
        if (!containerEl) {
            return;
        }
        const langs = await this.getLanguageCatalog();
        const selected = new Set((selectedIds || []).map(String));
        containerEl.innerHTML = '';
        langs.forEach((lang) => {
            const label = document.createElement('label');
            label.className = 'language-checkbox-item';

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.value = lang.id;
            input.checked = selected.has(String(lang.id));

            const text = document.createElement('span');
            text.textContent = lang.name;

            label.appendChild(input);
            label.appendChild(text);
            containerEl.appendChild(label);
        });
    },

    getSelectedLanguageIdsFromContainer(containerEl) {
        if (!containerEl) {
            return [];
        }
        return Array.from(containerEl.querySelectorAll('input[type="checkbox"]:checked'))
            .map((el) => el.value)
            .filter(Boolean);
    },

    async loadRegisterLanguages() {
        const container = document.getElementById('register-languages');
        await this.fillLanguageCheckboxes(container, []);
    },

    async renderProfileLanguages() {
        const section = document.getElementById('profile-languages-section');
        const container = document.getElementById('profile-languages');
        if (!this.auth.user) {
            if (section) {
                section.style.display = 'none';
            }
            return;
        }
        if (section) {
            section.style.display = 'block';
        }
        const ids = this.auth.user.language_ids || [];
        await this.fillLanguageCheckboxes(container, ids);
    },

    togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input || !btn) return;
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        const openIcon = btn.querySelector('.icon-eye-open');
        const closedIcon = btn.querySelector('.icon-eye-closed');
        if (openIcon) openIcon.hidden = reveal;
        if (closedIcon) closedIcon.hidden = !reveal;
        btn.setAttribute('aria-label', i18n.t(reveal ? 'password_hide' : 'password_show'));
    },

    applyProfileApiResponse(res) {
        if (res.user) {
            this.auth.user = res.user;
            if (res.nonce) {
                this.auth.user.token = res.nonce;
            }
            localStorage.setItem('zelo_user', JSON.stringify(this.auth.user));
            if (res.user.avatar) {
                this.cacheUserAvatar(res.user.avatar);
            }
            this.auth.refreshAuthChrome();
        }
    },

    populateProfileForm() {
        const user = this.auth.user;
        if (!user) return;
        const nameEl = document.getElementById('profile-input-name');
        const emailEl = document.getElementById('profile-input-email');
        const phoneEl = document.getElementById('profile-input-phone');
        if (nameEl) nameEl.value = user.name || '';
        if (emailEl) emailEl.value = user.email || '';
        if (phoneEl) phoneEl.value = user.phone || '';
        const curPass = document.getElementById('profile-current-password');
        const newPass = document.getElementById('profile-new-password');
        if (curPass) curPass.value = '';
        if (newPass) newPass.value = '';
        this.bindProfileAvatarInput();
        this.renderProfileLanguages();
    },

    bindProfileAvatarInput() {
        const input = document.getElementById('profile-avatar-input');
        if (!input || input._zeloAvatarBound) return;
        input._zeloAvatarBound = true;
        input.addEventListener('change', () => this.uploadProfileAvatar(input));
    },

    showProfileFormMessage(text, type = 'info') {
        const msg = document.getElementById('profile-form-msg');
        if (!msg) return;
        msg.style.display = text ? 'block' : 'none';
        msg.className = 'profile-form-msg' + (type ? ' ' + type : '');
        msg.textContent = text || '';
    },

    async uploadProfileAvatar(inputEl) {
        const file = inputEl && inputEl.files ? inputEl.files[0] : null;
        if (!file) return;
        this.showProfileFormMessage(i18n.t('profile_avatar_uploading'), 'info');
        try {
            const res = await API.uploadProfileAvatar(file);
            this.applyProfileApiResponse(res);
            this.showProfileFormMessage(i18n.t('profile_avatar_saved'), 'success');
        } catch (e) {
            this.showProfileFormMessage(e.message || i18n.t('error_generic'), 'error');
        } finally {
            inputEl.value = '';
        }
    },

    async saveProfile(event) {
        if (event) event.preventDefault();
        const user = this.auth.user;
        if (!user) {
            this.router.navigate('login');
            return;
        }

        const btn = document.getElementById('profile-save-btn');
        const name = (document.getElementById('profile-input-name')?.value || '').trim();
        const email = (document.getElementById('profile-input-email')?.value || '').trim();
        const phone = (document.getElementById('profile-input-phone')?.value || '').trim();
        const currentPassword = document.getElementById('profile-current-password')?.value || '';
        const newPassword = document.getElementById('profile-new-password')?.value || '';
        const langContainer = document.getElementById('profile-languages');
        const language_ids = this.getSelectedLanguageIdsFromContainer(langContainer);

        if (!name || !email) {
            this.showProfileFormMessage(i18n.t('profile_required_fields'), 'error');
            return;
        }
        if (newPassword && newPassword.length < 8) {
            this.showProfileFormMessage(i18n.t('profile_password_min'), 'error');
            return;
        }
        if (newPassword && !currentPassword) {
            this.showProfileFormMessage(i18n.t('profile_current_password_required'), 'error');
            return;
        }

        const payload = { language_ids };
        if (name !== (user.name || '')) payload.display_name = name;
        if (email !== (user.email || '')) payload.email = email;
        if (phone !== (user.phone || '')) payload.phone = phone;
        if (newPassword) {
            payload.current_password = currentPassword;
            payload.new_password = newPassword;
        }

        const hasExtra = payload.display_name || payload.email || payload.phone || payload.new_password;
        if (!hasExtra && JSON.stringify(language_ids) === JSON.stringify(user.language_ids || [])) {
            this.showProfileFormMessage(i18n.t('profile_nothing_changed'), 'info');
            return;
        }

        if (btn) btn.disabled = true;
        this.showProfileFormMessage('', 'info');
        try {
            const res = await API.patchProfile(payload);
            this.applyProfileApiResponse(res);
            if (res.email_pending_verification) {
                this.showProfileFormMessage(i18n.t('profile_email_verify_sent'), 'info');
            } else {
                this.showProfileFormMessage(i18n.t('profile_saved'), 'success');
            }
        } catch (e) {
            this.showProfileFormMessage(e.message || i18n.t('error_generic'), 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    },

    async saveProfileLanguages() {
        return this.saveProfile();
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
            this.syncStaleFlags();

            if (this.auth.user && this.auth.user.caps && this.auth.user.caps.view_ops) {
                const synced = await API.refreshSession();
                if (synced) {
                    this.auth.user = synced;
                    app.cacheUserAvatar(synced.avatar);
                    // force=true: após refreshSession ok, ignorar _opsAuthFailed de tentativa anterior
                    this.data.volunteerOps = await this.loadVolunteerOps(true);
                    if (!this._opsAuthFailed) {
                        this.auth.clearOpsAuthFailure();
                    }
                } else {
                    const cachedOps = await this.loadVolunteerOps(true);
                    if (!cachedOps) {
                        this.auth.handleOpsAuthFailure();
                    }
                }
            }

            console.log('Data loaded', this.data);

            this.auth.refreshAuthChrome();

            this.renderEventBanner();
            this.renderHomeMap();
            this.renderHomeVolunteerDashboard();
            this.renderHomeWeatherWidget();
            this.toggleHomeVisitorExtrasCollapse();
            this.updateNotificationsBadge();

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
            this.router.navigate('email-verified', {}, { persist: false });
        } else {
            this.resolveInitialNavigation();
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

    resolveInitialNavigation() {
        const route = this.router.resolveInitialRoute();
        const { viewId, params } = route;
        if (this.router.canAccessView(viewId)) {
            this.router.navigate(viewId, params);
            return;
        }
        if (viewId === 'escala' || viewId === 'profile') {
            if (!this.auth.user) {
                this.router.navigate('login', {}, { persist: false });
            } else {
                this.router.navigate('home', {}, { persist: false });
            }
            return;
        }
        this.router.navigate('home');
    },

    /**
     * Re-renderiza blocos montados em JS após troca de idioma (data-i18n já foi atualizado).
     */
    refreshViewForLanguage() {
        const viewId = this.router.currentView;
        const params = this.router.lastParams || {};

        switch (viewId) {
            case 'home':
                this.renderHomeWeatherWidget();
                this.renderHomeNotice();
                this.renderEventBanner();
                this.renderHomeMap();
                this.renderHomeVolunteerDashboard();
                this.toggleHomeVisitorExtrasCollapse();
                this.updateNotificationsBadge();
                break;
            case 'lista':
                if (this.data.currentCategory) {
                    this.renderList(
                        this.data.currentCategory,
                        this.data.listPage,
                        this.data.listSearch,
                        this.data.listSort,
                        this.data.listBairro,
                        this.data.listCidade,
                        this.data.listOpenNow
                    );
                }
                break;
            case 'detalhe':
                if (params.id != null) {
                    this.renderDetail(params.id);
                }
                break;
            case 'emergencia':
                this.renderEmergency();
                break;
            case 'evento':
                this.renderEventInfo();
                break;
            case 'tempo':
                this.renderWeather();
                break;
            case 'avisos':
                this.renderAvisos();
                break;
            case 'escala':
                this.renderVolunteerOps();
                break;
            case 'mapa-evento':
                this.renderIndoorEventMap();
                break;
            case 'profile':
                this.populateProfileForm();
                break;
            case 'register':
                this.loadRegisterLanguages();
                break;
            default:
                break;
        }
    },

    // --- Render Methods ---

    getAvisosReadSet() {
        try {
            const raw = localStorage.getItem('zelo_avisos_read');
            const arr = raw ? JSON.parse(raw) : [];
            return new Set(Array.isArray(arr) ? arr : []);
        } catch (e) {
            return new Set();
        }
    },

    saveAvisosReadSet(set) {
        localStorage.setItem('zelo_avisos_read', JSON.stringify(Array.from(set)));
    },

    markAvisoRead(id) {
        const set = this.getAvisosReadSet();
        set.add(id);
        this.saveAvisosReadSet(set);
        this.updateNotificationsBadge();
    },

    markAllAvisosRead() {
        const items = this.buildAvisosFeed();
        const set = this.getAvisosReadSet();
        items.forEach((item) => set.add(item.id));
        this.saveAvisosReadSet(set);
        this.updateNotificationsBadge();
        if (this.router.currentView === 'avisos') {
            this.renderAvisos();
        }
        this.renderHomeNotice();
    },

    getAssignmentStartMs(item) {
        const ops = this.data.volunteerOps;
        const dates = ops?.settings?.event_dates || {};
        const ymd = dates[item.day];
        if (!ymd || !item.start) return null;
        const parts = String(item.start).match(/^(\d{1,2}):(\d{2})/);
        if (!parts) return null;
        const iso = `${ymd}T${parts[1].padStart(2, '0')}:${parts[2]}:00`;
        const t = new Date(iso).getTime();
        return Number.isNaN(t) ? null : t;
    },

    isAssignmentToday(item) {
        const ops = this.data.volunteerOps;
        const dates = ops?.settings?.event_dates || {};
        const ymd = dates[item.day];
        if (!ymd) return false;
        const today = new Date();
        const todayYmd = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        return ymd === todayYmd;
    },

    buildAvisosFeed() {
        const items = [];
        const noticeData = this.data.evento?.info_uteis?.home_notice;
        const noticeActive = noticeData && (noticeData.active == 1 || noticeData.active === true || noticeData.active === 'true');
        if (noticeActive && noticeData.text) {
            items.push({
                id: 'event-notice',
                category: 'event',
                icon: noticeData.type === 'critical' ? '🚨' : noticeData.type === 'warning' ? '⚠️' : '📢',
                title: i18n.t('avisos_event_notice'),
                summary: noticeData.text,
                time: '',
                action: 'event_notice',
                link: noticeData.link || ''
            });
        }

        if (this.canViewOps() && this.data.volunteerOps) {
            const ops = this.data.volunteerOps;
            const uid = this.auth.user?.id;
            const now = Date.now();
            const in24h = now + 24 * 60 * 60 * 1000;
            const myRows = (ops.schedule || []).filter((i) => Number(i.wp_user_id) === Number(uid));

            if (ops.link_pending) {
                items.push({
                    id: 'registration-pending',
                    category: 'personal',
                    icon: '📋',
                    title: i18n.t('avisos_registration_pending'),
                    summary: i18n.t('ops_link_pending'),
                    time: '',
                    action: 'profile'
                });
            }

            myRows.forEach((item) => {
                const commitSt = this.getCommitmentStatus(item.id);
                const presenceSt = this.getCheckinStatus(item.id).status || 'pending';

                if (commitSt === 'pending' && this.canCommitAssignment(item)) {
                    const dl = ops.settings?.commitment_deadline || '';
                    const scheduleChanged = this.getCommitmentPendingReason(item.id) === 'schedule_changed';
                    const timePart = `${item.start || ''}${item.end ? ' – ' + item.end : ''}`;
                    items.push({
                        id: scheduleChanged ? `schedule-changed-${item.id}` : `commitment-${item.id}`,
                        category: 'personal',
                        icon: scheduleChanged ? '🔄' : '📌',
                        title: i18n.t(scheduleChanged ? 'avisos_schedule_changed' : 'avisos_commitment_pending'),
                        summary: scheduleChanged
                            ? i18n.t('avisos_schedule_changed_summary')
                                .replace('{0}', this.getOpsDayLabel(item.day))
                                .replace('{1}', item.shift || '')
                                .replace('{2}', item.location || '')
                                .replace('{3}', timePart)
                            : `${this.getOpsDayLabel(item.day)} · ${item.shift || ''} — ${item.location || ''}${dl ? ` (até ${dl})` : ''}`,
                        time: item.start || '',
                        action: 'escala'
                    });
                }

                if (commitSt === 'accepted' && presenceSt === 'pending' && this.canCheckinAssignment(item)) {
                    items.push({
                        id: `checkin-${item.id}`,
                        category: 'personal',
                        icon: '✅',
                        title: i18n.t('avisos_checkin_pending'),
                        summary: `${this.getOpsDayLabel(item.day)} · ${item.shift || ''} — ${item.location || ''} (${item.start || ''}${item.end ? ' – ' + item.end : ''})`,
                        time: item.start || '',
                        action: 'escala'
                    });
                }

                if (commitSt === 'accepted' && presenceSt === 'checked_in' && this.canCheckoutAssignment(item)) {
                    items.push({
                        id: `checkout-${item.id}`,
                        category: 'personal',
                        icon: '🚪',
                        title: i18n.t('avisos_checkout_pending'),
                        summary: `${this.getOpsDayLabel(item.day)} · ${item.shift || ''} — ${item.location || ''}`,
                        time: item.end || '',
                        action: 'escala'
                    });
                }

                const startMs = this.getAssignmentStartMs(item);
                if (commitSt === 'accepted' && startMs != null && startMs >= now && startMs <= in24h) {
                    items.push({
                        id: `shift-next-${item.id}`,
                        category: 'personal',
                        icon: '🕐',
                        title: i18n.t('avisos_shift_reminder'),
                        summary: `${this.getOpsDayLabel(item.day)} · ${item.shift || ''} — ${item.location || ''} (${item.start || ''}${item.end ? ' – ' + item.end : ''})`,
                        time: item.start || '',
                        action: 'escala'
                    });
                }
            });

            (ops.recent_declines || []).forEach((d) => {
                const row = d.row || {};
                items.push({
                    id: `decline-${d.assignment_id}`,
                    category: 'personal',
                    icon: '⚠️',
                    title: i18n.t('avisos_decline_supervisor'),
                    summary: `${row.volunteer_name || ''} — ${this.getOpsDayLabel(row.day)} ${row.shift || ''}`,
                    time: '',
                    action: 'escala'
                });
            });

            if (this.canManageOps() || this.canReallocateOps()) {
                (ops.swap_requests || []).filter((s) => s.status === 'pending').forEach((s) => {
                    items.push({
                        id: `swap-${s.id}`,
                        category: 'personal',
                        icon: '🔄',
                        title: i18n.t('avisos_swap_pending'),
                        summary: i18n.t('avisos_swap_summary').replace('{0}', s.assignment_id || ''),
                        time: '',
                        action: 'escala'
                    });
                });
            }
        }

        return items;
    },

    updateNotificationsBadge() {
        const badge = document.getElementById('header-notifications-badge');
        if (!badge) return;
        const read = this.getAvisosReadSet();
        const unread = this.buildAvisosFeed().filter((i) => !read.has(i.id)).length;
        if (unread > 0) {
            badge.hidden = false;
            badge.textContent = unread > 9 ? '9+' : String(unread);
        } else {
            badge.hidden = true;
        }
    },

    _avisosFilter: 'all',

    handleAvisoClick(id) {
        const items = this.buildAvisosFeed();
        const item = items.find((i) => i.id === id);
        if (!item) return;
        this.markAvisoRead(id);
        if (item.action === 'escala') {
            this.openVolunteerOps();
        } else if (item.action === 'event_notice') {
            if (item.link && item.link !== '#') {
                window.open(item.link, '_blank');
            } else {
                this.router.navigate('evento');
            }
        }
    },

    renderAvisos() {
        const container = document.getElementById('avisos-container');
        if (!container) return;

        const read = this.getAvisosReadSet();
        let items = this.buildAvisosFeed();
        const showPersonal = this.canViewOps() && this.auth.user;

        if (this._avisosFilter === 'personal' && showPersonal) {
            items = items.filter((i) => i.category === 'personal');
        } else if (this._avisosFilter === 'event') {
            items = items.filter((i) => i.category === 'event');
        }

        const chips = `
            <div class="avisos-toolbar">
                <button type="button" class="avisos-filter-chip ${this._avisosFilter === 'all' ? 'active' : ''}" onclick="app._avisosFilter='all';app.renderAvisos();">${this.escapeHtml(i18n.t('avisos_filter_all'))}</button>
                ${showPersonal ? `<button type="button" class="avisos-filter-chip ${this._avisosFilter === 'personal' ? 'active' : ''}" onclick="app._avisosFilter='personal';app.renderAvisos();">${this.escapeHtml(i18n.t('avisos_filter_personal'))}</button>` : ''}
                <button type="button" class="avisos-filter-chip ${this._avisosFilter === 'event' ? 'active' : ''}" onclick="app._avisosFilter='event';app.renderAvisos();">${this.escapeHtml(i18n.t('avisos_filter_event'))}</button>
                ${items.length ? `<button type="button" class="avisos-mark-all" onclick="app.markAllAvisosRead()">${this.escapeHtml(i18n.t('avisos_mark_all_read'))}</button>` : ''}
            </div>
        `;

        if (!items.length) {
            container.innerHTML = chips + `<div class="avisos-empty">${this.escapeHtml(i18n.t('avisos_empty'))}</div>`;
            this.updateNotificationsBadge();
            return;
        }

        const list = items.map((item) => {
            const unread = !read.has(item.id);
            return `
                <button type="button" class="avisos-item ${unread ? 'unread' : ''}" onclick="app.handleAvisoClick('${String(item.id).replace(/'/g, "\\'")}')">
                    <span class="avisos-item-icon">${item.icon}</span>
                    <span class="avisos-item-body">
                        <div class="avisos-item-title">${this.escapeHtml(item.title)}</div>
                        <div class="avisos-item-summary">${this.escapeHtml(item.summary)}</div>
                        ${item.time ? `<div class="avisos-item-meta">${this.escapeHtml(item.time)}</div>` : ''}
                    </span>
                </button>
            `;
        }).join('');

        container.innerHTML = chips + `<div class="avisos-list">${list}</div>`;
        this.updateNotificationsBadge();
    },

    renderHomeWeatherWidget() {
        const el = document.getElementById('home-weather-widget');
        if (!el) return;

        const data = this.data.clima;
        if (!data || data.enabled === false || !data.current) {
            el.hidden = true;
            el.innerHTML = '';
            return;
        }

        const cur = data.current;
        const loc = data.location || {};
        const stale = !!data.stale || !navigator.onLine;

        el.hidden = false;
        el.onclick = () => this.router.navigate('tempo');
        el.innerHTML = `
            <span class="home-weather-widget-icon">${this.getWeatherIconSvg(cur.icon, 40)}</span>
            <span class="home-weather-widget-main">
                <span class="home-weather-widget-temp">${cur.temp_c != null ? cur.temp_c : '—'}° · ${this.escapeHtml(cur.label || '')}</span>
                <span class="home-weather-widget-label">${stale ? this.escapeHtml(i18n.t('weather_stale')) : this.escapeHtml(i18n.t('weather_widget_tap'))}</span>
                ${loc.name ? `<span class="home-weather-widget-loc">${this.escapeHtml(loc.name)}</span>` : ''}
            </span>
            <span class="home-weather-widget-chevron">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </span>
        `;
    },

    toggleHeaderMenu() {
        const sheet = document.getElementById('header-menu-sheet');
        const backdrop = document.getElementById('header-menu-backdrop');
        const btn = document.getElementById('header-menu-btn');
        if (!sheet || !backdrop) return;
        const open = sheet.hidden;
        sheet.hidden = !open;
        backdrop.hidden = !open;
        if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    },

    closeHeaderMenu() {
        const sheet = document.getElementById('header-menu-sheet');
        const backdrop = document.getElementById('header-menu-backdrop');
        const btn = document.getElementById('header-menu-btn');
        if (sheet) sheet.hidden = true;
        if (backdrop) backdrop.hidden = true;
        if (btn) btn.setAttribute('aria-expanded', 'false');
    },

    updateInstallMenuItem() {
        const item = document.getElementById('header-menu-install');
        if (!item) return;
        item.hidden = !this.data.installPrompt;
    },

    triggerInstallPwa() {
        const prompt = this.data.installPrompt;
        if (!prompt) return;
        prompt.prompt();
        prompt.userChoice.then(() => {
            this.data.installPrompt = null;
            this.updateInstallMenuItem();
        });
    },

    renderHomeNotice() {
        const container = document.getElementById('home-notice-container');
        const noticeData = app.data.evento?.info_uteis?.home_notice;

        if (!container) return;

        if (!noticeData) {
            container.innerHTML = '';
            return;
        }

        const isActive = noticeData.active == 1 || noticeData.active === true || noticeData.active === 'true';

        if (!isActive) {
            container.innerHTML = '';
            return;
        }

        const type = noticeData.type || 'info';
        const text = noticeData.text || '';
        const read = this.getAvisosReadSet();

        if (type === 'info' && read.has('event-notice')) {
            container.innerHTML = '';
            return;
        }

        if (type === 'info') {
            container.innerHTML = '';
            return;
        }

        const icon = type === 'warning' ? '⚠️' : '🚨';
        container.innerHTML = `
            <a href="#" class="home-notice-strip ${type}" onclick="app.router.navigate('avisos'); return false;">
                <span>${icon}</span>
                <span class="home-notice-strip-text">${this.escapeHtml(text)}</span>
                <span class="home-notice-strip-link">${this.escapeHtml(i18n.t('avisos_view_all'))}</span>
            </a>
        `;
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
        return false;
    },

    canSuperviseOps() {
        if (this.canManageOps() || this.canReallocateOps()) return true;
        const roles = this.auth.user?.roles || [];
        if (roles.includes('zelo_supervisor_grupo') || roles.includes('zelo_supervisor_app')) return true;
        return !!(this.data.volunteerOps?.permissions?.supervise_ops);
    },

    canEditSchedule() {
        if (this.canManageOps()) return true;
        const edit = this.data.volunteerOps?.permissions?.schedule_edit;
        return !!(this.auth.user?.caps?.edit_schedule && edit && edit.enabled);
    },

    canEditScheduleScope(day, shift) {
        const edit = this.data.volunteerOps?.permissions?.schedule_edit;
        if (!this.canEditSchedule() || !edit) return false;
        if (edit.all) return true;
        return (edit.scopes || []).some((s) => s.day === day && s.shift === shift);
    },

    canShowOpsGovernance() {
        return this.canSuperviseOps() || this.canManageOps();
    },

    isCommitmentDeadlinePassed() {
        return !!(this.data.volunteerOps && this.data.volunteerOps.commitment_deadline_passed);
    },

    getCommitmentStatus(assignmentId) {
        const c = this.data.volunteerOps?.commitments?.[assignmentId];
        return (c && c.status) ? c.status : 'pending';
    },

    getCommitmentPendingReason(assignmentId) {
        const c = this.data.volunteerOps?.commitments?.[assignmentId];
        return (c && c.pending_reason) ? String(c.pending_reason) : '';
    },

    getCommitmentLabel(status, assignmentId) {
        if (status === 'accepted') return i18n.t('ops_commitment_accepted');
        if (status === 'declined') return i18n.t('ops_commitment_declined');
        if (status === 'pending' && assignmentId && this.getCommitmentPendingReason(assignmentId) === 'schedule_changed') {
            return i18n.t('ops_schedule_changed_confirm');
        }
        return i18n.t('ops_commitment_pending');
    },

    canCommitAssignment(item) {
        if (!item || !item.id) return false;
        if (this.getCommitmentStatus(item.id) !== 'pending') return false;
        const uid = this.auth.user?.id;
        const mine = Number(item.wp_user_id) === Number(uid);
        if (mine && !this.isCommitmentDeadlinePassed()) return true;
        return this.canSuperviseOps();
    },

    _parsePresenceRuleMs(rule, startMs, endMs) {
        if (!rule || startMs == null) return startMs;
        if (rule === 'shift_start') return startMs;
        if (rule === 'shift_end') return endMs;
        if (rule === 'day_before') return startMs - 86400000;
        const m = String(rule).match(/^minutes_before:(\d+)$/);
        if (m) return startMs - parseInt(m[1], 10) * 60000;
        const m2 = String(rule).match(/^minutes_after_end:(\d+)$/);
        if (m2 && endMs != null) return endMs + parseInt(m2[1], 10) * 60000;
        return startMs;
    },

    isAssignmentEventDay(item) {
        const dates = this.data.volunteerOps?.settings?.event_dates || {};
        const ymd = dates[item.day];
        if (!ymd) return false;
        const today = new Date();
        const todayYmd = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        return ymd === todayYmd;
    },

    canCheckinAssignment(item) {
        if (this.getCommitmentStatus(item.id) !== 'accepted') return false;
        if (!this.isAssignmentEventDay(item)) return false;
        const presenceSt = this.getCheckinStatus(item.id).status || 'pending';
        if (presenceSt !== 'pending') return false;
        const uid = this.auth.user?.id;
        const mine = Number(item.wp_user_id) === Number(uid);
        if (!mine && !this.canSuperviseOps()) return false;
        const startMs = this.getAssignmentStartMs(item);
        if (startMs == null) return false;
        const endMs = item.end ? this.getAssignmentEndMs(item, startMs) : null;
        const presence = this.data.volunteerOps?.settings?.presence || {};
        const from = this._parsePresenceRuleMs(presence.checkin_from || 'shift_start', startMs, endMs);
        const until = presence.checkin_until === 'shift_end' && endMs != null ? endMs : this._parsePresenceRuleMs(presence.checkin_until || 'shift_end', startMs, endMs);
        const now = Date.now();
        return from != null && until != null && now >= from && now <= until;
    },

    getAssignmentEndMs(item, startMs) {
        if (startMs == null) return null;
        const parts = String(item.end || '').match(/^(\d{1,2}):(\d{2})/);
        if (!parts) return startMs + 4 * 3600000;
        const d = new Date(startMs);
        d.setHours(parseInt(parts[1], 10), parseInt(parts[2], 10), 0, 0);
        return d.getTime();
    },

    canCheckoutAssignment(item) {
        if (this.getCommitmentStatus(item.id) !== 'accepted') return false;
        if (!this.isAssignmentEventDay(item)) return false;
        if (this.getCheckinStatus(item.id).status !== 'checked_in') return false;
        const uid = this.auth.user?.id;
        const mine = Number(item.wp_user_id) === Number(uid);
        if (!mine && !this.canSuperviseOps()) return false;
        const startMs = this.getAssignmentStartMs(item);
        if (startMs == null) return false;
        const endMs = this.getAssignmentEndMs(item, startMs);
        const presence = this.data.volunteerOps?.settings?.presence || {};
        const from = this._parsePresenceRuleMs(presence.checkout_from || 'shift_end', startMs, endMs);
        const until = this._parsePresenceRuleMs(presence.checkout_until || 'minutes_after_end:30', startMs, endMs);
        const now = Date.now();
        return from != null && until != null && now >= from && now <= until;
    },

    canActPresenceOn(item, onBehalf) {
        const uid = this.auth.user?.id;
        if (Number(item.wp_user_id) === Number(uid)) return true;
        return onBehalf && this.canSuperviseOps();
    },

    async promptPushNotifications() {
        if (!('Notification' in window) || !('serviceWorker' in navigator)) return;
        if (localStorage.getItem('zelo_push_prompted') === '1') return;
        if (Notification.permission === 'granted') {
            localStorage.setItem('zelo_push_prompted', '1');
            return;
        }
        if (Notification.permission === 'denied') return;
        const ok = confirm(`${i18n.t('ops_push_prompt')}\n\n${i18n.t('ops_push_prompt_body')}`);
        localStorage.setItem('zelo_push_prompted', '1');
        if (!ok) return;
        try {
            await Notification.requestPermission();
        } catch (e) {
            console.warn('Push permission', e);
        }
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
        this._volunteerOpsPromise = (async () => {
            const data = await API.getVolunteerOps(false);
            if (data && data.__authError) {
                this.auth.handleOpsAuthFailure();
                return null;
            }
            if (data) {
                this.data.volunteerOps = data;
                this._opsAuthFailed = false;
                this.syncStaleFlags();
                app.updateNotificationsBadge();
            } else if (!force) {
                const fallback = API.readSnapshot('zelo_volunteer_ops');
                if (fallback) {
                    this.data.volunteerOps = fallback;
                    API.lastFetchFromCache.volunteerOps = true;
                    this.syncStaleFlags();
                }
            }
            return data || this.data.volunteerOps;
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

    getCommitmentBadge(assignmentId) {
        const st = this.getCommitmentStatus(assignmentId);
        const cls = st === 'accepted' ? 'ops-commit-accepted' : st === 'declined' ? 'ops-commit-declined' : 'ops-commit-pending';
        return `<span class="ops-status-badge ${cls}">${this.escapeHtml(this.getCommitmentLabel(st, assignmentId))}</span>`;
    },

    opsActionIconSvg(kind) {
        const stroke = 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
        const icons = {
            accept: `<svg class="ops-icon-btn__svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" ${stroke} aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>`,
            decline: `<svg class="ops-icon-btn__svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" ${stroke} aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`,
            checkin: `<svg class="ops-icon-btn__svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" ${stroke} aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>`,
            checkout: `<svg class="ops-icon-btn__svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" ${stroke} aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>`,
            reallocate: `<svg class="ops-icon-btn__svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" ${stroke} aria-hidden="true"><path d="M16 3h5v5"></path><path d="M8 21H3v-5"></path><path d="M21 8l-9 9"></path><path d="M3 16l9-9"></path></svg>`,
            swap: `<svg class="ops-icon-btn__svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" ${stroke} aria-hidden="true"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 5h18"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 19H3"></path></svg>`
        };
        return icons[kind] || icons.accept;
    },

    renderOpsIconButton(i18nKey, onclick, iconKind, variant = 'primary') {
        const label = this.escapeHtml(i18n.t(i18nKey));
        return `<button type="button" class="ops-icon-btn ops-icon-btn--${variant}" aria-label="${label}" title="${label}" onclick="${onclick}">${this.opsActionIconSvg(iconKind)}</button>`;
    },

    renderAssignmentActions(item, forSupervisorRow, layout = false) {
        const idEsc = String(item.id).replace(/'/g, "\\'");
        const commitSt = this.getCommitmentStatus(item.id);
        const presenceSt = this.getCheckinStatus(item.id).status || 'pending';
        const onBehalf = forSupervisorRow && this.canSuperviseOps() && Number(item.wp_user_id) !== Number(this.auth.user?.id);
        const iconBar = layout === 'icons';
        const compactRow = iconBar || layout === 'compact' || layout === true;
        const btnPrimary = compactRow ? 'btn-assignment-action' : 'btn-block btn-block--compact';
        const btnMuted = compactRow ? 'btn-assignment-action btn-assignment-action--muted' : 'btn-block btn-block--compact btn-block--muted';
        let html = '';
        let hintHtml = '';

        if (commitSt === 'pending') {
            if (this.canCommitAssignment(item)) {
                if (!onBehalf && this.getCommitmentPendingReason(item.id) === 'schedule_changed') {
                    const note = `<p class="text-muted ops-volunteer-hint">${this.escapeHtml(i18n.t('ops_schedule_changed_hint'))}</p>`;
                    if (iconBar) hintHtml += note;
                    else html += `<p class="text-muted home-assignment-note">${this.escapeHtml(i18n.t('ops_schedule_changed_hint'))}</p>`;
                }
                const acceptKey = onBehalf ? 'ops_commit_on_behalf' : 'ops_commit_accept';
                if (iconBar) {
                    html += this.renderOpsIconButton(acceptKey, `app.doCommit('${idEsc}', true, ${onBehalf})`, 'accept', 'primary');
                    if (!onBehalf) {
                        html += this.renderOpsIconButton('ops_commit_decline', `app.doCommit('${idEsc}', false, false)`, 'decline', 'muted');
                    }
                } else {
                    html += `<button type="button" class="${btnPrimary}" onclick="app.doCommit('${idEsc}', true, ${onBehalf})">${i18n.t(acceptKey)}</button>`;
                    if (!onBehalf) {
                        html += `<button type="button" class="${btnMuted}" onclick="app.doCommit('${idEsc}', false, false)">${i18n.t('ops_commit_decline')}</button>`;
                    }
                }
            } else if (this.isCommitmentDeadlinePassed()) {
                const note = `<p class="text-muted ops-volunteer-hint">${this.escapeHtml(i18n.t('ops_commitment_deadline_passed'))}</p>`;
                if (iconBar) hintHtml += note;
                else html += `<p class="text-muted home-assignment-note">${this.escapeHtml(i18n.t('ops_commitment_deadline_passed'))}</p>`;
            }
        }

        if (commitSt === 'accepted' && presenceSt === 'pending' && this.canCheckinAssignment(item) && this.canActPresenceOn(item, onBehalf)) {
            if (iconBar) {
                html += this.renderOpsIconButton('ops_quick_checkin', `app.doCheckin('${idEsc}', ${onBehalf})`, 'checkin', 'primary');
            } else {
                html += `<button type="button" class="${btnPrimary}" onclick="app.doCheckin('${idEsc}', ${onBehalf})">${i18n.t('ops_quick_checkin')}</button>`;
            }
        }
        if (commitSt === 'accepted' && presenceSt === 'checked_in' && this.canCheckoutAssignment(item) && this.canActPresenceOn(item, onBehalf)) {
            if (iconBar) {
                html += this.renderOpsIconButton('ops_status_checked_out', `app.doCheckout('${idEsc}', ${onBehalf})`, 'checkout', 'primary');
            } else {
                html += `<button type="button" class="${btnPrimary}" onclick="app.doCheckout('${idEsc}', ${onBehalf})">${i18n.t('ops_status_checked_out')}</button>`;
            }
        }
        if (iconBar) {
            return { actions: html, hint: hintHtml };
        }
        if (compactRow && html) {
            return `<div class="home-assignment-actions">${html}</div>`;
        }
        return html;
    },

    async doCommit(assignmentId, accept, onBehalf = false) {
        try {
            const reason = accept ? '' : (prompt(i18n.t('ops_decline_reason')) || '');
            const result = await API.commitAssignment(assignmentId, accept ? 'accepted' : 'declined', reason, onBehalf);
            if (result.data) this.data.volunteerOps = result.data;
            else if (result.commitments) {
                if (this.data.volunteerOps) this.data.volunteerOps.commitments = result.commitments;
            }
            this.renderVolunteerOps();
            if (this.router.currentView === 'home') this.renderHomeVolunteerDashboard();
            this.updateNotificationsBadge();
        } catch (err) {
            alert(err.message || i18n.t('ops_commit_fail'));
        }
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
        const pref = localStorage.getItem('zelo_home_extras_open');
        if (pref === '0') {
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
        this.promptPushNotifications();

        const ops = this.data.volunteerOps;
        const uid = this.auth.user.id;
        const schedule = (ops && Array.isArray(ops.schedule)) ? ops.schedule : [];
        const myRows = schedule.filter((i) => Number(i.wp_user_id) === uid);

        let linkBanner = '';
        if (ops && ops.link_pending) {
            linkBanner = `<p class="home-ops-link-pending" style="background:#fef3c7;padding:0.75rem;border-radius:8px;font-size:0.9rem;">${i18n.t('ops_link_pending')}</p>`;
        }

        let assignmentsHtml = '';
        if (!myRows.length) {
            assignmentsHtml = `<p class="text-muted">${i18n.t('ops_no_assignments')}</p>`;
        } else {
            assignmentsHtml = myRows.map((item) => {
                const actions = this.renderAssignmentActions(item, false, true);
                const dayName = this.escapeHtml(this.getOpsDayName(item.day));
                const dayDate = this.getOpsDayDateShort(item.day);
                const dayLine = dayDate ? `${dayName} · ${this.escapeHtml(dayDate)}` : dayName;
                const shift = this.escapeHtml(item.shift || '—');
                const location = this.escapeHtml(item.location || '—');
                const timeRange = this.escapeHtml(this.formatAssignmentTimeRange(item));
                const meta = timeRange ? `${location} · ${timeRange}` : location;
                return `
                    <div class="home-volunteer-assignment">
                        <div class="home-volunteer-assignment-title">
                            <span class="home-assignment-day">${dayLine}</span>
                            <span class="home-assignment-shift">${this.escapeHtml(i18n.t('ops_shift_label'))} ${shift}</span>
                        </div>
                        <p class="home-assignment-meta">${meta}</p>
                        <div class="home-assignment-badges">
                            ${this.getCommitmentBadge(item.id)} ${this.getOpsStatusBadge(item.id)}
                        </div>
                        ${actions}
                    </div>
                `;
            }).join('');
        }

        container.innerHTML = `
            <h2>${i18n.t('ops_dashboard_title')}, ${name}</h2>
            <p class="volunteer-role-label">${roleLabel}</p>
            ${linkBanner}
            <h3 style="font-size:0.95rem;margin:0 0 0.5rem;">${i18n.t('ops_my_assignments')}</h3>
            ${assignmentsHtml}
            <div class="home-volunteer-dashboard-actions">
                <button type="button" class="btn-block" onclick="app.openVolunteerOps()">${i18n.t('ops_view_full_schedule')}</button>
            </div>
            <a href="#" class="profile-link" onclick="app.router.navigate('profile'); return false;">${i18n.t('my_profile')}</a>
        `;
        this.updateNotificationsBadge();
    },

    async renderIndoorEventMap() {
        const el = document.getElementById('indoor-map-container');
        if (!el) return;
        el.innerHTML = `<div class="loading">${i18n.t('loading')}</div>`;
        const cfg = await API.getIndoorMap();
        this.data.indoorMap = cfg;
        if (!cfg || !cfg.image_url) {
            el.innerHTML = `<p class="text-muted indoor-map-empty">${i18n.t('indoor_not_configured')}</p>`;
            return;
        }
        if (!this.data.indoorMapUi) {
            this.data.indoorMapUi = { boothId: '', destId: '', tab: 'guide', query: '', comboboxOpen: false, comboboxEditing: false };
        }
        const ui = this.data.indoorMapUi;
        if (ui.comboboxOpen === undefined) ui.comboboxOpen = false;
        if (ui.comboboxEditing === undefined) ui.comboboxEditing = false;
        const places = Array.isArray(cfg.places) ? cfg.places : [];
        const booths = places.filter((p) => p.kind === 'booth');
        const dests = places.filter((p) => p.kind !== 'booth');
        if (!ui.boothId && booths.length) ui.boothId = booths[0].id;
        if (ui.destId && !dests.find((d) => d.id === ui.destId)) ui.destId = '';

        const boothOpts = booths.map((b) => {
            const lab = this.indoorPlaceLabel(b);
            const sel = b.id === ui.boothId ? ' selected' : '';
            return `<option value="${this.escapeAttr(b.id)}"${sel}>${this.escapeHtml(lab)}</option>`;
        }).join('');

        const destInputValue = this.getIndoorDestInputDisplayValue(ui, dests);
        const comboboxOpenClass = ui.comboboxOpen ? ' is-open' : '';
        const listHtml = this.buildIndoorDestDropdownHtml(dests, ui);

        const dirText = this.getIndoorDirections(cfg, ui.boothId, ui.destId);
        const notice = cfg.volunteer_notice || {};
        const lang = i18n.current || 'pt_br';
        const noticeText = notice[lang] || notice.pt_br || '';

        const guideTabActive = ui.tab === 'guide';
        const mapTabActive = ui.tab === 'map';

        let pinsHtml = '';
        places.forEach((p) => {
            const x = typeof p.x === 'number' ? p.x : parseFloat(p.x) || 0;
            const y = typeof p.y === 'number' ? p.y : parseFloat(p.y) || 0;
            const isBooth = p.kind === 'booth';
            const isSel = p.id === ui.destId || p.id === ui.boothId;
            const lab = this.indoorPlaceLabel(p);
            const pinCls = isBooth ? this.indoorBoothPinClasses(p, booths) : 'indoor-pin dest';
            const boothSlot = isBooth ? this.indoorBoothSlot(p, booths) : 0;
            const pinLabel = isBooth && boothSlot ? `<span class="indoor-pin-label" aria-hidden="true">${boothSlot}</span>` : '';
            const ariaLabel = isBooth && boothSlot ? `${lab} (${i18n.t('indoor_legend_booth_' + boothSlot)})` : lab;
            pinsHtml += `<button type="button" class="${pinCls}${isSel ? ' selected' : ''}" style="left:${x * 100}%;top:${y * 100}%;" title="${this.escapeAttr(lab)}" aria-label="${this.escapeAttr(ariaLabel)}" onclick="app.onIndoorPinClick('${this.escapeAttr(p.id)}','${isBooth ? 'booth' : 'dest'}')">${pinLabel}</button>`;
        });

        const legendHtml = this.buildIndoorMapLegendHtml(booths);

        el.innerHTML = `
            <div class="indoor-map-screen">
                <div class="indoor-map-tabs">
                    <button type="button" class="indoor-map-tab${guideTabActive ? ' active' : ''}" onclick="app.setIndoorTab('guide')">${i18n.t('indoor_tab_guide')}</button>
                    <button type="button" class="indoor-map-tab${mapTabActive ? ' active' : ''}" onclick="app.setIndoorTab('map')">${i18n.t('indoor_tab_map')}</button>
                </div>
                <div class="indoor-panel"${guideTabActive ? '' : ' hidden'}>
                    <label class="indoor-field-label">${i18n.t('indoor_booth_from')}</label>
                    <select class="form-input indoor-booth-select" onchange="app.setIndoorBooth(this.value)">${boothOpts || `<option value="">${i18n.t('indoor_select_booth')}</option>`}</select>
                    <label class="indoor-field-label" for="indoor-dest-input">${i18n.t('indoor_dest_search')}</label>
                    <div class="indoor-dest-combobox${comboboxOpenClass}" id="indoor-dest-combobox">
                        <input type="text" id="indoor-dest-input" class="form-input indoor-dest-input" autocomplete="off" autocorrect="off" spellcheck="false"
                            role="combobox" aria-expanded="${ui.comboboxOpen ? 'true' : 'false'}" aria-controls="indoor-dest-dropdown" aria-autocomplete="list"
                            placeholder="${this.escapeAttr(i18n.t('indoor_dest_combobox_placeholder'))}" value="${this.escapeAttr(destInputValue)}" />
                        <button type="button" class="indoor-dest-combobox-toggle" aria-label="${this.escapeAttr(i18n.t('indoor_dest_combobox_toggle'))}" tabindex="-1">▾</button>
                        <div class="indoor-dest-dropdown" id="indoor-dest-dropdown" role="listbox">${listHtml}</div>
                    </div>
                    <div class="indoor-directions-panel" id="indoor-directions-panel">
                        <h3 class="indoor-directions-title">${i18n.t('indoor_directions')}</h3>
                        <p class="indoor-directions-text">${dirText ? this.escapeHtml(dirText).replace(/\n/g, '<br>') : i18n.t('indoor_no_route')}</p>
                        ${dirText ? `<button type="button" class="btn-block outline" style="margin-top:0.75rem;" onclick="app.copyIndoorDirections()">${i18n.t('indoor_copy_directions')}</button>` : ''}
                    </div>
                    ${noticeText ? `<p class="indoor-notice">${this.escapeHtml(noticeText)}</p>` : ''}
                </div>
                <div class="indoor-panel indoor-diagram-panel${mapTabActive ? '' : ' hidden'}">
                    <div class="indoor-diagram-actions">
                        <button type="button" class="indoor-diagram-btn" onclick="app.indoorDiagramFitAll()">${i18n.t('indoor_map_fit_all')}</button>
                        <button type="button" class="indoor-diagram-btn primary"${ui.destId || ui.boothId ? '' : ' disabled'} onclick="app.indoorDiagramGoToDest()">${i18n.t('indoor_map_go_dest')}</button>
                    </div>
                    <p class="indoor-map-hint">${i18n.t('indoor_map_pinch_hint')}</p>
                    ${legendHtml}
                    <div class="indoor-map-viewport" id="indoor-map-viewport">
                        <div class="indoor-map-transform" id="indoor-map-transform">
                            <div class="indoor-map-wrap">
                                <img src="${cfg.image_url}" alt="" class="indoor-map-img" id="indoor-map-img" />
                                <div class="indoor-pins-layer">${pinsHtml}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
        this._indoorDirectionsText = dirText || '';
        this._syncIndoorDiagramFullscreen(ui);
        if (guideTabActive) {
            this.initIndoorDestCombobox(dests);
        }
        if (mapTabActive) {
            requestAnimationFrame(() => {
                requestAnimationFrame(() => this.initIndoorDiagramGestures(cfg, places));
            });
        }
    },

    indoorBoothSlot(place, booths) {
        if (!place || place.kind !== 'booth') return 0;
        const slot = parseInt(place.booth_slot, 10);
        if (slot === 1 || slot === 2) return slot;
        const list = Array.isArray(booths) ? booths : [];
        const idx = list.findIndex((b) => b.id === place.id);
        return idx >= 0 ? idx + 1 : 1;
    },

    indoorBoothPinClasses(place, booths) {
        const slot = this.indoorBoothSlot(place, booths);
        if (slot === 2) return 'indoor-pin booth booth-2';
        return 'indoor-pin booth booth-1';
    },

    buildIndoorMapLegendHtml(booths) {
        const list = Array.isArray(booths) ? booths : [];
        const b1 = list.find((b) => this.indoorBoothSlot(b, list) === 1) || list[0];
        const b2 = list.find((b) => this.indoorBoothSlot(b, list) === 2) || list[1];
        const lab1 = b1 ? this.escapeHtml(this.indoorPlaceLabel(b1)) : i18n.t('indoor_legend_booth_1');
        const lab2 = b2 ? this.escapeHtml(this.indoorPlaceLabel(b2)) : i18n.t('indoor_legend_booth_2');
        let items = `<span class="indoor-legend-item"><span class="indoor-legend-swatch booth-1" aria-hidden="true">1</span>${lab1}</span>`;
        if (b2) {
            items += `<span class="indoor-legend-item"><span class="indoor-legend-swatch booth-2" aria-hidden="true">2</span>${lab2}</span>`;
        }
        items += `<span class="indoor-legend-item"><span class="indoor-legend-swatch dest" aria-hidden="true"></span>${i18n.t('indoor_legend_dest')}</span>`;
        return `<div class="indoor-map-legend" role="note">${items}</div>`;
    },

    filterIndoorDestinations(dests, query) {
        const list = Array.isArray(dests) ? dests : [];
        const q = (query || '').toLowerCase().trim();
        if (!q) return list;
        return list.filter((d) => {
            const lab = this.indoorPlaceLabel(d).toLowerCase();
            const kws = Array.isArray(d.keywords) ? d.keywords.join(' ').toLowerCase() : '';
            return lab.includes(q) || kws.includes(q);
        });
    },

    getIndoorDestInputDisplayValue(ui, dests) {
        if (!ui) return '';
        if (ui.comboboxEditing || ui.comboboxOpen) {
            return ui.query || '';
        }
        if (ui.destId) {
            const d = (dests || []).find((x) => x.id === ui.destId);
            if (d) return this.indoorPlaceLabel(d);
        }
        return ui.query || '';
    },

    buildIndoorDestDropdownHtml(dests, ui) {
        const filtered = this.filterIndoorDestinations(dests, ui && ui.comboboxOpen ? ui.query : '');
        if (!filtered.length) {
            return `<p class="indoor-dest-empty">${i18n.t('indoor_no_destinations_match')}</p>`;
        }
        return filtered.map((d) => {
            const lab = this.indoorPlaceLabel(d);
            const active = ui && d.id === ui.destId ? ' indoor-dest-active' : '';
            const floorMeta = d.floor ? `<span class="indoor-dest-floor-label">${this.escapeHtml(i18n.t('indoor_floor_label'))}</span><span class="indoor-dest-floor">${this.escapeHtml(d.floor)}</span>` : '';
            return `<button type="button" class="indoor-dest-item${active}" role="option" data-dest-id="${this.escapeAttr(d.id)}" aria-selected="${d.id === ui.destId ? 'true' : 'false'}">
                <span class="indoor-dest-name">${this.escapeHtml(lab)}</span>
                ${floorMeta ? `<span class="indoor-dest-meta">${floorMeta}</span>` : ''}
            </button>`;
        }).join('');
    },

    refreshIndoorDestDropdown(dests) {
        const ui = this.data.indoorMapUi || {};
        const dropdown = document.getElementById('indoor-dest-dropdown');
        if (!dropdown) return;
        dropdown.innerHTML = this.buildIndoorDestDropdownHtml(dests, ui);
    },

    refreshIndoorDirectionsPanel() {
        const cfg = this.data.indoorMap;
        const ui = this.data.indoorMapUi || {};
        const panel = document.getElementById('indoor-directions-panel');
        if (!panel || !cfg) return;
        const dirText = this.getIndoorDirections(cfg, ui.boothId, ui.destId);
        this._indoorDirectionsText = dirText || '';
        panel.innerHTML = `
            <h3 class="indoor-directions-title">${i18n.t('indoor_directions')}</h3>
            <p class="indoor-directions-text">${dirText ? this.escapeHtml(dirText).replace(/\n/g, '<br>') : i18n.t('indoor_no_route')}</p>
            ${dirText ? `<button type="button" class="btn-block outline" style="margin-top:0.75rem;" onclick="app.copyIndoorDirections()">${i18n.t('indoor_copy_directions')}</button>` : ''}`;
    },

    openIndoorDestCombobox(dests) {
        const ui = this.data.indoorMapUi;
        if (!ui) return;
        ui.comboboxOpen = true;
        const wrap = document.getElementById('indoor-dest-combobox');
        const input = document.getElementById('indoor-dest-input');
        if (wrap) wrap.classList.add('is-open');
        if (input) input.setAttribute('aria-expanded', 'true');
        this.refreshIndoorDestDropdown(dests);
    },

    closeIndoorDestCombobox(dests, restoreLabel) {
        const ui = this.data.indoorMapUi;
        if (!ui) return;
        ui.comboboxOpen = false;
        ui.comboboxEditing = false;
        ui.query = '';
        const wrap = document.getElementById('indoor-dest-combobox');
        const input = document.getElementById('indoor-dest-input');
        if (wrap) wrap.classList.remove('is-open');
        if (input) {
            input.setAttribute('aria-expanded', 'false');
            if (restoreLabel !== false) {
                input.value = this.getIndoorDestInputDisplayValue(ui, dests);
            }
        }
    },

    initIndoorDestCombobox(dests) {
        if (this._indoorComboboxAbort) {
            this._indoorComboboxAbort();
            this._indoorComboboxAbort = null;
        }
        const wrap = document.getElementById('indoor-dest-combobox');
        const input = document.getElementById('indoor-dest-input');
        const dropdown = document.getElementById('indoor-dest-dropdown');
        const toggle = wrap ? wrap.querySelector('.indoor-dest-combobox-toggle') : null;
        if (!wrap || !input || !dropdown) return;

        const ui = this.data.indoorMapUi;
        const ac = new AbortController();
        this._indoorComboboxAbort = () => ac.abort();
        const opts = { signal: ac.signal };
        let highlightIndex = -1;

        const getItems = () => Array.from(dropdown.querySelectorAll('.indoor-dest-item'));

        const setHighlight = (index) => {
            const items = getItems();
            items.forEach((el, i) => el.classList.toggle('indoor-dest-highlight', i === index));
            highlightIndex = index;
            if (items[index]) {
                items[index].scrollIntoView({ block: 'nearest' });
            }
        };

        input.addEventListener('focus', () => {
            ui.comboboxEditing = true;
            if (ui.destId && !ui.query) {
                input.select();
            }
            this.openIndoorDestCombobox(dests);
        }, opts);

        input.addEventListener('input', () => {
            ui.query = input.value;
            ui.comboboxEditing = true;
            highlightIndex = -1;
            this.openIndoorDestCombobox(dests);
        }, opts);

        if (toggle) {
            toggle.addEventListener('mousedown', (e) => e.preventDefault(), opts);
            toggle.addEventListener('click', () => {
                if (ui.comboboxOpen) {
                    this.closeIndoorDestCombobox(dests);
                } else {
                    input.focus();
                    this.openIndoorDestCombobox(dests);
                }
            }, opts);
        }

        dropdown.addEventListener('mousedown', (e) => {
            const btn = e.target.closest('[data-dest-id]');
            if (btn) e.preventDefault();
        }, opts);

        dropdown.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-dest-id]');
            if (btn) {
                this.selectIndoorDest(btn.getAttribute('data-dest-id'));
            }
        }, opts);

        input.addEventListener('keydown', (e) => {
            const items = getItems();
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!ui.comboboxOpen) this.openIndoorDestCombobox(dests);
                setHighlight(Math.min(highlightIndex + 1, items.length - 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setHighlight(Math.max(highlightIndex - 1, 0));
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (highlightIndex >= 0 && items[highlightIndex]) {
                    this.selectIndoorDest(items[highlightIndex].getAttribute('data-dest-id'));
                } else if (items.length === 1) {
                    this.selectIndoorDest(items[0].getAttribute('data-dest-id'));
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                this.closeIndoorDestCombobox(dests);
                input.blur();
            }
        }, opts);

        input.addEventListener('blur', () => {
            setTimeout(() => {
                if (!wrap.contains(document.activeElement)) {
                    this.closeIndoorDestCombobox(dests);
                }
            }, 150);
        }, opts);
    },

    _syncIndoorDiagramFullscreen(ui) {
        const on = ui && ui.tab === 'map' && this.isIndoorMobileLayout();
        document.body.classList.toggle('indoor-diagram-fullscreen', on);
    },

    _indoorDiagramLayoutReady(viewport, img) {
        if (!viewport || !img) return false;
        if (viewport.clientWidth < 40 || viewport.clientHeight < 40) return false;
        if (!img.offsetWidth || !img.offsetHeight) return false;
        return true;
    },

    _getIndoorLayerMetrics(viewport, layer) {
        const img = layer.querySelector('.indoor-map-img');
        const lw = img && img.offsetWidth ? img.offsetWidth : 1;
        const lh = img && img.offsetHeight ? img.offsetHeight : 1;
        const vw = Math.max(viewport.clientWidth || 1, 1);
        const vh = Math.max(viewport.clientHeight || 1, 1);
        const fitScale = Math.min(vw / lw, vh / lh);
        const safeFit = fitScale > 0 && isFinite(fitScale) ? fitScale : 1;
        return { lw, lh, vw, vh, fitScale: safeFit, minScale: Math.min(safeFit, 1), maxScale: 4 };
    },

    _fitIndoorDiagram(viewport, layer, zoom) {
        if (!viewport || !layer || !zoom) return false;
        const img = layer.querySelector('.indoor-map-img');
        if (!this._indoorDiagramLayoutReady(viewport, img)) return false;
        const { lw, lh, vw, vh, fitScale } = this._getIndoorLayerMetrics(viewport, layer);
        if (fitScale <= 0 || !isFinite(fitScale)) return false;
        const scale = fitScale;
        const sw = lw * scale;
        const sh = lh * scale;
        zoom.scale = scale;
        zoom.tx = (vw - sw) / 2;
        zoom.ty = (vh - sh) / 2;
        const clamped = this._clampIndoorPan(scale, zoom.tx, zoom.ty, viewport, layer);
        zoom.scale = clamped.scale;
        zoom.tx = clamped.tx;
        zoom.ty = clamped.ty;
        return true;
    },

    isIndoorMobileLayout() {
        return window.matchMedia('(max-width: 768px)').matches;
    },

    _clampIndoorPan(scale, tx, ty, viewport, layer) {
        const vw = viewport.clientWidth;
        const vh = viewport.clientHeight;
        const img = layer.querySelector('.indoor-map-img');
        const lw = img ? img.offsetWidth : layer.offsetWidth;
        const lh = img ? img.offsetHeight : layer.offsetHeight;
        const sw = lw * scale;
        const sh = lh * scale;
        const minTx = sw <= vw ? (vw - sw) / 2 : vw - sw;
        const maxTx = sw <= vw ? (vw - sw) / 2 : 0;
        const minTy = sh <= vh ? (vh - sh) / 2 : vh - sh;
        const maxTy = sh <= vh ? (vh - sh) / 2 : 0;
        return {
            scale,
            tx: Math.max(minTx, Math.min(maxTx, tx)),
            ty: Math.max(minTy, Math.min(maxTy, ty))
        };
    },

    _applyIndoorDiagramTransform(zoom) {
        const layer = document.getElementById('indoor-map-transform');
        if (!layer || !zoom) return;
        layer.style.transform = `translate(${zoom.tx}px, ${zoom.ty}px) scale(${zoom.scale})`;
    },

    _focusIndoorDiagramOnPlace(viewport, layer, place, zoom, targetScale) {
        if (!viewport || !layer || !place || !zoom) return;
        const img = layer.querySelector('.indoor-map-img');
        if (!img) return;
        const metrics = this._getIndoorLayerMetrics(viewport, layer);
        const lw = metrics.lw;
        const lh = metrics.lh;
        const px = (typeof place.x === 'number' ? place.x : parseFloat(place.x) || 0) * lw;
        const py = (typeof place.y === 'number' ? place.y : parseFloat(place.y) || 0) * lh;
        const vw = metrics.vw;
        const vh = metrics.vh;
        const scale = Math.max(metrics.minScale, Math.min(metrics.maxScale, targetScale));
        const tx = vw / 2 - px * scale;
        const ty = vh / 2 - py * scale;
        const clamped = this._clampIndoorPan(scale, tx, ty, viewport, layer);
        zoom.scale = clamped.scale;
        zoom.tx = clamped.tx;
        zoom.ty = clamped.ty;
    },

    _getIndoorMinScale(viewport, layer) {
        return this._getIndoorLayerMetrics(viewport, layer).minScale;
    },

    initIndoorDiagramGestures(cfg, places) {
        if (this._indoorGestureAbort) {
            this._indoorGestureAbort();
            this._indoorGestureAbort = null;
        }
        const viewport = document.getElementById('indoor-map-viewport');
        const layer = document.getElementById('indoor-map-transform');
        const img = document.getElementById('indoor-map-img');
        if (!viewport || !layer) return;

        const ui = this.data.indoorMapUi || {};
        if (!ui.zoom) ui.zoom = { scale: 1, tx: 0, ty: 0 };
        const zoom = ui.zoom;

        const ac = new AbortController();
        this._indoorGestureAbort = () => ac.abort();

        const applyFocusIfNeeded = () => {
            if (!this._indoorDiagramLayoutReady(viewport, img)) return false;
            const focusMode = ui._focusDiagram;
            let applied = false;
            if (focusMode === 'fit') {
                applied = this._fitIndoorDiagram(viewport, layer, zoom);
                if (applied) ui._focusDiagram = false;
            } else if (focusMode === 'place') {
                const focusId = ui.destId || ui.boothId;
                const place = (places || []).find((p) => p.id === focusId);
                const targetScale = this.isIndoorMobileLayout() ? 2.5 : 2;
                if (place) {
                    this._focusIndoorDiagramOnPlace(viewport, layer, place, zoom, targetScale);
                    applied = true;
                } else {
                    applied = this._fitIndoorDiagram(viewport, layer, zoom);
                }
                if (applied) ui._focusDiagram = false;
            } else if (!zoom.scale || zoom.scale <= 0.01 || !isFinite(zoom.scale)) {
                applied = this._fitIndoorDiagram(viewport, layer, zoom);
            } else {
                applied = true;
            }
            if (applied) this._applyIndoorDiagramTransform(zoom);
            return applied;
        };

        let layoutRetries = 0;
        const tryApplyLayout = () => {
            if (applyFocusIfNeeded()) return;
            layoutRetries += 1;
            if (layoutRetries < 12 && (ui._focusDiagram === 'fit' || ui._focusDiagram === 'place' || !zoom.scale || zoom.scale <= 0.01)) {
                requestAnimationFrame(tryApplyLayout);
            }
        };

        if (img && img.complete) {
            tryApplyLayout();
        } else if (img) {
            img.onload = () => tryApplyLayout();
        }

        if (typeof ResizeObserver !== 'undefined') {
            const ro = new ResizeObserver(() => {
                if (ui._focusDiagram === 'fit' || ui._focusDiagram === 'place' || !zoom.scale || zoom.scale <= 0.01) {
                    applyFocusIfNeeded();
                }
            });
            ro.observe(viewport);
            ac.signal.addEventListener('abort', () => ro.disconnect());
        }

        const opts = { signal: ac.signal, passive: false };
        let touchMode = null;
        let startDist = 0;
        let startScale = 1;
        let startTx = 0;
        let startTy = 0;
        let startX = 0;
        let startY = 0;
        let startMidX = 0;
        let startMidY = 0;

        const touchPoint = (t, vpRect) => ({
            x: t.clientX - vpRect.left,
            y: t.clientY - vpRect.top
        });

        viewport.addEventListener('touchstart', (e) => {
            if (e.touches.length === 2) {
                touchMode = 'pinch';
                const rect = viewport.getBoundingClientRect();
                const p1 = touchPoint(e.touches[0], rect);
                const p2 = touchPoint(e.touches[1], rect);
                startDist = Math.hypot(p2.x - p1.x, p2.y - p1.y) || 1;
                startScale = zoom.scale;
                startTx = zoom.tx;
                startTy = zoom.ty;
                startMidX = (p1.x + p2.x) / 2;
                startMidY = (p1.y + p2.y) / 2;
            } else if (e.touches.length === 1) {
                touchMode = 'pan';
                startTx = zoom.tx;
                startTy = zoom.ty;
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            }
        }, opts);

        viewport.addEventListener('touchmove', (e) => {
            if (touchMode === 'pinch' && e.touches.length >= 2) {
                e.preventDefault();
                const rect = viewport.getBoundingClientRect();
                const p1 = touchPoint(e.touches[0], rect);
                const p2 = touchPoint(e.touches[1], rect);
                const dist = Math.hypot(p2.x - p1.x, p2.y - p1.y) || 1;
                const midX = (p1.x + p2.x) / 2;
                const midY = (p1.y + p2.y) / 2;
                let newScale = Math.max(this._getIndoorMinScale(viewport, layer), Math.min(4, startScale * (dist / startDist)));
                const px = (startMidX - startTx) / startScale;
                const py = (startMidY - startTy) / startScale;
                let newTx = midX - px * newScale;
                let newTy = midY - py * newScale;
                const clamped = this._clampIndoorPan(newScale, newTx, newTy, viewport, layer);
                zoom.scale = clamped.scale;
                zoom.tx = clamped.tx;
                zoom.ty = clamped.ty;
                this._applyIndoorDiagramTransform(zoom);
            } else if (touchMode === 'pan' && e.touches.length === 1) {
                e.preventDefault();
                const dx = e.touches[0].clientX - startX;
                const dy = e.touches[0].clientY - startY;
                const clamped = this._clampIndoorPan(zoom.scale, startTx + dx, startTy + dy, viewport, layer);
                zoom.tx = clamped.tx;
                zoom.ty = clamped.ty;
                this._applyIndoorDiagramTransform(zoom);
            }
        }, opts);

        viewport.addEventListener('touchend', () => {
            touchMode = null;
        }, opts);

        if (!this.isIndoorMobileLayout()) {
            let dragging = false;
            viewport.addEventListener('mousedown', (e) => {
                if (e.button !== 0) return;
                dragging = true;
                startTx = zoom.tx;
                startTy = zoom.ty;
                startX = e.clientX;
                startY = e.clientY;
                viewport.classList.add('is-dragging');
            }, opts);
            window.addEventListener('mousemove', (e) => {
                if (!dragging) return;
                const clamped = this._clampIndoorPan(zoom.scale, startTx + e.clientX - startX, startTy + e.clientY - startY, viewport, layer);
                zoom.tx = clamped.tx;
                zoom.ty = clamped.ty;
                this._applyIndoorDiagramTransform(zoom);
            }, opts);
            window.addEventListener('mouseup', () => {
                dragging = false;
                viewport.classList.remove('is-dragging');
            }, opts);
            viewport.addEventListener('wheel', (e) => {
                e.preventDefault();
                const rect = viewport.getBoundingClientRect();
                const cx = e.clientX - rect.left;
                const cy = e.clientY - rect.top;
                const delta = e.deltaY < 0 ? 1.08 : 0.92;
                const minScale = this._getIndoorMinScale(viewport, layer);
                const newScale = Math.max(minScale, Math.min(4, zoom.scale * delta));
                const px = (cx - zoom.tx) / zoom.scale;
                const py = (cy - zoom.ty) / zoom.scale;
                const clamped = this._clampIndoorPan(newScale, cx - px * newScale, cy - py * newScale, viewport, layer);
                zoom.scale = clamped.scale;
                zoom.tx = clamped.tx;
                zoom.ty = clamped.ty;
                this._applyIndoorDiagramTransform(zoom);
            }, opts);
        }
    },

    indoorPlaceLabel(place) {
        if (!place) return '';
        const lang = i18n.current || 'pt_br';
        const labels = place.labels || {};
        return labels[lang] || labels.pt_br || labels.en || labels.es || place.id || '';
    },

    getIndoorDirections(cfg, boothId, destId) {
        if (!boothId || !destId || !cfg || !Array.isArray(cfg.routes)) return '';
        const lang = i18n.current || 'pt_br';
        const route = cfg.routes.find((r) => r.from_place_id === boothId && r.to_place_id === destId);
        if (!route || !route.directions) return '';
        const d = route.directions;
        return d[lang] || d.pt_br || d.en || d.es || '';
    },

    escapeHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    },

    escapeAttr(str) {
        return String(str || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    },

    indoorDiagramFitAll() {
        if (!this.data.indoorMapUi) this.data.indoorMapUi = {};
        this.data.indoorMapUi._focusDiagram = 'fit';
        this.renderIndoorEventMap();
    },

    indoorDiagramGoToDest() {
        if (!this.data.indoorMapUi) this.data.indoorMapUi = {};
        if (!this.data.indoorMapUi.destId && !this.data.indoorMapUi.boothId) return;
        this.data.indoorMapUi._focusDiagram = 'place';
        this.renderIndoorEventMap();
    },

    setIndoorTab(tab) {
        if (!this.data.indoorMapUi) this.data.indoorMapUi = {};
        this.data.indoorMapUi._userPickedTab = true;
        this.data.indoorMapUi.tab = tab;
        if (tab === 'map') {
            this.data.indoorMapUi._focusDiagram = 'fit';
        } else {
            document.body.classList.remove('indoor-diagram-fullscreen');
        }
        this.renderIndoorEventMap();
    },

    setIndoorBooth(boothId) {
        if (!this.data.indoorMapUi) this.data.indoorMapUi = {};
        this.data.indoorMapUi.boothId = boothId;
        this.renderIndoorEventMap();
    },

    selectIndoorDest(destId) {
        if (!this.data.indoorMapUi) this.data.indoorMapUi = {};
        this.data.indoorMapUi.destId = destId;
        this.data.indoorMapUi.query = '';
        this.data.indoorMapUi.comboboxOpen = false;
        this.data.indoorMapUi.comboboxEditing = false;
        this.renderIndoorEventMap();
        requestAnimationFrame(() => {
            const panel = document.getElementById('indoor-directions-panel');
            if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    },

    onIndoorPinClick(id, kind) {
        if (!this.data.indoorMapUi) this.data.indoorMapUi = {};
        if (kind === 'booth') {
            this.data.indoorMapUi.boothId = id;
            this.data.indoorMapUi._focusDiagram = 'place';
        } else {
            this.data.indoorMapUi.destId = id;
            this.data.indoorMapUi._focusDiagram = 'place';
            this.data.indoorMapUi.tab = 'map';
        }
        this.renderIndoorEventMap();
    },

    copyIndoorDirections() {
        const text = this._indoorDirectionsText || '';
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => alert(i18n.t('indoor_copied'))).catch(() => {});
        }
    },

    async requestSwap(assignmentId) {
        const reason = prompt(i18n.t('ops_decline_reason')) || '';
        try {
            await API.createSwapRequest(assignmentId, reason);
            alert(i18n.t('ops_swap_sent'));
            await this.loadVolunteerOps();
            this.renderVolunteerOps();
        } catch (e) {
            alert(e.message || i18n.t('ops_swap_fail'));
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
            alert(e.message || i18n.t('ops_swap_generic_fail'));
        }
    },

    resolveSwapPrompt(id) {
        const name = prompt(i18n.t('ops_swap_substitute_name')) || '';
        const rid = parseInt(prompt(i18n.t('ops_swap_substitute_id')) || '0', 10) || 0;
        this.resolveSwap(id, 'approved', name, rid);
    },

    async openVolunteerOps() {
        if (!this.auth.user) {
            this.router.navigate('login');
            return;
        }
        if (!this.canViewOps()) {
            alert(i18n.t('ops_no_access'));
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
        let label = map[day] || day;
        const dates = this.data.volunteerOps?.settings?.event_dates || {};
        const ymd = dates[day];
        if (ymd && /^\d{4}-\d{2}-\d{2}$/.test(ymd)) {
            const parts = ymd.split('-');
            label += ` · ${parts[2]}/${parts[1]}`;
        }
        return label;
    },

    getOpsDayName(day) {
        const map = { sexta: 'Sexta', sabado: 'Sábado', domingo: 'Domingo' };
        return map[day] || day;
    },

    getOpsDayDateShort(day) {
        const dates = this.data.volunteerOps?.settings?.event_dates || {};
        const ymd = dates[day];
        if (ymd && /^\d{4}-\d{2}-\d{2}$/.test(ymd)) {
            const parts = ymd.split('-');
            return `${parts[2]}/${parts[1]}`;
        }
        return '';
    },

    formatAssignmentTimeRange(item) {
        const start = item.start || '';
        const end = item.end || '';
        if (start && end) {
            return `${start}–${end}`;
        }
        return start || end || '';
    },

    getCheckinStatus(assignmentId) {
        const checkins = this.data.volunteerOps?.checkins || {};
        return checkins[assignmentId] || { status: 'pending' };
    },

    async doCheckin(assignmentId, onBehalf = false) {
        try {
            const result = await API.checkinVolunteer(assignmentId, onBehalf);
            if (result.data) this.data.volunteerOps = result.data;
            else if (this.data.volunteerOps) this.data.volunteerOps.checkins = result.checkins || {};
            this.renderVolunteerOps();
            if (this.router.currentView === 'home') {
                this.renderHomeVolunteerDashboard();
            }
            this.updateNotificationsBadge();
        } catch (err) {
            alert(err.message || i18n.t('ops_checkin_fail'));
        }
    },

    async doCheckout(assignmentId, onBehalf = false) {
        try {
            const result = await API.checkoutVolunteer(assignmentId, onBehalf);
            if (result.data) this.data.volunteerOps = result.data;
            else if (this.data.volunteerOps) this.data.volunteerOps.checkins = result.checkins || {};
            this.renderVolunteerOps();
            if (this.router.currentView === 'home') {
                this.renderHomeVolunteerDashboard();
            }
            this.updateNotificationsBadge();
        } catch (err) {
            alert(err.message || i18n.t('ops_checkout_fail'));
        }
    },

    async doReallocate(assignmentId) {
        if (!this.canReallocateOps()) return;
        const newLocation = prompt(i18n.t('reallocate_prompt'));
        if (!newLocation) return;
        try {
            const result = await API.reallocateVolunteer(assignmentId, newLocation);
            this.data.volunteerOps = result.data || this.data.volunteerOps;
            this.renderVolunteerOps();
            if (this.router.currentView === 'home') {
                this.renderHomeVolunteerDashboard();
            }
            this.updateNotificationsBadge();
        } catch (err) {
            alert(i18n.t('reallocate_fail'));
        }
    },

    getMyScheduleRows() {
        const uid = this.auth.user?.id;
        if (!uid || !this.data.volunteerOps?.schedule) return [];
        return this.data.volunteerOps.schedule.filter((i) => Number(i.wp_user_id) === Number(uid));
    },

    applyOpsFilterMyShift() {
        const myRows = this.getMyScheduleRows();
        if (!myRows.length) return;
        const pick = myRows.slice().sort((a, b) => (a.start || '').localeCompare(b.start || ''))[0];
        this._opsFilterPreset = { day: pick.day || '', shift: pick.shift || '' };
        this.renderVolunteerOps();
    },

    getScheduleEditScopeOptions() {
        const edit = this.data.volunteerOps?.permissions?.schedule_edit;
        if (!edit || !edit.enabled) return [];
        if (edit.all) {
            const seen = new Set();
            const out = [];
            (this.data.volunteerOps.schedule || []).forEach((row) => {
                const key = `${row.day}|${row.shift}`;
                if (!row.day || !row.shift || seen.has(key)) return;
                seen.add(key);
                out.push({ day: row.day, shift: row.shift });
            });
            const shifts = this.data.volunteerOps.catalogs?.shifts || [];
            ['sexta', 'sabado', 'domingo'].forEach((day) => {
                shifts.forEach((sh) => {
                    const code = sh.code || sh.id;
                    if (!code) return;
                    const key = `${day}|${code}`;
                    if (!seen.has(key)) {
                        seen.add(key);
                        out.push({ day, shift: code });
                    }
                });
            });
            return out;
        }
        return edit.scopes || [];
    },

    volunteerRefFromRow(row) {
        if (row.wp_user_id) return `wp:${row.wp_user_id}`;
        if (row.roster_volunteer_id) return `rv:${row.roster_volunteer_id}`;
        return '';
    },

    openScheduleEditor(prefDay = '', prefShift = '') {
        if (!this.canEditSchedule()) return;
        const scopes = this.getScheduleEditScopeOptions();
        if (!scopes.length) {
            alert(i18n.t('ops_schedule_edit_no_scope'));
            return;
        }
        let day = prefDay;
        let shift = prefShift;
        if (!day || !shift || !this.canEditScheduleScope(day, shift)) {
            day = scopes[0].day;
            shift = scopes[0].shift;
        }
        const rows = (this.data.volunteerOps.schedule || [])
            .filter((r) => r.day === day && r.shift === shift)
            .map((r) => ({
                id: r.id,
                start: r.start || '',
                end: r.end || '',
                volunteer_ref: this.volunteerRefFromRow(r)
            }));
        this._scheduleEditorSaving = false;
        this._scheduleEditor = { day, shift, rows, scopes };
        this.renderScheduleEditorOverlay();
    },

    closeScheduleEditor() {
        if (this._scheduleEditorSaving) return;
        this._scheduleEditor = null;
        this._unbindScheduleEditorEscape();
        document.body.classList.remove('ops-editor-open');
        const el = document.getElementById('ops-schedule-editor-overlay');
        if (el) el.remove();
    },

    getShiftCatalogEntry(shiftCode) {
        const shifts = this.data.volunteerOps?.catalogs?.shifts || [];
        return shifts.find((s) => (s.code || s.id) === shiftCode) || null;
    },

    resolveLocationNameForShift(shiftCode) {
        const sh = this.getShiftCatalogEntry(shiftCode);
        if (!sh || !sh.location_id) return '-';
        const locs = this.data.volunteerOps?.catalogs?.locations || [];
        const loc = locs.find((l) => l.id === sh.location_id);
        return loc ? loc.name : sh.location_id;
    },

    onScheduleEditorShiftChange() {
        if (!this._scheduleEditor) return;
        const sel = document.getElementById('ops-editor-shift');
        const daySel = document.getElementById('ops-editor-day');
        if (!sel || !daySel) return;
        const day = daySel.value;
        const shift = sel.value;
        if (!this.canEditScheduleScope(day, shift)) return;
        this._scheduleEditor.day = day;
        this._scheduleEditor.shift = shift;
        const sh = this.getShiftCatalogEntry(shift);
        this._scheduleEditor.rows = (this.data.volunteerOps.schedule || [])
            .filter((r) => r.day === day && r.shift === shift)
            .map((r) => ({
                id: r.id,
                start: r.start || sh?.start || '',
                end: r.end || sh?.end || '',
                volunteer_ref: this.volunteerRefFromRow(r)
            }));
        this.renderScheduleEditorOverlay();
    },

    syncScheduleEditorRowsFromDom() {
        if (!this._scheduleEditor) return;
        const root = document.getElementById('ops-schedule-editor-overlay');
        if (!root) return;
        this._scheduleEditor.rows = this._scheduleEditor.rows.map((row, idx) => {
            const startEl = root.querySelector(`.ops-editor-start[data-idx="${idx}"]`);
            const endEl = root.querySelector(`.ops-editor-end[data-idx="${idx}"]`);
            const volEl = root.querySelector(`.ops-editor-volunteer[data-idx="${idx}"]`);
            return {
                id: row.id || '',
                start: startEl ? startEl.value : row.start,
                end: endEl ? endEl.value : row.end,
                volunteer_ref: volEl ? volEl.value : row.volunteer_ref
            };
        });
    },

    addScheduleEditorRow() {
        if (!this._scheduleEditor) return;
        this.syncScheduleEditorRowsFromDom();
        const sh = this.getShiftCatalogEntry(this._scheduleEditor.shift);
        this._scheduleEditor.rows.push({
            id: '',
            start: sh?.start || '',
            end: sh?.end || '',
            volunteer_ref: ''
        });
        this.renderScheduleEditorOverlay();
    },

    removeScheduleEditorRow(index) {
        if (!this._scheduleEditor) return;
        this.syncScheduleEditorRowsFromDom();
        this._scheduleEditor.rows.splice(index, 1);
        this.renderScheduleEditorOverlay();
    },

    buildVolunteerRefOptions(selected) {
        const cats = this.data.volunteerOps?.catalogs || {};
        let html = `<option value="">${this.escapeHtml(i18n.t('ops_editor_pick_volunteer'))}</option>`;
        (cats.wp_users || []).forEach((u) => {
            const ref = `wp:${u.id}`;
            html += `<option value="${this.escapeHtml(ref)}"${ref === selected ? ' selected' : ''}>${this.escapeHtml(u.name)} (WP)</option>`;
        });
        (cats.roster_volunteers || []).forEach((rv) => {
            const ref = `rv:${rv.id}`;
            html += `<option value="${this.escapeHtml(ref)}"${ref === selected ? ' selected' : ''}>${this.escapeHtml(rv.name)}</option>`;
        });
        return html;
    },

    getShiftBoundsLabel(shiftCode) {
        const sh = this.getShiftCatalogEntry(shiftCode);
        if (!sh || (!sh.start && !sh.end)) return '';
        return `${sh.start || '–'} ${i18n.t('ops_time_to')} ${sh.end || '–'}`;
    },

    renderScheduleEditorRowCard(row, idx) {
        return `
            <article class="ops-editor-row-card">
                <div class="ops-editor-row-times">
                    <label class="ops-editor-field">
                        <span class="ops-editor-field-label">${this.escapeHtml(i18n.t('ops_time_start'))}</span>
                        <input type="time" class="ops-editor-start" data-idx="${idx}" value="${this.escapeHtml(row.start || '')}">
                    </label>
                    <label class="ops-editor-field">
                        <span class="ops-editor-field-label">${this.escapeHtml(i18n.t('ops_time_end'))}</span>
                        <input type="time" class="ops-editor-end" data-idx="${idx}" value="${this.escapeHtml(row.end || '')}">
                    </label>
                </div>
                <label class="ops-editor-field ops-editor-field--full">
                    <span class="ops-editor-field-label">${this.escapeHtml(i18n.t('ops_volunteer_label'))}</span>
                    <select class="ops-editor-volunteer" data-idx="${idx}">${this.buildVolunteerRefOptions(row.volunteer_ref || '')}</select>
                </label>
                <button type="button" class="ops-editor-remove-link" onclick="app.removeScheduleEditorRow(${idx})">${this.escapeHtml(i18n.t('ops_editor_remove_line'))}</button>
            </article>`;
    },

    _bindScheduleEditorEscape() {
        if (this._scheduleEditorEscapeBound) return;
        this._scheduleEditorEscapeHandler = (e) => {
            if (e.key === 'Escape' && this._scheduleEditor) this.closeScheduleEditor();
        };
        document.addEventListener('keydown', this._scheduleEditorEscapeHandler);
        this._scheduleEditorEscapeBound = true;
    },

    _unbindScheduleEditorEscape() {
        if (this._scheduleEditorEscapeHandler) {
            document.removeEventListener('keydown', this._scheduleEditorEscapeHandler);
            this._scheduleEditorEscapeHandler = null;
        }
        this._scheduleEditorEscapeBound = false;
    },

    renderScheduleEditorOverlay() {
        const ed = this._scheduleEditor;
        if (!ed) return;
        let overlay = document.getElementById('ops-schedule-editor-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'ops-schedule-editor-overlay';
            overlay.className = 'ops-schedule-editor-overlay';
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) this.closeScheduleEditor();
            });
            document.body.appendChild(overlay);
        }
        const dayOpts = ['sexta', 'sabado', 'domingo'].map((d) =>
            `<option value="${d}"${ed.day === d ? ' selected' : ''}>${this.escapeHtml(this.getOpsDayLabel(d))}</option>`
        ).join('');
        const shiftOpts = (this.data.volunteerOps.catalogs?.shifts || []).map((sh) => {
            const code = sh.code || sh.id;
            return `<option value="${this.escapeHtml(code)}"${ed.shift === code ? ' selected' : ''}>${this.escapeHtml(code)}</option>`;
        }).join('');
        const locName = this.resolveLocationNameForShift(ed.shift);
        const bounds = this.getShiftBoundsLabel(ed.shift);
        const rowCount = ed.rows.length;
        const countLabel = rowCount === 1
            ? i18n.t('ops_editor_row_count_one')
            : i18n.t('ops_editor_row_count').replace('{0}', String(rowCount));
        const rowsHtml = rowCount
            ? ed.rows.map((row, idx) => this.renderScheduleEditorRowCard(row, idx)).join('')
            : `<p class="ops-editor-empty text-muted">${this.escapeHtml(i18n.t('ops_editor_no_rows'))}</p>`;
        const saving = !!this._scheduleEditorSaving;
        const contextParts = [locName !== '-' ? locName : '', bounds].filter(Boolean).join(' · ');
        overlay.innerHTML = `
            <div class="ops-schedule-editor-panel" role="dialog" aria-modal="true" aria-labelledby="ops-editor-title">
                <header class="ops-schedule-editor-header">
                    <h2 id="ops-editor-title">${this.escapeHtml(i18n.t('ops_schedule_editor_title'))}</h2>
                    <button type="button" class="ops-editor-close-btn" onclick="app.closeScheduleEditor()" aria-label="${this.escapeHtml(i18n.t('ops_editor_close_aria'))}">×</button>
                </header>
                <div class="ops-schedule-editor-body">
                    <div class="ops-schedule-editor-scope-bar">
                        <div class="ops-editor-field-grid">
                            <label class="ops-editor-field">
                                <span class="ops-editor-field-label">${this.escapeHtml(i18n.t('ops_editor_day'))}</span>
                                <select id="ops-editor-day" class="ops-editor-select" onchange="app.onScheduleEditorShiftChange()">${dayOpts}</select>
                            </label>
                            <label class="ops-editor-field">
                                <span class="ops-editor-field-label">${this.escapeHtml(i18n.t('ops_shift_label'))}</span>
                                <select id="ops-editor-shift" class="ops-editor-select" onchange="app.onScheduleEditorShiftChange()">${shiftOpts}</select>
                            </label>
                        </div>
                        ${contextParts ? `<p class="ops-editor-context-chip">${this.escapeHtml(contextParts)}</p>` : ''}
                        <p class="ops-editor-row-count">${this.escapeHtml(countLabel)}</p>
                    </div>
                    <div class="ops-schedule-editor-rows">${rowsHtml}</div>
                    <button type="button" class="btn-block outline ops-editor-add-btn" onclick="app.addScheduleEditorRow()" ${saving ? 'disabled' : ''}>${this.escapeHtml(i18n.t('ops_editor_add_row'))}</button>
                </div>
                <footer class="ops-schedule-editor-footer">
                    <button type="button" class="btn-block ops-editor-save-btn" onclick="app.saveScheduleEditor()" ${saving ? 'disabled' : ''}>${this.escapeHtml(saving ? i18n.t('ops_editor_saving') : i18n.t('ops_editor_save'))}</button>
                </footer>
            </div>
        `;
        document.body.classList.add('ops-editor-open');
        this._bindScheduleEditorEscape();
    },

    collectScheduleEditorRows() {
        if (!this._scheduleEditor) return [];
        const root = document.getElementById('ops-schedule-editor-overlay');
        return this._scheduleEditor.rows.map((row, idx) => {
            const startEl = root ? root.querySelector(`.ops-editor-start[data-idx="${idx}"]`) : null;
            const endEl = root ? root.querySelector(`.ops-editor-end[data-idx="${idx}"]`) : null;
            const volEl = root ? root.querySelector(`.ops-editor-volunteer[data-idx="${idx}"]`) : null;
            const out = {
                start: startEl ? startEl.value : row.start,
                end: endEl ? endEl.value : row.end,
                volunteer_ref: volEl ? volEl.value : row.volunteer_ref
            };
            if (row.id) out.id = row.id;
            return out;
        }).filter((r) => r.volunteer_ref);
    },

    async saveScheduleEditor() {
        if (!this._scheduleEditor || this._scheduleEditorSaving) return;
        const { day, shift } = this._scheduleEditor;
        const rows = this.collectScheduleEditorRows();
        const dayLabel = this.getOpsDayLabel(day);
        const msg = i18n.t('ops_editor_save_confirm')
            .replace('{0}', dayLabel)
            .replace('{1}', shift)
            .replace('{2}', String(rows.length));
        if (!window.confirm(msg)) return;
        this._scheduleEditorSaving = true;
        this.renderScheduleEditorOverlay();
        try {
            const result = await API.saveScheduleScope(day, shift, rows);
            if (result.data) this.data.volunteerOps = result.data;
            this._scheduleEditorSaving = false;
            this.closeScheduleEditor();
            this.renderVolunteerOps();
            if (this.router.currentView === 'home') this.renderHomeVolunteerDashboard();
            this.updateNotificationsBadge();
        } catch (err) {
            this._scheduleEditorSaving = false;
            this.renderScheduleEditorOverlay();
            alert(err.message || i18n.t('ops_editor_save_fail'));
        }
    },

    getOpsScheduleViewMode() {
        if (this._opsScheduleViewMode === 'list' || this._opsScheduleViewMode === 'shift') {
            return this._opsScheduleViewMode;
        }
        try {
            const stored = localStorage.getItem('zelo_ops_schedule_view');
            if (stored === 'list' || stored === 'shift') {
                this._opsScheduleViewMode = stored;
                return stored;
            }
        } catch (e) { /* ignore */ }
        this._opsScheduleViewMode = 'shift';
        return 'shift';
    },

    setOpsScheduleViewMode(mode) {
        this._opsScheduleViewMode = mode === 'list' ? 'list' : 'shift';
        try {
            localStorage.setItem('zelo_ops_schedule_view', this._opsScheduleViewMode);
        } catch (e) { /* ignore */ }
        this.renderVolunteerOps();
    },

    opsSlotKey(row) {
        return `${row.start || ''}|${row.end || ''}`;
    },

    _parseTimeToMinutes(t) {
        const m = String(t || '').match(/^(\d{1,2}):(\d{2})$/);
        if (!m) return null;
        return parseInt(m[1], 10) * 60 + parseInt(m[2], 10);
    },

    formatSlotDuration(start, end) {
        const a = this._parseTimeToMinutes(start);
        const b = this._parseTimeToMinutes(end);
        if (a == null || b == null) return '';
        let diff = b - a;
        if (diff <= 0) diff += 24 * 60;
        const h = Math.floor(diff / 60);
        const min = diff % 60;
        if (h && min) return `${h}h${min < 10 ? '0' : ''}${min}`;
        if (h) return `${h}h`;
        return `${min}min`;
    },

    normalizeWhatsAppPhone(phone) {
        const digits = String(phone || '').replace(/\D/g, '');
        if (!digits) return '';
        if (digits.length <= 11 && !digits.startsWith('55')) {
            return '55' + digits.replace(/^0+/, '');
        }
        return digits;
    },

    buildWhatsAppUrl(phone) {
        const n = this.normalizeWhatsAppPhone(phone);
        return n ? `https://wa.me/${n}` : '';
    },

    renderOpsWhatsAppNameLink(name, phone, className = 'ops-volunteer-name') {
        const label = this.escapeHtml(name || '');
        if (!label) return '';
        const url = this.buildWhatsAppUrl(phone);
        const title = this.escapeHtml(i18n.t('ops_whatsapp_open'));
        if (!url) {
            return `<span class="${className}">${label}</span>`;
        }
        return `<a href="${url}" class="ops-whatsapp-link ${className}" target="_blank" rel="noopener noreferrer" title="${title}">${label}</a>`;
    },

    getShiftContact(day, shift) {
        const map = this.data.volunteerOps?.shift_contacts;
        if (!map || !day || !shift) return null;
        const dayKey = String(day);
        const dayMap = map[dayKey] || map[dayKey.toLowerCase()];
        if (!dayMap) return null;
        return dayMap[shift] || dayMap[String(shift).toUpperCase()] || null;
    },

    collectOpsShiftResponsibleNames() {
        const map = this.data.volunteerOps?.shift_contacts;
        if (!map || typeof map !== 'object') return [];
        const names = new Set();
        Object.values(map).forEach((dayMap) => {
            if (!dayMap || typeof dayMap !== 'object') return;
            Object.values(dayMap).forEach((contact) => {
                const n = contact && contact.name ? String(contact.name).trim() : '';
                if (n) names.add(n);
            });
        });
        return [...names].sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
    },

    normalizeOpsResponsibleName(name) {
        return String(name || '').trim().toLowerCase();
    },

    itemMatchesOpsResponsibleFilter(item, selectedResponsible) {
        if (!selectedResponsible) return true;
        const contact = this.getShiftContact(item.day, item.shift);
        const name = contact && contact.name ? contact.name : '';
        return this.normalizeOpsResponsibleName(name) === this.normalizeOpsResponsibleName(selectedResponsible);
    },

    renderOpsShiftResponsibleLine(day, shift) {
        const contact = this.getShiftContact(day, shift);
        if (!contact || !contact.name) return '';
        const label = this.escapeHtml(i18n.t('ops_shift_responsible'));
        const nameHtml = this.renderOpsWhatsAppNameLink(contact.name, contact.phone, 'ops-shift-responsible-name');
        return `<p class="ops-shift-responsible">${label} ${nameHtml}</p>`;
    },

    getShiftDisplayBounds(day, shift, rows) {
        let loc = '';
        if (rows.length && rows[0].location) {
            loc = rows[0].location;
        } else {
            const resolved = this.resolveLocationNameForShift(shift);
            if (resolved && resolved !== '-') loc = resolved;
        }
        const sh = this.getShiftCatalogEntry(shift);
        let minStart = '';
        let maxEnd = '';
        rows.forEach((r) => {
            if (r.start && (!minStart || r.start < minStart)) minStart = r.start;
            if (r.end && (!maxEnd || r.end > maxEnd)) maxEnd = r.end;
        });
        if (sh && sh.start && sh.end && !minStart) {
            minStart = sh.start;
            maxEnd = sh.end;
        }
        const bounds = (minStart && maxEnd)
            ? `${minStart} ${i18n.t('ops_time_to')} ${maxEnd}`
            : '';
        return {
            location: loc,
            bounds,
            label: [shift, loc, bounds].filter(Boolean).join(' · ')
        };
    },

    groupScheduleForShiftView(items) {
        const grouped = {};
        items.forEach((item) => {
            const day = item.day || 'outros';
            const shift = item.shift || '-';
            const sk = this.opsSlotKey(item);
            if (!grouped[day]) grouped[day] = {};
            if (!grouped[day][shift]) grouped[day][shift] = {};
            if (!grouped[day][shift][sk]) grouped[day][shift][sk] = [];
            grouped[day][shift][sk].push(item);
        });
        return grouped;
    },

    sortSlotKeys(slotMap) {
        return Object.keys(slotMap).sort((a, b) => {
            const sa = a.split('|')[0] || '';
            const sb = b.split('|')[0] || '';
            return sa.localeCompare(sb);
        });
    },

    _opsShiftSortIndex(code) {
        const order = ['A1', 'B1', 'A2', 'B2'];
        const i = order.indexOf(code);
        return i >= 0 ? i : 99;
    },

    renderOpsVolunteerInSlot(item, uid, showActions) {
        const mineRow = Number(item.wp_user_id) === Number(uid);
        const supRow = showActions && this.canSuperviseOps() && !mineRow;
        const idEsc = String(item.id).replace(/'/g, "\\'");
        let inlineActions = '';
        let hintHtml = '';
        if (showActions) {
            const act = this.renderAssignmentActions(item, supRow, 'icons');
            inlineActions += act.actions || '';
            hintHtml = act.hint || '';
            const canRealloc = this.canReallocateOps() && this.canSuperviseOps() && (mineRow || supRow);
            if (canRealloc) {
                inlineActions += this.renderOpsIconButton('ops_reallocate', `app.doReallocate('${idEsc}')`, 'reallocate', 'outline');
            }
            if (mineRow && this.getCommitmentStatus(item.id) === 'declined') {
                inlineActions += this.renderOpsIconButton('ops_request_swap', `app.requestSwap('${idEsc}')`, 'swap', 'accent');
            }
        }
        const langs = (item.languages || []).join(', ');
        const volName = item.volunteer_name || i18n.t('ops_volunteer_default');
        const nameLink = this.renderOpsWhatsAppNameLink(volName, item.volunteer_phone);
        const youPrefix = mineRow ? `<span class="ops-you-badge">${this.escapeHtml(i18n.t('ops_you_badge'))}</span> ` : '';
        return `
            <li class="ops-volunteer-row ops-volunteer-row--compact${mineRow ? ' ops-volunteer-row--mine' : ''}">
                <div class="ops-volunteer-head">
                    <div class="ops-volunteer-ident">
                        ${youPrefix}${nameLink}
                        ${langs ? `<span class="ops-volunteer-langs text-muted">${this.escapeHtml(langs)}</span>` : ''}
                    </div>
                    ${inlineActions ? `<div class="ops-volunteer-inline-actions" role="group" aria-label="${this.escapeHtml(i18n.t('ops_assignment_actions_group'))}">${inlineActions}</div>` : ''}
                </div>
                <div class="ops-volunteer-status">${this.getCommitmentBadge(item.id)} ${this.getOpsStatusBadge(item.id)}</div>
                ${hintHtml}
            </li>`;
    },

    renderOpsShiftCard(day, shift, slotMap, uid, showActions) {
        const allRows = [];
        Object.values(slotMap).forEach((arr) => { allRows.push(...arr); });
        const display = this.getShiftDisplayBounds(day, shift, allRows);
        const slotKeys = this.sortSlotKeys(slotMap);
        const slotsHtml = slotKeys.map((sk, idx) => {
            const rows = slotMap[sk];
            const parts = sk.split('|');
            const start = parts[0] || (rows[0] && rows[0].start) || '';
            const end = parts[1] || (rows[0] && rows[0].end) || '';
            const dur = this.formatSlotDuration(start, end);
            const vols = rows
                .slice()
                .sort((a, b) => String(a.volunteer_name || '').localeCompare(String(b.volunteer_name || '')))
                .map((item) => this.renderOpsVolunteerInSlot(item, uid, showActions))
                .join('');
            const timeLine = `${start || '-'} ${i18n.t('ops_time_to')} ${end || '-'}`;
            return `
                <div class="ops-slot ops-slot--${idx % 4}">
                    <div class="ops-slot-meta">
                        <span class="ops-slot-time">${this.escapeHtml(timeLine)}</span>
                        ${dur ? `<span class="ops-slot-duration">${this.escapeHtml(i18n.t('ops_slot_duration'))}: ${this.escapeHtml(dur)}</span>` : ''}
                    </div>
                    <ul class="ops-slot-volunteers">${vols}</ul>
                </div>`;
        }).join('');
        const dayEsc = String(day).replace(/'/g, "\\'");
        const shiftEsc = String(shift).replace(/'/g, "\\'");
        const editBtn = showActions && this.canEditScheduleScope(day, shift)
            ? `<button type="button" class="ops-btn ops-shift-edit-btn" onclick="app.openScheduleEditor('${dayEsc}','${shiftEsc}')">${this.escapeHtml(i18n.t('ops_edit_this_shift'))}</button>`
            : '';
        const responsibleLine = this.renderOpsShiftResponsibleLine(day, shift);
        return `
            <article class="ops-shift-card">
                <header class="ops-shift-card-header">
                    <div class="ops-shift-card-title">
                        <strong class="ops-shift-code">${this.escapeHtml(shift)}</strong>
                        <span class="ops-shift-meta text-muted">${this.escapeHtml([display.location, display.bounds].filter(Boolean).join(' · '))}</span>
                        ${responsibleLine}
                    </div>
                    ${editBtn}
                </header>
                <div class="ops-shift-card-body">${slotsHtml}</div>
            </article>`;
    },

    renderOpsShiftSchedule(items, uid, showActions, options = {}) {
        const grouped = this.groupScheduleForShiftView(items);
        const dayOrder = ['sexta', 'sabado', 'domingo'];
        const wrapClass = options.wrapClass || 'ops-schedule-shift-view';
        const sections = [];
        dayOrder.forEach((day) => {
            const shifts = grouped[day];
            if (!shifts) return;
            const shiftCodes = Object.keys(shifts).sort((a, b) => this._opsShiftSortIndex(a) - this._opsShiftSortIndex(b));
            const cards = shiftCodes.map((shift) => this.renderOpsShiftCard(day, shift, shifts[shift], uid, showActions)).join('');
            if (!cards) return;
            sections.push(`
                <section class="ops-day-group">
                    <h3 class="ops-day-group-title">${this.escapeHtml(this.getOpsDayLabel(day))}</h3>
                    <div class="ops-shift-cards">${cards}</div>
                </section>`);
        });
        Object.keys(grouped).forEach((day) => {
            if (dayOrder.includes(day)) return;
            const shifts = grouped[day];
            const shiftCodes = Object.keys(shifts).sort();
            const cards = shiftCodes.map((shift) => this.renderOpsShiftCard(day, shift, shifts[shift], uid, showActions)).join('');
            if (!cards) return;
            sections.push(`
                <section class="ops-day-group">
                    <h3 class="ops-day-group-title">${this.escapeHtml(this.getOpsDayLabel(day))}</h3>
                    <div class="ops-shift-cards">${cards}</div>
                </section>`);
        });
        const inner = sections.join('') || `<div class="loading">${this.escapeHtml(i18n.t('ops_no_schedule_filtered'))}</div>`;
        return `<div class="${wrapClass}${options.compact ? ' ops-schedule-shift-view--compact' : ''}">${inner}</div>`;
    },

    renderOpsMyAssignmentsBlock(uid) {
        const myRows = this.getMyScheduleRows();
        if (!myRows.length) {
            return `<div class="ops-mine-block"><h3>${this.escapeHtml(i18n.t('ops_my_assignments'))}</h3><p class="text-muted">${this.escapeHtml(i18n.t('ops_no_wp_user'))}</p></div>`;
        }
        const scheduleHtml = this.renderOpsShiftSchedule(myRows, uid, true, {
            compact: true,
            wrapClass: 'ops-schedule-shift-view ops-schedule-shift-view--mine'
        });
        return `
            <section class="ops-mine-block ops-mine-block--shift">
                <h3>${this.escapeHtml(i18n.t('ops_my_assignments'))}</h3>
                ${scheduleHtml}
            </section>`;
    },

    renderOpsGovernanceCard(day, data) {
        const keymen = data.keymen
            ? Object.entries(data.keymen).map(([shift, person]) => `${this.escapeHtml(shift)}: ${this.escapeHtml(person)}`).join(' | ')
            : '-';
        return `
            <div class="ops-governance-card">
                <h4>${this.escapeHtml(this.getOpsDayLabel(day))}</h4>
                <p><strong>${this.escapeHtml(i18n.t('governance_group_a'))}:</strong> ${this.escapeHtml(data.group_a_supervisor || '-')}</p>
                <p><strong>${this.escapeHtml(i18n.t('governance_group_b'))}:</strong> ${this.escapeHtml(data.group_b_supervisor || '-')}</p>
                <p><strong>${this.escapeHtml(i18n.t('governance_app_supervisor'))}:</strong> ${this.escapeHtml(data.app_supervisor || '-')}</p>
                <p><strong>${this.escapeHtml(i18n.t('governance_keymen'))}:</strong> ${keymen}</p>
            </div>`;
    },

    renderOpsScheduleRow(item, uid) {
        const mineRow = Number(item.wp_user_id) === uid;
        const supRow = this.canSuperviseOps() && !mineRow;
        const actions = this.renderAssignmentActions(item, supRow);
        const swapBtn = mineRow && this.getCommitmentStatus(item.id) === 'declined'
            ? `<button type="button" class="ops-btn ops-btn--accent" onclick="app.requestSwap('${String(item.id).replace(/'/g, "\\'")}')">${this.escapeHtml(i18n.t('ops_request_swap'))}</button>` : '';
        const canRealloc = this.canReallocateOps() && this.canSuperviseOps() && (mineRow || supRow);
        const reallocBtn = canRealloc
            ? `<button type="button" class="ops-btn" onclick="app.doReallocate('${String(item.id).replace(/'/g, "\\'")}')">${this.escapeHtml(i18n.t('ops_reallocate'))}</button>`
            : '';
        const actionCell = (actions || swapBtn || reallocBtn)
            ? `<div class="ops-table-actions">${actions}${reallocBtn}${swapBtn}</div>`
            : '';
        const timeRange = `${item.start || '-'} ${i18n.t('ops_time_to')} ${item.end || '-'}`;
        return `
            <tr class="ops-schedule-row${mineRow ? ' ops-schedule-row--mine' : ''}">
                <td data-label="${this.escapeHtml(i18n.t('ops_shift_label'))}">${this.escapeHtml(item.shift || '-')}</td>
                <td data-label="${this.escapeHtml(i18n.t('ops_time_label'))}">${this.escapeHtml(timeRange)}</td>
                <td data-label="${this.escapeHtml(i18n.t('ops_location_label'))}">${this.escapeHtml(item.location || '-')}</td>
                <td data-label="${this.escapeHtml(i18n.t('ops_volunteer_label'))}">${mineRow ? `<span class="ops-you-badge">${this.escapeHtml(i18n.t('ops_you_badge'))}</span> ` : ''}${this.renderOpsWhatsAppNameLink(item.volunteer_name || i18n.t('ops_volunteer_default'), item.volunteer_phone)}</td>
                <td data-label="${this.escapeHtml(i18n.t('ops_languages_label'))}">${this.escapeHtml((item.languages || []).join(', ') || '-')}</td>
                <td data-label="${this.escapeHtml(i18n.t('ops_status_label'))}">${this.getCommitmentBadge(item.id)} ${this.getOpsStatusBadge(item.id)}</td>
                ${actionCell ? `<td class="ops-table-actions-cell">${actionCell}</td>` : '<td></td>'}
            </tr>`;
    },

    renderOpsDayGroups(items, uid, showActions) {
        const dayOrder = ['sexta', 'sabado', 'domingo'];
        const byDay = {};
        items.forEach((item) => {
            const d = item.day || 'outros';
            if (!byDay[d]) byDay[d] = [];
            byDay[d].push(item);
        });
        const sections = [];
        dayOrder.forEach((day) => {
            const rows = byDay[day];
            if (!rows || !rows.length) return;
            const sorted = rows.slice().sort((a, b) => {
                const ta = (a.start || '').localeCompare(b.start || '');
                if (ta !== 0) return ta;
                return (a.end || '').localeCompare(b.end || '');
            });
            const tableRows = sorted.map((item) => this.renderOpsScheduleRow(item, uid)).join('');
            sections.push(`
                <section class="ops-day-group">
                    <h3 class="ops-day-group-title">${this.escapeHtml(this.getOpsDayLabel(day))}</h3>
                    <div class="ops-table-wrap">
                        <table class="ops-schedule-table">
                            <thead>
                                <tr>
                                    <th>${this.escapeHtml(i18n.t('ops_shift_label'))}</th>
                                    <th>${this.escapeHtml(i18n.t('ops_time_label'))}</th>
                                    <th>${this.escapeHtml(i18n.t('ops_location_label'))}</th>
                                    <th>${this.escapeHtml(i18n.t('ops_volunteer_label'))}</th>
                                    <th>${this.escapeHtml(i18n.t('ops_languages_label'))}</th>
                                    <th>${this.escapeHtml(i18n.t('ops_status_label'))}</th>
                                    ${showActions ? `<th>${this.escapeHtml(i18n.t('ops_actions_label'))}</th>` : '<th></th>'}
                                </tr>
                            </thead>
                            <tbody>${tableRows}</tbody>
                        </table>
                    </div>
                </section>`);
        });
        return sections.join('') || `<div class="loading">${this.escapeHtml(i18n.t('ops_no_schedule_filtered'))}</div>`;
    },

    async downloadOpsExportPdf() {
        const day = document.getElementById('ops-day-filter')?.value || '';
        const shift = document.getElementById('ops-shift-filter')?.value || '';
        const btn = document.getElementById('ops-export-pdf-btn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = i18n.t('ops_exporting');
        }
        try {
            const blob = await API.downloadOpsExport({ day, shift });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `zelo-escala${day ? '-' + day : ''}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch (e) {
            alert(e.message || i18n.t('ops_export_error'));
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = i18n.t('ops_export_pdf');
            }
        }
    },

    renderVolunteerOps() {
        const container = document.getElementById('volunteer-ops-container');
        if (!container) return;

        if (!this.auth.user) {
            container.innerHTML = `<div class="loading">${this.escapeHtml(i18n.t('ops_login_required'))}</div>`;
            return;
        }
        if (!this.canViewOps()) {
            container.innerHTML = `<div class="loading">${this.escapeHtml(i18n.t('ops_no_permission'))}</div>`;
            return;
        }

        const ops = this.data.volunteerOps;
        if (!ops || !Array.isArray(ops.schedule)) {
            container.innerHTML = `<div class="loading">${this.escapeHtml(i18n.t('ops_loading'))}</div>`;
            return;
        }

        const staleBanner = this._dataStale.ops ? `<div class="zelo-stale-banner">${this.renderStaleBadge('ops')}</div>` : '';
        const preset = this._opsFilterPreset || {};
        const dayEl = document.getElementById('ops-day-filter');
        if (dayEl) {
            this._opsLastFilters = {
                day: dayEl.value || '',
                shift: document.getElementById('ops-shift-filter')?.value || '',
                location: document.getElementById('ops-location-filter')?.value || '',
                name: document.getElementById('ops-name-filter')?.value || '',
                language: document.getElementById('ops-language-filter')?.value || '',
                responsible: document.getElementById('ops-responsible-filter')?.value || ''
            };
        }
        const lf = this._opsLastFilters || {};
        const selectedDay = dayEl?.value || preset.day || lf.day || '';
        const selectedShift = document.getElementById('ops-shift-filter')?.value || preset.shift || lf.shift || '';
        const selectedLanguage = (document.getElementById('ops-language-filter')?.value || lf.language || '').toLowerCase();
        const selectedLocation = (document.getElementById('ops-location-filter')?.value || lf.location || '').trim();
        const selectedResponsible = (document.getElementById('ops-responsible-filter')?.value || lf.responsible || '').trim();
        const nameQuery = (document.getElementById('ops-name-filter')?.value || lf.name || '').trim().toLowerCase();
        if (preset.day || preset.shift) this._opsFilterPreset = null;

        const uid = this.auth.user.id;
        const myHtml = this.renderOpsMyAssignmentsBlock(uid);

        let swapPanel = '';
        const swaps = ops.swap_requests || [];
        if (this.canManageOps() || this.canReallocateOps()) {
            const pend = swaps.filter((s) => s.status === 'pending');
            swapPanel = `<div class="ops-swap-panel"><h3>${this.escapeHtml(i18n.t('ops_swap_requests_title'))}</h3>${pend.length ? pend.map((s) => `<div class="ops-schedule-card ops-swap-card"><code>${this.escapeHtml(s.id)}</code> — ${this.escapeHtml(i18n.t('ops_swap_assignment'))} <strong>${this.escapeHtml(String(s.assignment_id))}</strong> (${this.escapeHtml(i18n.t('ops_swap_requester'))} ${this.escapeHtml(String(s.requester_id))})<div class="ops-swap-actions"><button type="button" class="ops-btn ops-btn--active" onclick="app.resolveSwapPrompt('${s.id}')">${this.escapeHtml(i18n.t('ops_swap_approve'))}</button><button type="button" class="ops-btn" onclick="app.resolveSwap('${s.id}','rejected','',0)">${this.escapeHtml(i18n.t('ops_swap_reject'))}</button></div></div>`).join('') : `<p class="text-muted">${this.escapeHtml(i18n.t('ops_swap_none'))}</p>`}</div>`;
        }

        let histBlock = '';
        if (this.canManageOps() && Array.isArray(ops.history) && ops.history.length) {
            histBlock = `<div class="ops-history-block"><h3>${this.escapeHtml(i18n.t('ops_history_title'))}</h3><ul>${ops.history.slice(0, 15).map((h) => `<li><code>${this.escapeHtml(h.type || '')}</code> ${this.escapeHtml(h.at || '')} — ${this.escapeHtml(i18n.t('ops_history_assignment'))} ${this.escapeHtml(h.assignment_id || '')}</li>`).join('')}</ul></div>`;
        }

        let items = ops.schedule.slice();
        if (selectedDay) items = items.filter((i) => i.day === selectedDay);
        if (selectedShift) items = items.filter((i) => i.shift === selectedShift);
        if (selectedLocation) items = items.filter((i) => (i.location || '') === selectedLocation);
        if (nameQuery) {
            items = items.filter((i) => String(i.volunteer_name || '').toLowerCase().includes(nameQuery));
        }
        if (selectedLanguage) {
            items = items.filter((i) => (i.languages || []).some((lang) => String(lang).toLowerCase().includes(selectedLanguage)));
        }
        if (selectedResponsible) {
            items = items.filter((i) => this.itemMatchesOpsResponsibleFilter(i, selectedResponsible));
        }

        const locations = [...new Set((ops.schedule || []).map((i) => i.location).filter(Boolean))].sort();
        const responsibleNames = this.collectOpsShiftResponsibleNames();
        const responsibleOptions = responsibleNames.map((name) =>
            `<option value="${this.escapeHtml(name)}"${selectedResponsible === name ? ' selected' : ''}>${this.escapeHtml(name)}</option>`
        ).join('');
        const locOptions = locations.map((loc) =>
            `<option value="${this.escapeHtml(loc)}"${selectedLocation === loc ? ' selected' : ''}>${this.escapeHtml(loc)}</option>`
        ).join('');

        const governance = ops.governance || {};
        const governanceHtml = this.canShowOpsGovernance()
            ? Object.entries(governance).map(([day, data]) => this.renderOpsGovernanceCard(day, data)).join('')
            : '';

        const viewMode = this.getOpsScheduleViewMode();
        const scheduleHtml = viewMode === 'list'
            ? this.renderOpsDayGroups(items, uid, true)
            : this.renderOpsShiftSchedule(items, uid, true);

        const editBtn = this.canEditSchedule()
            ? `<button type="button" class="ops-btn ops-btn--accent" onclick="app.openScheduleEditor()">${this.escapeHtml(i18n.t('ops_schedule_edit_btn'))}</button>`
            : '';
        const exportBtn = this.canManageOps()
            ? `<button type="button" id="ops-export-pdf-btn" class="ops-btn ops-btn--accent ops-export-btn" onclick="app.downloadOpsExportPdf()">${this.escapeHtml(i18n.t('ops_export_pdf'))}</button>`
            : '';

        container.innerHTML = `
            ${staleBanner}
            ${myHtml}
            ${swapPanel}
            ${histBlock}
            <div class="ops-toolbar">
                <div class="ops-filters">
                    <span class="ops-view-toggle" role="group" aria-label="${this.escapeHtml(i18n.t('ops_view_mode_label'))}">
                        <button type="button" class="avisos-filter-chip${viewMode === 'shift' ? ' active' : ''}" onclick="app.setOpsScheduleViewMode('shift')">${this.escapeHtml(i18n.t('ops_view_by_shift'))}</button>
                        <button type="button" class="avisos-filter-chip${viewMode === 'list' ? ' active' : ''}" onclick="app.setOpsScheduleViewMode('list')">${this.escapeHtml(i18n.t('ops_view_list'))}</button>
                    </span>
                    <button type="button" class="avisos-filter-chip" onclick="app.applyOpsFilterMyShift()">${this.escapeHtml(i18n.t('ops_filter_my_shift'))}</button>
                    <select id="ops-day-filter" onchange="app.renderVolunteerOps()">
                        <option value="">${this.escapeHtml(i18n.t('ops_filter_all_days'))}</option>
                        <option value="sexta" ${selectedDay === 'sexta' ? 'selected' : ''}>${this.escapeHtml(this.getOpsDayLabel('sexta'))}</option>
                        <option value="sabado" ${selectedDay === 'sabado' ? 'selected' : ''}>${this.escapeHtml(this.getOpsDayLabel('sabado'))}</option>
                        <option value="domingo" ${selectedDay === 'domingo' ? 'selected' : ''}>${this.escapeHtml(this.getOpsDayLabel('domingo'))}</option>
                    </select>
                    <select id="ops-shift-filter" onchange="app.renderVolunteerOps()">
                        <option value="">${this.escapeHtml(i18n.t('ops_filter_all_shifts'))}</option>
                        <option value="A1" ${selectedShift === 'A1' ? 'selected' : ''}>A1</option>
                        <option value="B1" ${selectedShift === 'B1' ? 'selected' : ''}>B1</option>
                        <option value="A2" ${selectedShift === 'A2' ? 'selected' : ''}>A2</option>
                        <option value="B2" ${selectedShift === 'B2' ? 'selected' : ''}>B2</option>
                    </select>
                    <select id="ops-location-filter" onchange="app.renderVolunteerOps()">
                        <option value="">${this.escapeHtml(i18n.t('ops_filter_all_locations'))}</option>
                        ${locOptions}
                    </select>
                    ${responsibleNames.length ? `<select id="ops-responsible-filter" onchange="app.renderVolunteerOps()" aria-label="${this.escapeHtml(i18n.t('ops_filter_responsible_label'))}">
                        <option value="">${this.escapeHtml(i18n.t('ops_filter_all_responsibles'))}</option>
                        ${responsibleOptions}
                    </select>` : ''}
                    <input id="ops-name-filter" value="${this.escapeHtml(nameQuery)}" oninput="app.renderVolunteerOps()" placeholder="${this.escapeHtml(i18n.t('ops_filter_name_placeholder'))}">
                    <input id="ops-language-filter" value="${this.escapeHtml(selectedLanguage)}" oninput="app.renderVolunteerOps()" placeholder="${this.escapeHtml(i18n.t('ops_filter_language_placeholder'))}">
                </div>
                <div class="ops-toolbar-actions">${editBtn}${exportBtn}</div>
            </div>
            ${governanceHtml ? `<details class="ops-governance-details"><summary>${this.escapeHtml(i18n.t('ops_governance_title'))}</summary><div class="ops-governance-grid">${governanceHtml}</div></details>` : ''}
            <h3 class="ops-schedule-heading">${this.escapeHtml(i18n.t('ops_team_schedule'))}</h3>
            <div class="ops-schedule-by-day">${scheduleHtml}</div>
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
            this.renderHomeWeatherWidget();
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

document.addEventListener('zelo:langChanged', () => {
    if ( typeof app !== 'undefined' && typeof app.refreshViewForLanguage === 'function' ) {
        app.refreshViewForLanguage();
    }
});

// Start app
document.addEventListener('DOMContentLoaded', () => {
    app.init();

    // PWA Install Prompt Logic
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        app.data.installPrompt = e;
        app.updateInstallMenuItem();
    });

    window.addEventListener('appinstalled', () => {
        console.log('a2hs installed');
        app.data.installPrompt = null;
        app.updateInstallMenuItem();
    });

    // Network Status Logic
    const updateNetworkStatus = () => {
        const statusEl = document.getElementById('network-status');
        if (navigator.onLine) {
            statusEl.textContent = i18n.t('network_online');
            statusEl.classList.remove('offline');
            statusEl.classList.add('online');
        } else {
            statusEl.textContent = i18n.t('network_offline');
            statusEl.classList.add('offline');
            statusEl.classList.remove('online');
        }
    };

    window.addEventListener('online', updateNetworkStatus);
    window.addEventListener('offline', updateNetworkStatus);

    // Initial check
    updateNetworkStatus();

    // Update External Links
    const linkLostPwd = document.getElementById('link-lost-password');
    if (linkLostPwd && API.siteUrl) {
        linkLostPwd.href = `${API.siteUrl}/wp-login.php?action=lostpassword`;
    }
});
