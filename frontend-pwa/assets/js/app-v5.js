const app = {
    data: {
        locais: [],
        evento: null,
        clima: null,
        categoriesMeta: {},
        volunteerOps: null,
        news: null,
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

    /** Slug WP do post Novidades com instruções completas (admin cria com este slug). */
    PRESS_INSTRUCTIONS_NEWS_SLUG: 'imprensa-autoridades',

    _dataStale: {
        locais: false,
        ops: false,
        clima: false,
        evento: false,
        news: false,
        newsCarousel: false,
        indoorMap: false
    },

    // --- Helpers ---
    renderStaleBadge(scope) {
        const keyMap = {
            ops: 'data_stale_ops',
            locais: 'data_stale_locais',
            indoor: 'data_stale_indoor',
            news: 'data_stale_news',
            newsCarousel: 'data_stale_news'
        };
        const key = keyMap[scope] || 'data_stale_generic';
        return `<span class="zelo-stale-badge" role="status">${this.escapeHtml(i18n.t(key))}</span>`;
    },

    syncStaleFlags() {
        if (typeof API === 'undefined' || !API.lastFetchFromCache) return;
        this._dataStale.locais = !!API.lastFetchFromCache.locais || !!API.lastFetchRevalidating.locais;
        this._dataStale.ops = !!API.lastFetchFromCache.volunteerOps || !!API.lastFetchRevalidating.volunteerOps;
        this._dataStale.clima = !!API.lastFetchFromCache.clima || !!API.lastFetchRevalidating.clima;
        this._dataStale.evento = !!API.lastFetchFromCache.evento || !!API.lastFetchRevalidating.evento;
        this._dataStale.news = !!API.lastFetchFromCache.news || !!API.lastFetchRevalidating.news;
        this._dataStale.newsCarousel = !!API.lastFetchFromCache.newsCarousel || !!API.lastFetchRevalidating.newsCarousel;
        this._dataStale.indoorMap = !!API.lastFetchFromCache.indoorMap || !!API.lastFetchRevalidating.indoorMap;
        this.updateNetworkDegradedBanner();
    },

    updateNetworkDegradedBanner() {
        const el = document.getElementById('network-degraded-banner');
        if (!el || typeof API === 'undefined') return;
        const revalidating = API.isAnyCriticalRevalidating && API.isAnyCriticalRevalidating();
        const staleCritical = navigator.onLine && API.hasBannerCriticalCacheStale && API.hasBannerCriticalCacheStale();
        const noData = !this.data.evento || !Object.keys(this.data.evento).length;
        if (noData && !navigator.onLine) {
            el.hidden = false;
            el.textContent = i18n.t('network_no_data');
            return;
        }
        if (revalidating) {
            el.hidden = false;
            el.textContent = i18n.t('network_revalidating');
            return;
        }
        if (staleCritical) {
            el.hidden = false;
            el.textContent = i18n.t('network_slow_cached');
            return;
        }
        el.hidden = true;
    },

    _retryStaleCriticalTimer: null,

    retryStaleCriticalData() {
        if (!navigator.onLine || typeof API === 'undefined') return;
        clearTimeout(this._retryStaleCriticalTimer);
        this._retryStaleCriticalTimer = setTimeout(() => this._runRetryStaleCritical(), 400);
    },

    async _runRetryStaleCritical() {
        if (!navigator.onLine || typeof API === 'undefined') return;
        if (!API.hasBannerCriticalCacheStale || !API.hasBannerCriticalCacheStale()) {
            this.syncStaleFlags();
            return;
        }
        const tasks = [];
        if (API.lastFetchFromCache.locais) {
            tasks.push(API.getLocais());
        }
        if (API.lastFetchFromCache.evento) {
            tasks.push(API.getEvento());
        }
        if (API.lastFetchFromCache.volunteerOps && this.auth.user && this.canViewOps()) {
            tasks.push(this.loadVolunteerOps(true));
        }
        if (!tasks.length) {
            this.syncStaleFlags();
            return;
        }
        await Promise.allSettled(tasks);
        this.syncStaleFlags();
        this._renderBootstrapUI();
        this._refreshCurrentView();
    },

    _hydrateFromSnapshots() {
        if (typeof API === 'undefined') return;
        const locais = API.readSnapshot('zelo_locais');
        if (locais) {
            this.data.locais = locais;
            API.lastFetchFromCache.locais = true;
        }
        const evento = API.readSnapshot('zelo_evento');
        if (evento) {
            this.data.evento = evento;
            API.lastFetchFromCache.evento = true;
        }
        const categorias = API.readSnapshot('zelo_categorias');
        if (categorias) {
            API.lastFetchFromCache.categorias = true;
        }
        const clima = API.readSnapshot('zelo_clima');
        if (clima) {
            this.data.clima = clima;
            API.lastFetchFromCache.clima = true;
        }
        const indoor = API.readSnapshot(API.indoorMapSnapshotKey);
        if (indoor && indoor.image_url) {
            this.data.indoorMap = indoor;
            API.lastFetchFromCache.indoorMap = true;
        }
        const ops = API.readSnapshot('zelo_volunteer_ops');
        if (ops) {
            this.data.volunteerOps = ops;
            API.lastFetchFromCache.volunteerOps = true;
        }
        if (categorias) {
            this.data.categoriesMeta = this.buildCategoryMeta(categorias);
        }
        if (this.auth.user && this.canViewOps()) {
            const uid = this.auth.user.id;
            const news = API.readSnapshot(API.newsSnapshotKey(uid));
            if (news) {
                this.data.news = news;
                API.cache.news = news;
                API.lastFetchFromCache.news = true;
            }
            const carousel = API.readSnapshot(API.newsCarouselSnapshotKey(uid));
            if (carousel && Array.isArray(carousel.items)) {
                this.data.newsCarousel = carousel;
                API.lastFetchFromCache.newsCarousel = true;
            }
        }
    },

    _applyRevalidatedData(scope, data) {
        if (!data) return;
        switch (scope) {
            case 'locais':
                this.data.locais = data;
                break;
            case 'evento':
                this.data.evento = data;
                break;
            case 'categorias':
                this.data.categoriesMeta = this.buildCategoryMeta(data || []);
                break;
            case 'clima':
                this.data.clima = data;
                break;
            case 'indoorMap':
                this.data.indoorMap = (data && data.image_url) ? data : null;
                break;
            case 'volunteerOps':
                if (data && !data.__authError) {
                    this.data.volunteerOps = data;
                }
                break;
            case 'news':
                this.data.news = data;
                break;
            case 'newsCarousel':
                this.data.newsCarousel = data && Array.isArray(data.items) ? data : null;
                break;
            default:
                break;
        }
    },

    _refreshCurrentView() {
        const viewId = this.router.currentView;
        if (!viewId) return;
        if (viewId === 'home') {
            this.renderHomeWeatherWidget();
            this.renderHomeVolunteerDashboard();
            this.renderHomeNewsCard();
            this.renderEventBanner();
            this.renderHomeMap();
        } else if (viewId === 'evento') {
            this.renderEventInfo();
        } else if (viewId === 'escala') {
            this.renderVolunteerOps();
        } else if (viewId === 'tempo') {
            this.renderWeather();
        } else if (viewId === 'mapa-evento') {
            this.renderIndoorEventMap();
        } else if (viewId === 'mapa') {
            MapManager.setCategoryMeta(this.data.categoriesMeta);
            MapManager.addMarkers(this.data.locais);
        } else if (viewId === 'avisos') {
            this.renderAvisos();
        } else if (viewId === 'blog') {
            this.renderBlog();
        }
    },

    _bindServiceWorkerMessages() {
        if (this._swMessageBound || !('serviceWorker' in navigator)) return;
        this._swMessageBound = true;
        navigator.serviceWorker.addEventListener('message', (event) => {
            const msg = event.data || {};
            if (msg.type === 'zelo:push' || msg.type === 'zelo:notificationclick') {
                this.handlePushClientMessage(msg);
            }
        });
    },

    navigateFromPushUrl(url) {
        if (!url) return;
        try {
            const u = new URL(url, window.location.href);
            const raw = (u.hash || '').replace(/^#/, '');
            if (!raw) return;
            const qIdx = raw.indexOf('?');
            const viewId = qIdx >= 0 ? raw.slice(0, qIdx) : raw;
            const qs = qIdx >= 0 ? raw.slice(qIdx + 1) : '';
            const params = Object.fromEntries(new URLSearchParams(qs));
            if (this.router.canAccessView(viewId)) {
                this.router.navigate(viewId, params);
            }
        } catch (e) {
            console.warn('Push URL inválida', url, e);
        }
    },

    showPushForegroundToast(title, body, url) {
        let el = document.getElementById('zelo-push-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'zelo-push-toast';
            el.className = 'zelo-push-toast';
            el.setAttribute('role', 'button');
            el.tabIndex = 0;
            document.body.appendChild(el);
        }
        const safeTitle = this.escapeHtml(title || i18n.t('push_toast_default_title'));
        const safeBody = body ? `<div class="zelo-push-toast-body">${this.escapeHtml(body)}</div>` : '';
        el.innerHTML = `<div class="zelo-push-toast-title">${safeTitle}</div>${safeBody}<div class="zelo-push-toast-action">${this.escapeHtml(i18n.t('push_toast_open'))}</div>`;
        el.hidden = false;
        el.onclick = () => {
            el.hidden = true;
            if (url) {
                this.navigateFromPushUrl(url);
            }
        };
        clearTimeout(this._pushToastTimer);
        this._pushToastTimer = setTimeout(() => {
            el.hidden = true;
        }, 9000);
    },

    async refreshNewsLive() {
        if (!this.auth.user || !this.canViewOps()) {
            return;
        }
        await Promise.all([
            this.loadNews(1, false, { forceFresh: true }),
            this.loadNewsCarousel({ forceFresh: true })
        ]);
        this._renderBootstrapUI();
        this._refreshCurrentView();
    },

    async handlePushClientMessage(msg) {
        if (this.auth.user && this.canViewOps()) {
            await this.refreshNewsLive();
        }
        if (msg.type === 'zelo:notificationclick' && msg.url) {
            this.navigateFromPushUrl(msg.url);
            return;
        }
        if (msg.type === 'zelo:push' && document.visibilityState === 'visible') {
            this.showPushForegroundToast(msg.title, msg.body, msg.url);
        }
    },

    _maybeRefreshNewsOnVisible() {
        if (document.visibilityState !== 'visible' || !navigator.onLine) {
            return;
        }
        if (!this.auth.user || !this.canViewOps()) {
            return;
        }
        const now = Date.now();
        if (this._lastNewsVisibleRefreshAt && now - this._lastNewsVisibleRefreshAt < 15000) {
            return;
        }
        this._lastNewsVisibleRefreshAt = now;
        this.refreshNewsLive();
    },

    _renderBootstrapUI() {
        this.auth.refreshAuthChrome();
        this.renderEventBanner();
        this.renderHomeMap();
        this.renderHomeVolunteerDashboard();
        this.renderHomeNewsCard();
        this.renderHomeWeatherWidget();
        this.toggleHomeVisitorExtrasCollapse();
        this.updateHomeOpsVisibility();
        this.updateNotificationsBadge();
        this.updateHomePressInstructionsBtn();
        this.syncStaleFlags();
    },

    _bindDataRevalidationListener() {
        if (this._revalidationBound) return;
        this._revalidationBound = true;
        window.addEventListener('zelo:data-revalidated', (e) => {
            const detail = e.detail || {};
            this._applyRevalidatedData(detail.scope, detail.data);
            this.syncStaleFlags();
            this._renderBootstrapUI();
            this._refreshCurrentView();
        });
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

    _avatarBust: 0,
    _profileAvatarPreviewUrl: null,

    bustAvatarUrl(url) {
        if (!url || url.indexOf('blob:') === 0 || url.indexOf('images/') === 0) {
            return url;
        }
        if (!this._avatarBust) {
            return url;
        }
        const sep = url.includes('?') ? '&' : '?';
        return url + sep + '_v=' + this._avatarBust;
    },

    setAvatarBust() {
        this._avatarBust = Date.now();
    },

    revokeProfileAvatarPreview() {
        if (this._profileAvatarPreviewUrl) {
            URL.revokeObjectURL(this._profileAvatarPreviewUrl);
            this._profileAvatarPreviewUrl = null;
        }
    },

    setAvatarImgElement(img, url) {
        if (!img) return;
        img.onerror = function () {
            this.onerror = null;
            this.src = 'images/default-avatar.png';
        };
        img.src = url;
    },

    updateAvatarDisplays(rawUrl) {
        const url = this.bustAvatarUrl(rawUrl || this.getAvatarUrl());
        this.setAvatarImgElement(document.getElementById('profile-avatar'), url);
        const iconContainer = document.getElementById('user-auth-indicator');
        if (iconContainer && this.auth.user) {
            const safe = this.escapeHtml(url);
            iconContainer.innerHTML = `<img src="${safe}" alt="" onerror="this.onerror=null;this.src='images/default-avatar.png';">`;
        }
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
            gradient: `linear-gradient(135deg, ${this.hexToRgba(base.color, 0.95)}, ${base.color})`,
            isEmergency: slug === 'emergencia'
        };
    },

    isEmergencyCategory(slug) {
        return slug === 'emergencia';
    },

    getEmergencyLangKey() {
        const c = i18n.current || 'pt_br';
        if (c === 'en') return 'en';
        if (c === 'es') return 'es';
        return 'pt';
    },

    pickLocalizedField(obj, langKey) {
        if (!obj || typeof obj !== 'object') return '';
        const val = obj[langKey] || obj.pt || obj.en || obj.es || '';
        return String(val).trim();
    },

    formatTelHref(number) {
        const digits = String(number || '').replace(/[^\d+]/g, '');
        return digits || '';
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
            if (viewId === 'mapa-evento' || viewId === 'blog' || viewId === 'blog-post') {
                return app.canViewOps();
            }
            if (viewId === 'delegado-registro') {
                return app.canViewOps();
            }
            if (viewId === 'delegado-lista') {
                return app.canManageOps();
            }
            if (viewId === 'cadastros-pendentes') {
                return !!(app.auth.user && app.auth.user.site_admin);
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
                } else if (viewId === 'cadastros-pendentes') {
                    app.renderVolunteerApprovals();
                } else if (viewId === 'blog') {
                    app.renderBlog();
                } else if (viewId === 'blog-post') {
                    app.renderBlogPost(params.id);
                } else if (viewId === 'delegado-registro') {
                    app.renderDelegateSupportForm();
                } else if (viewId === 'delegado-lista') {
                    app.renderDelegateSupportList();
                }

                // Render Home components if on home
                if (viewId === 'home') {
                    app.renderHomeWeatherWidget();
                    app.renderHomeNotice();
                    app.renderEventBanner();
                    app.renderHomeMap();
                    app.renderHomeVolunteerDashboard();
                    app.renderHomeNewsCard();
                    app.toggleHomeVisitorExtrasCollapse();
                    app.updateNotificationsBadge();
                    app.updateHomePressInstructionsBtn();
                    app.updateHomeDelegateListBtn();
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
                const url = `${API.baseUrl}/auth/login`;

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
                        await app.loadNews();
                        await app.loadNewsCarousel();
                    } else {
                        app.data.volunteerOps = null;
                        API.clearOpsRelatedSnapshots(this.user.id);
                        app.data.news = null;
                        app.data.newsCarousel = null;
                        app.data.indoorMap = null;
                    }

                    app.router.navigate('home');
                    app.maybePromptPushConsent();

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
            const uid = this.user && this.user.id;
            if (typeof app.unregisterPushSubscription === 'function') {
                app.unregisterPushSubscription().catch(() => {});
            }
            this.user = null;
            localStorage.removeItem('zelo_user');
            API.clearOpsRelatedSnapshots(uid);
            app.data.volunteerOps = null;
            app.data.news = null;
            app.data.newsCarousel = null;
            app.data.indoorMap = null;
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

            if (this.user) {
                if (iconContainer) {
                    iconContainer.setAttribute('title', this.user.name || i18n.t('my_profile'));
                }
                app.updateAvatarDisplays(app.getAvatarUrl());
                const pRole = document.getElementById('profile-role');
                if (pRole) pRole.textContent = app.getOpsRoleLabel() || this.user.roles[0] || i18n.t('visitor_role');
                app.populateProfileForm();
            } else if (iconContainer) {
                iconContainer.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>`;
                iconContainer.setAttribute('title', i18n.t('auth_sign_in'));
            }

            app.updateBottomNavForVolunteer();
            app.updateNewsMenuItem();
            app.updateHomeOpsVisibility();
            app.renderHomeNewsCard();
            app.updateNotificationsBadge();
            app.renderProfileLanguages();
            app.renderProfileAdminSection();
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
            this.auth.user = API.persistAuthUser(res.user, res.nonce);
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
        this.renderProfilePush();
        this.renderProfileAdminSection();
    },

    bindProfileAvatarInput() {
        const input = document.getElementById('profile-avatar-input');
        if (!input || input._zeloAvatarBound) return;
        input._zeloAvatarBound = true;
        input.addEventListener('change', () => this.uploadProfileAvatar(input));
    },

    showProfileAvatarMessage(text, type = 'info') {
        const msg = document.getElementById('profile-avatar-msg');
        if (!msg) return;
        msg.style.display = text ? 'block' : 'none';
        msg.className = 'profile-avatar-msg' + (type ? ' ' + type : '');
        msg.textContent = text || '';
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

        this.revokeProfileAvatarPreview();
        this._profileAvatarPreviewUrl = URL.createObjectURL(file);
        this.updateAvatarDisplays(this._profileAvatarPreviewUrl);
        this.showProfileAvatarMessage(i18n.t('profile_avatar_uploading'), 'info');

        try {
            const res = await API.uploadProfileAvatar(file);
            this.revokeProfileAvatarPreview();
            this.setAvatarBust();
            this.applyProfileApiResponse(res);
            this.showProfileAvatarMessage(i18n.t('profile_avatar_saved'), 'success');
        } catch (e) {
            this.revokeProfileAvatarPreview();
            this.auth.refreshAuthChrome();
            this.showProfileAvatarMessage(e.message || i18n.t('error_generic'), 'error');
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

        this._bindDataRevalidationListener();
        this._bindServiceWorkerMessages();

        try {
            this.auth.init();
            this._hydrateFromSnapshots();
            this.syncStaleFlags();
            this._renderBootstrapUI();

            const verifyParamsEarly = new URLSearchParams(window.location.search);
            if (verifyParamsEarly.get('zelo_verified') !== '1') {
                this.resolveInitialNavigation();
                this._initialNavDone = true;
            }

            const sessionPromise = this.auth.user
                ? API.refreshSession().then((synced) => {
                    if (synced) {
                        this.auth.user = synced;
                        app.cacheUserAvatar(synced.avatar);
                        this.auth.refreshAuthChrome();
                    }
                    return synced;
                })
                : Promise.resolve(null);

            const indoorPromise = (this.auth.user && this.auth.user.caps && this.auth.user.caps.view_ops)
                ? API.getIndoorMap(true).catch(() => ({}))
                : Promise.resolve({});

            const results = await Promise.allSettled([
                API.getLocais(),
                API.getEvento(),
                API.getCategorias(),
                API.getClima().catch(() => null),
                indoorPromise
            ]);

            const pick = (idx, fallback) => {
                const r = results[idx];
                return r && r.status === 'fulfilled' ? r.value : fallback;
            };

            this.data.locais = pick(0, this.data.locais) || [];
            this.data.evento = pick(1, this.data.evento) || {};
            const categoriasData = pick(2, API.readSnapshot('zelo_categorias') || []);
            this.data.categoriesMeta = this.buildCategoryMeta(categoriasData || []);
            this.data.clima = pick(3, this.data.clima);
            const indoorMap = pick(4, {});
            this.data.indoorMap = (indoorMap && indoorMap.image_url) ? indoorMap : this.data.indoorMap;

            this.syncStaleFlags();
            this._renderBootstrapUI();

            if (this.auth.user && this.auth.user.caps && this.auth.user.caps.view_ops) {
                const opsResult = await Promise.allSettled([
                    sessionPromise,
                    this.loadVolunteerOps(true)
                ]);
                const sessionSynced = opsResult[0].status === 'fulfilled' && opsResult[0].value;
                const opsVal = opsResult[1].status === 'fulfilled' ? opsResult[1].value : null;
                if (opsVal && opsVal.__authError) {
                    this.auth.handleOpsAuthFailure();
                } else if (!sessionSynced && !opsVal && !this.data.volunteerOps) {
                    this.auth.handleOpsAuthFailure();
                } else if (sessionSynced && !this._opsAuthFailed) {
                    this.auth.clearOpsAuthFailure();
                }
            } else {
                await sessionPromise;
            }

            if (this.auth.user && this.canViewOps()) {
                Promise.all([this.loadNews(), this.loadNewsCarousel()]).then(() => {
                    this._renderBootstrapUI();
                    this._refreshCurrentView();
                });
            } else if (this.auth.user) {
                API.clearOpsRelatedSnapshots(this.auth.user.id);
                this.data.news = null;
                this.data.newsCarousel = null;
                if (!this.canViewOps()) {
                    this.data.indoorMap = null;
                }
            }

            console.log('Data loaded', this.data);

        } catch (err) {
            console.error('Failed to load data', err);
            this.updateNetworkDegradedBanner();
        }

        const verifyParams = new URLSearchParams(window.location.search);
        if (verifyParams.get('zelo_verified') === '1') {
            verifyParams.delete('zelo_verified');
            const qs = verifyParams.toString();
            const cleanUrl = window.location.pathname + (qs ? `?${qs}` : '') + window.location.hash;
            history.replaceState({}, '', cleanUrl);
            this.router.navigate('email-verified', {}, { persist: false });
        } else if (!this._initialNavDone) {
            this.resolveInitialNavigation();
        }

        if (this.auth.user) {
            setTimeout(() => this.maybePromptPushConsent(), 800);
        }

        this.getUserLocation();

        setTimeout(() => {
            if (typeof API !== 'undefined' && API.hasBannerCriticalCacheStale && API.hasBannerCriticalCacheStale()) {
                this.retryStaleCriticalData();
            }
        }, 6500);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState !== 'visible' || !navigator.onLine) {
                return;
            }
            this.retryStaleCriticalData();
            if (this.router.currentView === 'tempo' && this.shouldRefreshWeather()) {
                this.refreshWeather();
            }
            this._maybeRefreshNewsOnVisible();
        });
    },

    resolveInitialNavigation() {
        const route = this.router.resolveInitialRoute();
        const { viewId, params } = route;
        if (this.router.canAccessView(viewId)) {
            this.router.navigate(viewId, params);
            return;
        }
        if (viewId === 'escala' || viewId === 'profile' || viewId === 'blog' || viewId === 'blog-post' || viewId === 'mapa-evento' || viewId === 'cadastros-pendentes' || viewId === 'delegado-registro' || viewId === 'delegado-lista') {
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
                this.renderHomeNewsCard();
                this.toggleHomeVisitorExtrasCollapse();
                this.updateNotificationsBadge();
                this.updateHomePressInstructionsBtn();
                this.updateHomeDelegateListBtn();
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
            case 'cadastros-pendentes':
                this.renderVolunteerApprovals();
                break;
            case 'profile':
                this.populateProfileForm();
                break;
            case 'register':
                this.loadRegisterLanguages();
                break;
            case 'blog':
                this.renderBlog({ keepPage: true });
                break;
            case 'blog-post':
                this.renderBlogPost(params.id);
                break;
            case 'delegado-registro':
                this.renderDelegateSupportForm();
                break;
            case 'delegado-lista':
                this.renderDelegateSupportList();
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

    async loadNews(page = 1, append = false, options = {}) {
        if (!this.auth.user || !this.canViewOps()) {
            this.data.news = null;
            return null;
        }
        const data = await API.getNews(
            { page, per_page: 20, forceFresh: !!options.forceFresh },
            this.auth.user.id
        );
        if (!data) {
            if (!append) {
                this.data.news = null;
            }
            this.syncStaleFlags();
            return data;
        }
        if (append && this.data.news && Array.isArray(this.data.news.items)) {
            this.data.news = {
                ...data,
                items: this.data.news.items.concat(data.items || [])
            };
        } else {
            this.data.news = data;
        }
        this.syncStaleFlags();
        this.updateNotificationsBadge();
        this.updateHomePressInstructionsBtn();
        return this.data.news;
    },

    getPressInstructionsPostId() {
        const slug = this.PRESS_INSTRUCTIONS_NEWS_SLUG;
        const items = (this.data.news && Array.isArray(this.data.news.items)) ? this.data.news.items : [];
        const fromNews = items.find((p) => p.slug === slug);
        if (fromNews) return Number(fromNews.id);
        const fromCarousel = this.getNewsCarouselItems().find((p) => p.slug === slug);
        return fromCarousel ? Number(fromCarousel.id) : null;
    },

    async openPressInstructionsPost() {
        if (!this.auth.user) {
            this.router.navigate('login');
            return;
        }
        if (!this.canViewOps()) {
            return;
        }
        let id = this.getPressInstructionsPostId();
        if (!id) {
            await this.loadNews(1, false);
            id = this.getPressInstructionsPostId();
        }
        if (id) {
            this.router.navigate('blog-post', { id });
        } else {
            this.router.navigate('blog');
        }
    },

    updateHomePressInstructionsBtn() {
        const btn = document.getElementById('home-press-instructions-btn');
        if (!btn) return;
        const show = this.canViewOps() && !!this.getPressInstructionsPostId();
        btn.hidden = !show;
    },

    formatNewsDate(iso) {
        if (!iso) return '';
        try {
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return '';
            return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
        } catch (e) {
            return '';
        }
    },

    getNewsNotificationItems() {
        const items = (this.data.news && Array.isArray(this.data.news.items)) ? this.data.news.items : [];
        return items.filter((p) => p.as_notification);
    },

    updateNewsMenuItem() {
        const item = document.getElementById('header-menu-news');
        if (!item) return;
        item.hidden = !this.canViewOps();
    },

    async loadNewsCarousel(options = {}) {
        if (!this.auth.user || !this.canViewOps()) {
            this.data.newsCarousel = null;
            return null;
        }
        const data = await API.getNews(
            { carousel_only: true, per_page: 8, page: 1, forceFresh: !!options.forceFresh },
            this.auth.user.id
        );
        this.data.newsCarousel = data && Array.isArray(data.items) ? data : null;
        this.syncStaleFlags();
        return this.data.newsCarousel;
    },

    getNewsCarouselItems() {
        return (this.data.newsCarousel && Array.isArray(this.data.newsCarousel.items))
            ? this.data.newsCarousel.items
            : [];
    },

    onHomeNewsCarouselScroll(track) {
        if (!track || !track.parentElement) return;
        const dots = track.parentElement.querySelectorAll('.home-news-carousel-dot');
        if (!dots.length) return;
        const slideW = track.offsetWidth * 0.88 || 1;
        let idx = Math.round(track.scrollLeft / slideW);
        idx = Math.max(0, Math.min(idx, dots.length - 1));
        dots.forEach((d, i) => d.classList.toggle('active', i === idx));
    },

    renderHomeNewsCard() {
        const el = document.getElementById('home-news-card');
        const opsBtn = document.getElementById('home-ops-news-btn');
        const canOps = this.canViewOps();

        if (opsBtn) {
            opsBtn.hidden = !canOps;
        }

        if (!el) return;
        if (!canOps) {
            el.hidden = true;
            el.innerHTML = '';
            return;
        }

        const carouselItems = this.getNewsCarouselItems().filter((p) => p.featured_image);
        const stale = this._dataStale.newsCarousel ? this.renderStaleBadge('newsCarousel') : '';

        if (carouselItems.length > 0) {
            el.hidden = false;
            const slides = carouselItems.map((post, i) => {
                const title = this.escapeHtml(this.decodeHtmlEntities(post.title || ''));
                const date = this.escapeHtml(this.formatNewsDate(post.published_at));
                const img = this.escapeHtml(post.featured_image);
                const id = Number(post.id);
                return `
                    <article class="home-news-carousel-slide" role="group" aria-roledescription="slide" aria-label="${i + 1} / ${carouselItems.length}">
                        <button type="button" class="home-news-carousel-slide-btn" onclick="app.router.navigate('blog-post', { id: ${id} }); return false;">
                            <span class="home-news-carousel-image" style="background-image:url('${img}')"></span>
                            <span class="home-news-carousel-caption">
                                <span class="home-news-carousel-title">${title}</span>
                                <span class="home-news-carousel-date">${date}</span>
                            </span>
                        </button>
                    </article>`;
            }).join('');
            const dots = carouselItems.map((_, i) =>
                `<span class="home-news-carousel-dot${i === 0 ? ' active' : ''}" aria-hidden="true"></span>`
            ).join('');
            el.innerHTML = `
                <div class="home-news-carousel-wrap">
                    <div class="home-section-title home-news-carousel-heading">
                        ${this.escapeHtml(i18n.t('news_section_title'))}
                        ${stale}
                    </div>
                    <div class="home-news-carousel-viewport">
                        <div class="home-news-carousel-track" id="home-news-carousel-track" onscroll="app.onHomeNewsCarouselScroll(this)">
                            ${slides}
                        </div>
                        <div class="home-news-carousel-dots">${dots}</div>
                    </div>
                    <button type="button" class="home-news-carousel-all" onclick="app.router.navigate('blog'); return false;">
                        ${this.escapeHtml(i18n.t('news_carousel_view_all'))}
                    </button>
                </div>`;
            return;
        }

        el.hidden = false;
        el.innerHTML = `
            <div class="home-section-title">${this.escapeHtml(i18n.t('news_section_title'))}</div>
            <button type="button" class="home-news-card" onclick="app.router.navigate('blog'); return false;">
                <span class="home-news-card-icon">📰</span>
                <span class="home-news-card-body">
                    <span class="home-news-card-title">${this.escapeHtml(i18n.t('news_home_card_title'))}</span>
                    <span class="home-news-card-desc">${this.escapeHtml(i18n.t('news_home_card_desc'))}</span>
                </span>
                ${this._dataStale.news ? this.renderStaleBadge('news') : ''}
                <span class="home-news-card-chevron">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </span>
            </button>
        `;
    },

    _blogPage: 1,

    async renderBlog(options = {}) {
        const container = document.getElementById('blog-container');
        if (!container) return;
        if (!this.auth.user) {
            this.router.navigate('login');
            return;
        }

        if (!options.keepPage) {
            container.innerHTML = `<div class="loading">${this.escapeHtml(i18n.t('loading'))}</div>`;
            this._blogPage = 1;
            await this.loadNews(1, false, { forceFresh: true });
        }

        const stale = this._dataStale.news ? this.renderStaleBadge('news') : '';
        const items = (this.data.news && this.data.news.items) || [];
        const total = (this.data.news && this.data.news.total) || 0;

        if (!items.length) {
            container.innerHTML = stale + `<div class="blog-empty">${this.escapeHtml(i18n.t('news_empty'))}</div>`;
            return;
        }

        const list = items.map((post) => this.buildBlogCardHtml(post)).join('');
        const hasMore = items.length < total;
        container.innerHTML = stale + `<div class="blog-list">${list}</div>` +
            (hasMore ? `<button type="button" class="blog-load-more" onclick="app.loadMoreBlog()">${this.escapeHtml(i18n.t('news_load_more'))}</button>` : '');
    },

    buildBlogCardHtml(post) {
        const img = post.featured_image
            ? `<div class="blog-card-image" style="background-image:url('${this.escapeHtml(post.featured_image)}')"></div>`
            : `<div class="blog-card-image blog-card-image--placeholder">📰</div>`;
        return `
            <button type="button" class="blog-card" onclick="app.openBlogPost(${Number(post.id)})">
                ${img}
                <span class="blog-card-body">
                    <span class="blog-card-date">${this.escapeHtml(this.formatNewsDate(post.published_at))}</span>
                    <span class="blog-card-title">${this.formatPlainText(post.title || '')}</span>
                    <span class="blog-card-excerpt">${this.formatPlainText(post.excerpt || '')}</span>
                </span>
            </button>
        `;
    },

    async loadMoreBlog() {
        const btn = document.querySelector('.blog-load-more');
        if (btn) {
            btn.disabled = true;
            btn.textContent = i18n.t('loading');
        }
        this._blogPage += 1;
        await this.loadNews(this._blogPage, true);
        if (this.router.currentView === 'blog') {
            this.renderBlog({ keepPage: true });
        }
    },

    openBlogPost(id) {
        this.router.navigate('blog-post', { id });
    },

    async renderBlogPost(id) {
        const container = document.getElementById('blog-post-container');
        if (!container) return;
        if (!this.auth.user) {
            this.router.navigate('login');
            return;
        }
        if (!id) {
            this.router.navigate('blog');
            return;
        }

        container.innerHTML = `<div class="loading">${this.escapeHtml(i18n.t('loading'))}</div>`;
        try {
            const uid = this.auth.user.id;
            const post = await API.getNewsItem(id, uid);
            if (!post) {
                container.innerHTML = `<div class="blog-empty">${this.escapeHtml(i18n.t('news_not_found'))}</div>`;
                return;
            }
            this.syncStaleFlags();
            const staleBanner = API.lastFetchFromCache.newsDetail
                ? `<div class="zelo-stale-banner blog-post-stale">${this.renderStaleBadge('news')}</div>`
                : '';
            this.markAvisoRead(`post-${post.id}`);
            const img = post.featured_image
                ? `<div class="blog-post-hero" style="background-image:url('${this.escapeHtml(post.featured_image)}')"></div>`
                : '';
            container.innerHTML = staleBanner + `
                <article class="blog-post">
                    ${img}
                    <div class="blog-post-header">
                        <time class="blog-post-date">${this.escapeHtml(this.formatNewsDate(post.published_at))}</time>
                        <h1 class="blog-post-title">${this.formatPlainText(post.title || '')}</h1>
                        ${post.author_name ? `<p class="blog-post-author">${this.escapeHtml(post.author_name)}</p>` : ''}
                    </div>
                    <div class="blog-post-body">${post.content_html || ''}</div>
                </article>
            `;
        } catch (e) {
            const msg = (e && (e.code === 'news_offline_unavailable' || e.message === 'news_offline_unavailable'))
                ? i18n.t('news_offline_unavailable')
                : (e.message || i18n.t('error_generic'));
            container.innerHTML = `<div class="blog-empty">${this.escapeHtml(msg)}</div>`;
        }
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

        if (this.auth.user) {
            this.getNewsNotificationItems().forEach((post) => {
                items.push({
                    id: `post-${post.id}`,
                    category: 'news',
                    icon: post.priority === 'important' ? '📣' : '📰',
                    title: this.decodeHtmlEntities(post.title || ''),
                    summary: this.decodeHtmlEntities(post.excerpt || ''),
                    time: this.formatNewsDate(post.published_at),
                    action: 'news',
                    postId: post.id
                });
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
                        summary: this.formatSwapAvisoSummary(s),
                        time: this.formatOpsSwapCreatedAt(s.created_at),
                        action: 'escala'
                    });
                });
            }

            if (uid) {
                (ops.swap_requests || []).forEach((s) => {
                    if (s.status === 'rejected' && s.requester_id === uid) {
                        const row = this.findOpsScheduleRow(s.assignment_id);
                        const ctx = this.formatOpsAssignmentBrief(row);
                        const reason = (s.rejection_reason || '').trim();
                        items.push({
                            id: `swap-rejected-${s.id}`,
                            category: 'personal',
                            icon: '❌',
                            title: i18n.t('avisos_swap_rejected'),
                            summary: reason
                                ? i18n.t('avisos_swap_rejected_summary').replace('{0}', ctx).replace('{1}', reason)
                                : ctx,
                            time: this.formatOpsSwapCreatedAt(s.resolved_at || s.created_at),
                            action: 'escala'
                        });
                    } else if (s.status === 'approved' && s.requester_id === uid) {
                        const row = this.findOpsScheduleRow(s.assignment_id);
                        items.push({
                            id: `swap-approved-req-${s.id}`,
                            category: 'personal',
                            icon: '✅',
                            title: i18n.t('avisos_swap_approved_requester'),
                            summary: i18n.t('avisos_swap_approved_requester_summary')
                                .replace('{0}', this.formatOpsAssignmentBrief(row))
                                .replace('{1}', s.replacement_name || '—'),
                            time: this.formatOpsSwapCreatedAt(s.resolved_at || s.created_at),
                            action: 'escala'
                        });
                    } else if (s.status === 'approved' && s.replacement_user_id === uid) {
                        const row = this.findOpsScheduleRow(s.assignment_id);
                        items.push({
                            id: `swap-assigned-${s.id}`,
                            category: 'personal',
                            icon: '📋',
                            title: i18n.t('avisos_swap_assigned'),
                            summary: i18n.t('avisos_swap_assigned_summary').replace('{0}', this.formatOpsAssignmentBrief(row)),
                            time: this.formatOpsSwapCreatedAt(s.resolved_at || s.created_at),
                            action: 'escala'
                        });
                    }
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
        } else if (item.action === 'news' && item.postId) {
            this.router.navigate('blog-post', { id: item.postId });
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
        } else if (this._avisosFilter === 'news') {
            items = items.filter((i) => i.category === 'news');
        }

        const showNewsFilter = this.canViewOps();

        const chips = `
            <div class="avisos-toolbar">
                <button type="button" class="avisos-filter-chip ${this._avisosFilter === 'all' ? 'active' : ''}" onclick="app._avisosFilter='all';app.renderAvisos();">${this.escapeHtml(i18n.t('avisos_filter_all'))}</button>
                ${showNewsFilter ? `<button type="button" class="avisos-filter-chip ${this._avisosFilter === 'news' ? 'active' : ''}" onclick="app._avisosFilter='news';app.renderAvisos();">${this.escapeHtml(i18n.t('avisos_filter_news'))}</button>` : ''}
                ${showPersonal ? `<button type="button" class="avisos-filter-chip ${this._avisosFilter === 'personal' ? 'active' : ''}" onclick="app._avisosFilter='personal';app.renderAvisos();">${this.escapeHtml(i18n.t('avisos_filter_personal'))}</button>` : ''}
                <button type="button" class="avisos-filter-chip ${this._avisosFilter === 'event' ? 'active' : ''}" onclick="app._avisosFilter='event';app.renderAvisos();">${this.escapeHtml(i18n.t('avisos_filter_event'))}</button>
                ${items.length ? `<button type="button" class="avisos-mark-all" onclick="app.markAllAvisosRead()">${this.escapeHtml(i18n.t('avisos_mark_all_read'))}</button>` : ''}
            </div>
            ${showNewsFilter ? `<p class="avisos-blog-link"><button type="button" class="avisos-blog-link-btn" onclick="app.router.navigate('blog');">${this.escapeHtml(i18n.t('news_view_all'))}</button></p>` : ''}
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
                        <div class="avisos-item-title">${item.category === 'news' ? this.formatPlainText(item.title) : this.escapeHtml(item.title)}</div>
                        <div class="avisos-item-summary">${item.category === 'news' ? this.formatPlainText(item.summary) : this.escapeHtml(item.summary)}</div>
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
            const emergencyCls = cat.isEmergency ? ' home-search-result-item--emergency' : '';
            return `
                <div class="home-search-result-item${emergencyCls}" onclick="app.handleSearchResultClick(${item.id})">
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
            const emergencyCls = meta.isEmergency ? ' rich-list-card--emergency' : '';

            return `
            <div class="rich-list-card${emergencyCls}" onclick="app.router.navigate('detalhe', {id: ${item.id}})">
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

    canSiteAdmin() {
        return !!(this.auth.user && this.auth.user.site_admin);
    },

    isVolunteerApprovalPending() {
        return !!(this.auth.user && this.auth.user.volunteer_approval_status === 'pending');
    },

    isVolunteerApprovalRejected() {
        return !!(this.auth.user && this.auth.user.volunteer_approval_status === 'rejected');
    },

    updateHomeOpsVisibility() {
        const block = document.getElementById('home-ops-extras-block');
        if (block) {
            block.hidden = !this.canViewOps();
        }
        this.updateHomeDelegateListBtn();
    },

    updateHomeDelegateListBtn() {
        const btn = document.getElementById('home-delegate-list-btn');
        if (btn) {
            btn.hidden = !this.canManageOps();
        }
    },

    renderProfileAdminSection() {
        const section = document.getElementById('profile-admin-section');
        if (!section) return;
        section.style.display = this.canSiteAdmin() ? 'block' : 'none';
        if (this.canSiteAdmin()) {
            this.refreshVolunteerApprovalsCount();
        }
    },

    updateVolunteerApprovalsBadge() {
        const badge = document.getElementById('profile-approvals-badge');
        if (!badge) return;
        const count = this._volunteerApprovalsCount || 0;
        if (count > 0) {
            badge.hidden = false;
            badge.textContent = ` (${count})`;
        } else {
            badge.hidden = true;
            badge.textContent = '';
        }
    },

    async refreshVolunteerApprovalsCount() {
        if (!this.canSiteAdmin()) return;
        try {
            const data = await API.getVolunteerApprovals();
            this._volunteerApprovalsCount = typeof data.count === 'number'
                ? data.count
                : (Array.isArray(data.items) ? data.items.length : 0);
        } catch (e) {
            this._volunteerApprovalsCount = 0;
        }
        this.updateVolunteerApprovalsBadge();
    },

    async renderVolunteerApprovals() {
        const container = document.getElementById('volunteer-approvals-container');
        if (!container) return;
        if (!this.canSiteAdmin()) {
            container.innerHTML = `<p class="text-muted">${this.escapeHtml(i18n.t('ops_no_access'))}</p>`;
            return;
        }
        container.innerHTML = `<div class="loading">${this.escapeHtml(i18n.t('loading'))}</div>`;
        try {
            const data = await API.getVolunteerApprovals();
            const items = Array.isArray(data.items) ? data.items : [];
            this._volunteerApprovalsCount = items.length;
            this.updateVolunteerApprovalsBadge();
            if (!items.length) {
                container.innerHTML = `<p class="description">${this.escapeHtml(i18n.t('admin_volunteer_approvals_empty'))}</p>`;
                return;
            }
            container.innerHTML = items.map((item) => {
                const langs = (item.languages || []).join(', ') || '—';
                const reg = item.registered_at ? this.escapeHtml(String(item.registered_at).slice(0, 10)) : '—';
                const uid = Number(item.user_id);
                return `
                    <div class="volunteer-approval-card profile-card" style="margin-bottom:1rem;">
                        <h3 style="margin:0 0 0.5rem;">${this.escapeHtml(item.name || '')}</h3>
                        <p style="margin:0.25rem 0;">${this.escapeHtml(item.email || '')}</p>
                        <p class="text-muted" style="margin:0.25rem 0;">${this.escapeHtml(item.phone || '')}</p>
                        <p class="text-muted" style="margin:0.5rem 0 1rem;font-size:0.9rem;">${this.escapeHtml(langs)} · ${reg}</p>
                        <div class="volunteer-approval-actions">
                            <button type="button" class="btn-block" onclick="app.approveVolunteerRegistration(${uid})">${this.escapeHtml(i18n.t('admin_volunteer_approvals_approve'))}</button>
                            <button type="button" class="btn-block outline warning" style="margin-top:0.5rem;" onclick="app.rejectVolunteerRegistration(${uid})">${this.escapeHtml(i18n.t('admin_volunteer_approvals_reject'))}</button>
                        </div>
                    </div>`;
            }).join('');
        } catch (e) {
            container.innerHTML = `<p class="text-danger">${this.escapeHtml(e.message || i18n.t('error_generic'))}</p>`;
        }
    },

    async approveVolunteerRegistration(userId) {
        try {
            await API.approveVolunteerRegistration(userId);
            alert(i18n.t('admin_volunteer_approvals_success_approve'));
            await this.renderVolunteerApprovals();
        } catch (e) {
            alert(e.message || i18n.t('error_generic'));
        }
    },

    async rejectVolunteerRegistration(userId) {
        if (!confirm(i18n.t('admin_volunteer_approvals_confirm_reject'))) return;
        try {
            await API.rejectVolunteerRegistration(userId);
            alert(i18n.t('admin_volunteer_approvals_success_reject'));
            await this.renderVolunteerApprovals();
        } catch (e) {
            alert(e.message || i18n.t('error_generic'));
        }
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

    isAssignmentFutureEventDay(item) {
        const dates = this.data.volunteerOps?.settings?.event_dates || {};
        const ymd = dates[item.day];
        if (!ymd) return false;
        const today = new Date();
        const todayYmd = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        return ymd > todayYmd;
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
        return this.maybePromptPushConsent();
    },

    _pushConsentKey: 'zelo_push_consent_v3',
    _pushVapidFpKey: 'zelo_push_vapid_fp',
    _pushStatusCache: null,

    _pushVapidFingerprintOk(status) {
        const fp = status && status.vapidPublicKeyFingerprint;
        if (!fp) return true;
        const stored = localStorage.getItem(this._pushVapidFpKey);
        if (!stored) return false;
        return stored === fp;
    },

    _pushSubscriptionKeyMatchesVapid(sub, publicKey) {
        if (!sub || !publicKey) return false;
        const appKey = sub.options && sub.options.applicationServerKey;
        if (!appKey) return false;
        const expected = this._urlBase64ToUint8Array(publicKey);
        if (appKey.byteLength !== expected.byteLength) return false;
        for (let i = 0; i < appKey.byteLength; i++) {
            if (appKey[i] !== expected[i]) return false;
        }
        return true;
    },

    _pushStoreVapidFingerprint(status) {
        const fp = status && status.vapidPublicKeyFingerprint;
        if (fp) {
            localStorage.setItem(this._pushVapidFpKey, fp);
        }
    },

    _pushClearVapidFingerprint() {
        localStorage.removeItem(this._pushVapidFpKey);
    },

    isPushEffectivelyActive(status, permission) {
        if (!status || !status.enabled) return false;
        const perm = permission || (('Notification' in window) ? Notification.permission : 'denied');
        return !!(status.subscribed && perm === 'granted' && this._pushVapidFingerprintOk(status));
    },

    _urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    },

    async registerPushSubscription() {
        if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
            throw new Error(i18n.t('ops_push_unsupported'));
        }
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            throw new Error(i18n.t('ops_push_denied'));
        }
        const vapid = await API.getPushVapidPublic();
        const publicKey = vapid && vapid.publicKey;
        if (!publicKey) {
            throw new Error(i18n.t('ops_push_unavailable'));
        }
        const reg = await navigator.serviceWorker.ready;
        let sub = await reg.pushManager.getSubscription();
        if (sub && !this._pushSubscriptionKeyMatchesVapid(sub, publicKey)) {
            try {
                await sub.unsubscribe();
            } catch (e) {
                console.warn('Push unsubscribe stale', e);
            }
            sub = null;
        }
        if (!sub) {
            sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this._urlBase64ToUint8Array(publicKey)
            });
        }
        await API.subscribePush(sub.toJSON());
        const status = await this.getPushStatusCached(true);
        this._pushStoreVapidFingerprint(status || vapid);
        this._pushStatusCache = null;
        return true;
    },

    async unregisterPushSubscription() {
        if (!('serviceWorker' in navigator)) {
            return;
        }
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
            try {
                await API.unsubscribePush(sub.endpoint);
            } catch (e) {
                console.warn('Push unsubscribe API', e);
            }
            try {
                await sub.unsubscribe();
            } catch (e) {
                console.warn('Push unsubscribe browser', e);
            }
        } else {
            await API.unsubscribePush('');
        }
        this._pushClearVapidFingerprint();
        this._pushStatusCache = null;
    },

    async getPushStatusCached(force = false) {
        if (!this.auth.user) return null;
        if (this._pushStatusCache && !force) return this._pushStatusCache;
        try {
            this._pushStatusCache = await API.getPushStatus();
        } catch (e) {
            this._pushStatusCache = { enabled: false, subscribed: false, devices: 0 };
        }
        return this._pushStatusCache;
    },

    async maybePromptPushConsent() {
        if (!this.auth.user || !this.canViewOps()) return;
        if (!('Notification' in window) || !('serviceWorker' in navigator)) return;
        if (localStorage.getItem(this._pushConsentKey) === '1') return;
        const status = await this.getPushStatusCached();
        if (!status || !status.enabled) {
            localStorage.setItem(this._pushConsentKey, '1');
            return;
        }
        const perm = Notification.permission;
        if (this.isPushEffectivelyActive(status, perm)) {
            localStorage.setItem(this._pushConsentKey, '1');
            return;
        }
        const ok = confirm(`${i18n.t('ops_push_prompt')}\n\n${i18n.t('ops_push_prompt_body')}`);
        localStorage.setItem(this._pushConsentKey, '1');
        if (!ok) return;
        try {
            await this.registerPushSubscription();
        } catch (e) {
            console.warn('Push subscribe', e);
        }
    },

    async renderProfilePush() {
        const section = document.getElementById('profile-push-section');
        const statusEl = document.getElementById('profile-push-status');
        const enableBtn = document.getElementById('profile-push-enable-btn');
        const disableBtn = document.getElementById('profile-push-disable-btn');
        if (!section || !this.auth.user || !this.canViewOps()) {
            if (section) section.style.display = 'none';
            return;
        }
        const status = await this.getPushStatusCached(true);
        if (!status || !status.enabled) {
            section.style.display = 'none';
            return;
        }
        section.style.display = 'block';
        const perm = ('Notification' in window) ? Notification.permission : 'denied';
        const fpOk = this._pushVapidFingerprintOk(status);
        const effectivelyActive = this.isPushEffectivelyActive(status, perm);
        let statusText = i18n.t('profile_push_off');
        if (effectivelyActive) {
            statusText = i18n.t('profile_push_on');
        } else if (status.subscribed && perm === 'granted' && !fpOk) {
            statusText = i18n.t('profile_push_reactivate');
        } else if (perm === 'denied') {
            statusText = i18n.t('profile_push_blocked');
        }
        if (statusEl) statusEl.textContent = statusText;
        if (enableBtn) {
            enableBtn.style.display = !effectivelyActive ? 'block' : 'none';
            if (!enableBtn._zeloBound) {
                enableBtn._zeloBound = true;
                enableBtn.addEventListener('click', () => this.onProfilePushEnable());
            }
        }
        if (disableBtn) {
            disableBtn.style.display = (status.subscribed || effectivelyActive) ? 'block' : 'none';
            if (!disableBtn._zeloBound) {
                disableBtn._zeloBound = true;
                disableBtn.addEventListener('click', () => this.onProfilePushDisable());
            }
        }
    },

    async onProfilePushEnable() {
        try {
            await this.registerPushSubscription();
            this.showProfileFormMessage(i18n.t('profile_push_enabled'), 'success');
            await this.renderProfilePush();
        } catch (e) {
            this.showProfileFormMessage(e.message || i18n.t('ops_push_unavailable'), 'error');
        }
    },

    async onProfilePushDisable() {
        if (!confirm(i18n.t('profile_push_disable_confirm'))) return;
        try {
            await this.unregisterPushSubscription();
            this.showProfileFormMessage(i18n.t('profile_push_disabled'), 'success');
            await this.renderProfilePush();
        } catch (e) {
            this.showProfileFormMessage(e.message || i18n.t('ops_push_unavailable'), 'error');
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

    decodeHtmlEntities(str) {
        if (str == null || str === '') return '';
        let s = String(str);
        s = s.replace(/&#(8211|8212|8210|8722);|&(?:ndash|mdash|minus);/gi, '-');
        const el = document.createElement('textarea');
        el.innerHTML = s;
        return el.value.replace(/[\u2010\u2011\u2012\u2013\u2014\u2212]/g, '-');
    },

    formatPlainText(str) {
        return this.escapeHtml(this.decodeHtmlEntities(str));
    },

    getOpsStatusLabel(status) {
        const s = status || 'pending';
        if (s === 'checked_in') return i18n.t('ops_status_checked_in');
        if (s === 'checked_out') return i18n.t('ops_status_checked_out');
        return i18n.t('ops_status_pending');
    },

    getOpsStatusBadge(assignmentId, item) {
        const commitSt = this.getCommitmentStatus(assignmentId);
        if (commitSt === 'declined') return '';

        const status = this.getCheckinStatus(assignmentId).status || 'pending';
        if (commitSt === 'accepted' && status === 'pending' && item && this.isAssignmentFutureEventDay(item)) {
            return `<span class="ops-status-badge ops-status-awaiting_day">${this.escapeHtml(i18n.t('ops_status_awaiting_day'))}</span>`;
        }
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
            swap: `<svg class="ops-icon-btn__svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" ${stroke} aria-hidden="true"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 5h18"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 19H3"></path></svg>`,
            remove: `<svg class="ops-icon-btn__svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" ${stroke} aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>`,
            edit: `<svg class="ops-icon-btn__svg" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" ${stroke} aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>`
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
        let noteHtml = '';

        if (commitSt === 'pending') {
            if (this.canCommitAssignment(item)) {
                if (!onBehalf && this.getCommitmentPendingReason(item.id) === 'schedule_changed') {
                    const note = `<p class="text-muted ops-volunteer-hint">${this.escapeHtml(i18n.t('ops_schedule_changed_hint'))}</p>`;
                    if (iconBar) hintHtml += note;
                    else if (compactRow) noteHtml += `<p class="text-muted home-assignment-note">${this.escapeHtml(i18n.t('ops_schedule_changed_hint'))}</p>`;
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
                else if (compactRow) noteHtml += `<p class="text-muted home-assignment-note">${this.escapeHtml(i18n.t('ops_commitment_deadline_passed'))}</p>`;
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
        if (compactRow && (noteHtml || html)) {
            const actionsPart = html ? `<div class="home-assignment-actions">${html}</div>` : '';
            return `${noteHtml}${actionsPart}`;
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

        if (!this.auth.user) {
            container.hidden = true;
            container.innerHTML = '';
            return;
        }

        if (!this.canViewOps()) {
            if (this.isVolunteerApprovalPending() && !this._approvalRefreshAttempted) {
                this._approvalRefreshAttempted = true;
                const synced = await API.refreshSession();
                if (synced) {
                    this.auth.user = synced;
                    app.cacheUserAvatar(synced.avatar);
                    if (this.canViewOps()) {
                        return this.renderHomeVolunteerDashboard();
                    }
                }
            }
            if (this.isVolunteerApprovalPending()) {
                container.hidden = false;
                container.innerHTML = `<p class="home-ops-link-pending" style="background:#fef3c7;padding:0.75rem;border-radius:8px;font-size:0.9rem;">${this.escapeHtml(i18n.t('volunteer_approval_pending_banner'))}</p>`;
                return;
            }
            if (this.isVolunteerApprovalRejected()) {
                container.hidden = false;
                container.innerHTML = `<p class="home-subscriber-rejected-banner" style="background:#fee2e2;padding:0.75rem;border-radius:8px;font-size:0.9rem;">${this.escapeHtml(i18n.t('volunteer_approval_rejected_banner'))}</p>`;
                return;
            }
            container.hidden = true;
            container.innerHTML = '';
            return;
        }

        this._approvalRefreshAttempted = false;

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
        const actionableRows = myRows.filter((i) => this.isMyAssignmentActionable(i));

        let linkBanner = '';
        if (ops && ops.link_pending) {
            linkBanner = `<p class="home-ops-link-pending" style="background:#fef3c7;padding:0.75rem;border-radius:8px;font-size:0.9rem;">${i18n.t('ops_link_pending')}</p>`;
        }

        let assignmentsHtml = '';
        if (!myRows.length) {
            assignmentsHtml = `<p class="text-muted">${i18n.t('ops_no_assignments')}</p>`;
        } else if (!actionableRows.length) {
            assignmentsHtml = `<p class="home-assignments-all-clear">${this.escapeHtml(i18n.t('ops_assignments_all_clear'))}</p>`;
        } else {
            assignmentsHtml = actionableRows.map((item) => {
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
                            ${this.getCommitmentBadge(item.id)} ${this.getOpsStatusBadge(item.id, item)}
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
        if (!this.canViewOps()) {
            el.innerHTML = `<p class="text-muted">${this.escapeHtml(i18n.t('ops_no_access'))}</p>`;
            return;
        }
        const hasScreen = !!el.querySelector('.indoor-map-screen');
        const cachedCfg = this.data.indoorMap;
        const useCachedDom = hasScreen && cachedCfg && cachedCfg.image_url;
        if (!useCachedDom) {
            el.innerHTML = `<div class="loading">${i18n.t('loading')}</div>`;
            if (this._indoorGestureAbort) {
                this._indoorGestureAbort();
                this._indoorGestureAbort = null;
            }
        }
        const cfg = useCachedDom ? cachedCfg : await API.getIndoorMap(true);
        this.data.indoorMap = cfg;
        this.syncStaleFlags();
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

        if (useCachedDom) {
            const staleBannerEl = el.querySelector('.zelo-stale-banner.indoor-map-stale');
            if (this._dataStale.indoorMap && !staleBannerEl) {
                el.insertAdjacentHTML('afterbegin', `<div class="zelo-stale-banner indoor-map-stale">${this.renderStaleBadge('indoor')}</div>`);
            } else if (!this._dataStale.indoorMap && staleBannerEl) {
                staleBannerEl.remove();
            }
            this._indoorDirectionsText = this.getIndoorDirections(cfg, ui.boothId, ui.destId) || '';
            this._patchIndoorMapSelection();
            this._syncIndoorDiagramFullscreen();
            this._syncIndoorTabDom(ui.tab);
            return;
        }

        const dirText = this.getIndoorDirections(cfg, ui.boothId, ui.destId);
        const notice = cfg.volunteer_notice || {};
        const lang = i18n.current || 'pt_br';
        const noticeText = notice[lang] || notice.pt_br || '';

        const boothOpts = booths.map((b) => {
            const lab = this.indoorPlaceLabel(b);
            const sel = b.id === ui.boothId ? ' selected' : '';
            return `<option value="${this.escapeAttr(b.id)}"${sel}>${this.escapeHtml(lab)}</option>`;
        }).join('');

        const destInputValue = this.getIndoorDestInputDisplayValue(ui, dests);
        const comboboxOpenClass = ui.comboboxOpen ? ' is-open' : '';
        const listHtml = this.buildIndoorDestDropdownHtml(dests, ui);

        const staleBanner = this._dataStale.indoorMap
            ? `<div class="zelo-stale-banner indoor-map-stale">${this.renderStaleBadge('indoor')}</div>`
            : '';

        const guideTabActive = ui.tab === 'guide';
        const mapTabActive = ui.tab === 'map';

        let pinsHtml = this._buildIndoorPinsHtml(places, booths, ui);

        const legendHtml = this.buildIndoorMapLegendHtml(booths);

        el.innerHTML = staleBanner + `
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
        if (ui) ui._diagramLayoutApplied = false;
        this._indoorDirectionsText = dirText || '';
        this._syncIndoorDiagramFullscreen();
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

    _buildIndoorPinsHtml(places, booths, ui) {
        let pinsHtml = '';
        (places || []).forEach((p) => {
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
        return pinsHtml;
    },

    _refreshIndoorDiagramPins(places, booths) {
        const layer = document.querySelector('#indoor-map-viewport .indoor-pins-layer');
        if (!layer) return;
        const ui = this.data.indoorMapUi || {};
        layer.innerHTML = this._buildIndoorPinsHtml(places, booths, ui);
    },

    _patchIndoorMapSelection() {
        if (!document.querySelector('.indoor-map-screen')) return false;
        const cfg = this.data.indoorMap;
        if (!cfg) return false;
        const ui = this.data.indoorMapUi || {};
        const places = Array.isArray(cfg.places) ? cfg.places : [];
        const booths = places.filter((p) => p.kind === 'booth');
        const dests = places.filter((p) => p.kind !== 'booth');
        const boothSel = document.querySelector('.indoor-booth-select');
        if (boothSel && ui.boothId) boothSel.value = ui.boothId;
        const destInput = document.getElementById('indoor-dest-input');
        if (destInput) destInput.value = this.getIndoorDestInputDisplayValue(ui, dests);
        this.refreshIndoorDestDropdown(dests);
        this.refreshIndoorDirectionsPanel();
        this._refreshIndoorDiagramPins(places, booths);
        this._updateIndoorDiagramGoDestBtn();
        return true;
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

    _syncIndoorDiagramFullscreen() {
        document.body.classList.remove('indoor-diagram-fullscreen');
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
        const diagramPanel = viewport.closest('.indoor-diagram-panel');
        if (diagramPanel && !ui._diagramLayoutApplied) {
            diagramPanel.classList.add('is-preparing');
        }

        const ac = new AbortController();
        this._indoorGestureAbort = () => ac.abort();

        const revealDiagram = () => {
            if (diagramPanel) diagramPanel.classList.remove('is-preparing');
        };

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
            } else if (!ui._diagramLayoutApplied || !zoom.scale || zoom.scale <= 0.01 || !isFinite(zoom.scale)) {
                applied = this._fitIndoorDiagram(viewport, layer, zoom);
            } else {
                applied = true;
            }
            if (applied) {
                ui._diagramLayoutApplied = true;
                this._applyIndoorDiagramTransform(zoom);
                revealDiagram();
            }
            return applied;
        };

        let layoutRetries = 0;
        const tryApplyLayout = () => {
            if (applyFocusIfNeeded()) return;
            layoutRetries += 1;
            if (layoutRetries < 4 && (ui._focusDiagram === 'fit' || ui._focusDiagram === 'place' || !ui._diagramLayoutApplied)) {
                requestAnimationFrame(tryApplyLayout);
            } else {
                revealDiagram();
            }
        };

        if (img && img.complete) {
            tryApplyLayout();
        } else if (img) {
            img.onload = () => tryApplyLayout();
        }

        if (typeof ResizeObserver !== 'undefined') {
            const ro = new ResizeObserver(() => {
                if (ui._focusDiagram === 'fit' || ui._focusDiagram === 'place') {
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

    _indoorDiagramDomReady() {
        return !!document.getElementById('indoor-map-viewport')
            && !!document.getElementById('indoor-map-transform')
            && !!this.data.indoorMap
            && !!this.data.indoorMap.image_url;
    },

    _updateIndoorDiagramGoDestBtn() {
        const ui = this.data.indoorMapUi || {};
        const btn = document.querySelector('.indoor-diagram-panel .indoor-diagram-btn.primary');
        if (!btn) return;
        const enabled = !!(ui.destId || ui.boothId);
        btn.disabled = !enabled;
    },

    _applyIndoorDiagramFocusInPlace() {
        if (!this._indoorDiagramDomReady()) return false;
        const viewport = document.getElementById('indoor-map-viewport');
        const layer = document.getElementById('indoor-map-transform');
        const img = document.getElementById('indoor-map-img');
        const ui = this.data.indoorMapUi || {};
        if (!ui.zoom) ui.zoom = { scale: 1, tx: 0, ty: 0 };
        const cfg = this.data.indoorMap;
        const places = Array.isArray(cfg.places) ? cfg.places : [];
        const focusMode = ui._focusDiagram;
        const diagramPanel = viewport.closest('.indoor-diagram-panel');
        if (diagramPanel && focusMode) diagramPanel.classList.add('is-preparing');

        const apply = () => {
            if (!focusMode || !this._indoorDiagramLayoutReady(viewport, img)) return false;
            let ok = false;
            if (focusMode === 'fit') {
                ok = this._fitIndoorDiagram(viewport, layer, ui.zoom);
            } else if (focusMode === 'place') {
                const focusId = ui.destId || ui.boothId;
                const place = places.find((p) => p.id === focusId);
                const targetScale = this.isIndoorMobileLayout() ? 2.5 : 2;
                if (place) {
                    this._focusIndoorDiagramOnPlace(viewport, layer, place, ui.zoom, targetScale);
                    ok = true;
                } else {
                    ok = this._fitIndoorDiagram(viewport, layer, ui.zoom);
                }
            }
            if (ok) {
                ui._focusDiagram = false;
                ui._diagramLayoutApplied = true;
                this._applyIndoorDiagramTransform(ui.zoom);
                this._updateIndoorDiagramGoDestBtn();
                if (diagramPanel) diagramPanel.classList.remove('is-preparing');
            }
            return ok;
        };

        if (apply()) return true;
        requestAnimationFrame(() => {
            if (!apply() && diagramPanel) diagramPanel.classList.remove('is-preparing');
        });
        return true;
    },

    _syncIndoorTabDom(tab) {
        const container = document.getElementById('indoor-map-container');
        const screen = container && container.querySelector('.indoor-map-screen');
        if (!screen || !this.data.indoorMap || !this.data.indoorMap.image_url) return false;

        const ui = this.data.indoorMapUi;
        const guideActive = tab === 'guide';
        const mapActive = tab === 'map';
        const diagramPanel = screen.querySelector('.indoor-diagram-panel');
        if (mapActive && diagramPanel) {
            if (!ui._diagramLayoutApplied) {
                diagramPanel.classList.add('is-preparing');
            } else if (ui.zoom) {
                this._applyIndoorDiagramTransform(ui.zoom);
            }
        } else if (!mapActive && diagramPanel) {
            diagramPanel.classList.remove('is-preparing');
        }

        const tabs = screen.querySelectorAll('.indoor-map-tab');
        tabs.forEach((btn, idx) => {
            btn.classList.toggle('active', (idx === 0 && guideActive) || (idx === 1 && mapActive));
        });

        screen.querySelectorAll('.indoor-panel').forEach((panel) => {
            const isDiagram = panel.classList.contains('indoor-diagram-panel');
            panel.classList.toggle('hidden', isDiagram ? !mapActive : !guideActive);
        });

        this._syncIndoorDiagramFullscreen();
        const cfg = this.data.indoorMap;
        const places = Array.isArray(cfg.places) ? cfg.places : [];

        if (mapActive) {
            requestAnimationFrame(() => {
                if (ui._diagramLayoutApplied && ui.zoom) {
                    if (diagramPanel) diagramPanel.classList.remove('is-preparing');
                } else if (!ui._focusDiagram) {
                    ui._focusDiagram = 'fit';
                }
                if (!this._indoorGestureAbort) {
                    this.initIndoorDiagramGestures(cfg, places);
                } else if (ui._focusDiagram) {
                    this._applyIndoorDiagramFocusInPlace();
                }
            });
        } else {
            if (diagramPanel) diagramPanel.classList.remove('is-preparing');
            const dests = places.filter((p) => p.kind !== 'booth');
            this.initIndoorDestCombobox(dests);
            this.refreshIndoorDirectionsPanel();
        }
        return true;
    },

    indoorDiagramFitAll() {
        if (!this.data.indoorMapUi) this.data.indoorMapUi = {};
        this.data.indoorMapUi._focusDiagram = 'fit';
        if (this._applyIndoorDiagramFocusInPlace()) return;
        this.renderIndoorEventMap();
    },

    indoorDiagramGoToDest() {
        if (!this.data.indoorMapUi) this.data.indoorMapUi = {};
        if (!this.data.indoorMapUi.destId && !this.data.indoorMapUi.boothId) return;
        this.data.indoorMapUi._focusDiagram = 'place';
        if (this._applyIndoorDiagramFocusInPlace()) return;
        this.renderIndoorEventMap();
    },

    setIndoorTab(tab) {
        if (!this.data.indoorMapUi) this.data.indoorMapUi = {};
        this.data.indoorMapUi._userPickedTab = true;
        this.data.indoorMapUi.tab = tab;
        if (this._syncIndoorTabDom(tab)) return;
        if (tab === 'map') {
            this.data.indoorMapUi._focusDiagram = 'fit';
        }
        this.renderIndoorEventMap();
    },

    setIndoorBooth(boothId) {
        if (!this.data.indoorMapUi) this.data.indoorMapUi = {};
        this.data.indoorMapUi.boothId = boothId;
        if (this._patchIndoorMapSelection()) return;
        this.renderIndoorEventMap();
    },

    selectIndoorDest(destId) {
        if (!this.data.indoorMapUi) this.data.indoorMapUi = {};
        this.data.indoorMapUi.destId = destId;
        this.data.indoorMapUi.query = '';
        this.data.indoorMapUi.comboboxOpen = false;
        this.data.indoorMapUi.comboboxEditing = false;
        if (this._patchIndoorMapSelection()) {
            requestAnimationFrame(() => {
                const panel = document.getElementById('indoor-directions-panel');
                if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
            return;
        }
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
        if (this._patchIndoorMapSelection()) {
            if (this.data.indoorMapUi.tab === 'map') {
                this._syncIndoorTabDom('map');
                this._applyIndoorDiagramFocusInPlace();
            }
            return;
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

    async resolveSwap(id, status, extra = {}) {
        try {
            const res = await API.patchSwapRequest(id, status, extra);
            if (res.data) this.data.volunteerOps = res.data;
            this.closeSwapResolveModal();
            this.renderVolunteerOps();
            this.updateNotificationsBadge();
        } catch (e) {
            if (this._swapResolveModal) {
                this._swapResolveModal.error = e.message || i18n.t('ops_swap_generic_fail');
                this._swapResolveModal.saving = false;
                this.renderSwapResolveModal();
            } else {
                alert(e.message || i18n.t('ops_swap_generic_fail'));
            }
        }
    },

    openSwapRejectModal(id) {
        const swap = (this.data.volunteerOps?.swap_requests || []).find((s) => s.id === id);
        if (!swap) return;
        this._swapResolveModal = {
            mode: 'reject',
            swapId: id,
            swap,
            rejectionReason: '',
            replacementUserId: 0,
            saving: false,
            error: ''
        };
        this.renderSwapResolveModal();
    },

    async openSwapApproveModal(id) {
        const swap = (this.data.volunteerOps?.swap_requests || []).find((s) => s.id === id);
        if (!swap) return;
        try {
            await this.loadVolunteerOps(true);
        } catch (e) {
            /* usa cache se rede falhar */
        }
        this._swapResolveModal = {
            mode: 'approve',
            swapId: id,
            swap: (this.data.volunteerOps?.swap_requests || []).find((s) => s.id === id) || swap,
            rejectionReason: '',
            replacementUserId: 0,
            saving: false,
            error: ''
        };
        this.renderSwapResolveModal();
    },

    closeSwapResolveModal() {
        this._swapResolveModal = null;
        const overlay = document.getElementById('ops-swap-resolve-overlay');
        if (overlay) overlay.remove();
        document.body.classList.remove('ops-modal-open');
        this._unbindSwapResolveEscape();
    },

    getSwapSubstituteCandidates(excludeRequesterId) {
        const ops = this.data.volunteerOps || {};
        const exclude = parseInt(excludeRequesterId, 10) || 0;
        const byWp = {};
        const add = (c) => {
            const uid = parseInt(c.wp_user_id, 10) || 0;
            if (!uid || uid === exclude) return;
            byWp[uid] = { wp_user_id: uid, name: c.name || String(uid) };
        };
        (ops.swap_roster_candidates || []).forEach(add);
        if (!Object.keys(byWp).length) {
            (ops.catalogs?.wp_users || []).forEach((u) => {
                add({ wp_user_id: u.id, name: u.name });
            });
        }
        return Object.values(byWp).sort((a, b) => String(a.name).localeCompare(String(b.name), 'pt-BR'));
    },

    buildSwapSubstituteOptions(selectedUid, excludeRequesterId) {
        const candidates = this.getSwapSubstituteCandidates(excludeRequesterId);
        let html = `<option value="">${this.escapeHtml(i18n.t('ops_swap_pick_substitute'))}</option>`;
        candidates.forEach((c) => {
            const uid = parseInt(c.wp_user_id, 10) || 0;
            if (!uid) return;
            const sel = uid === selectedUid ? ' selected' : '';
            html += `<option value="${uid}"${sel}>${this.escapeHtml(c.name || String(uid))}</option>`;
        });
        return html;
    },

    renderSwapResolveModal() {
        const mod = this._swapResolveModal;
        if (!mod) return;
        const swap = mod.swap || {};
        const row = this.findOpsScheduleRow(swap.assignment_id);
        const name = this.getSwapRequesterDisplayName(swap, row);
        const context = this.escapeHtml(this.formatOpsAssignmentBrief(row));
        const isReject = mod.mode === 'reject';
        const titleKey = isReject ? 'ops_swap_reject_modal_title' : 'ops_swap_approve_modal_title';
        const saving = !!mod.saving;
        const errorHtml = mod.error
            ? `<p class="ops-confirm-error" role="alert">${this.escapeHtml(mod.error)}</p>`
            : '';
        const substituteCount = !isReject ? this.getSwapSubstituteCandidates(swap.requester_id).length : 0;
        const emptySubstitutesHtml = !isReject && substituteCount === 0
            ? `<p class="ops-confirm-warning">${this.escapeHtml(i18n.t('ops_swap_no_substitutes'))}</p>`
            : '';
        const bodyFields = isReject
            ? `<label class="ops-editor-field ops-editor-field--full">
                    <span class="ops-editor-field-label">${this.escapeHtml(i18n.t('ops_swap_reject_reason_label'))}</span>
                    <textarea id="ops-swap-reject-reason" rows="4" ${saving ? 'disabled' : ''} placeholder="${this.escapeAttr(i18n.t('ops_swap_reject_reason_placeholder'))}">${this.escapeHtml(mod.rejectionReason || '')}</textarea>
               </label>`
            : `<label class="ops-editor-field ops-editor-field--full">
                    <span class="ops-editor-field-label">${this.escapeHtml(i18n.t('ops_swap_substitute_label'))}</span>
                    <p class="ops-confirm-meta text-muted">${this.escapeHtml(i18n.t('ops_swap_substitute_hint'))}</p>
                    <select id="ops-swap-substitute" ${saving ? 'disabled' : ''}>${this.buildSwapSubstituteOptions(mod.replacementUserId, swap.requester_id)}</select>
               </label>`;
        const confirmKey = isReject ? 'ops_swap_reject_confirm' : 'ops_swap_approve_confirm';
        const confirmFn = isReject ? 'app.confirmSwapRejectModal()' : 'app.confirmSwapApproveModal()';
        let overlay = document.getElementById('ops-swap-resolve-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'ops-swap-resolve-overlay';
            overlay.className = 'ops-confirm-overlay';
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay && !mod.saving) this.closeSwapResolveModal();
            });
            document.body.appendChild(overlay);
        }
        overlay.innerHTML = `
            <div class="ops-confirm-panel ops-confirm-panel--form" role="dialog" aria-modal="true" aria-labelledby="ops-swap-resolve-title">
                <header class="ops-confirm-header">
                    <h2 id="ops-swap-resolve-title">${this.escapeHtml(i18n.t(titleKey))}</h2>
                    <button type="button" class="ops-editor-close-btn" onclick="app.closeSwapResolveModal()" aria-label="${this.escapeHtml(i18n.t('ops_editor_close_aria'))}" ${saving ? 'disabled' : ''}>×</button>
                </header>
                <div class="ops-confirm-body">
                    <p class="ops-confirm-volunteer"><strong>${this.escapeHtml(name)}</strong></p>
                    <p class="ops-confirm-meta text-muted">${context}</p>
                    ${emptySubstitutesHtml}
                    ${bodyFields}
                    ${errorHtml}
                </div>
                <footer class="ops-confirm-footer">
                    <button type="button" class="btn-block outline" onclick="app.closeSwapResolveModal()" ${saving ? 'disabled' : ''}>${this.escapeHtml(i18n.t('ops_remove_declined_cancel'))}</button>
                    <button type="button" class="btn-block${isReject ? ' ops-confirm-danger-btn' : ''}" onclick="${confirmFn}" ${saving ? 'disabled' : ''}>${this.escapeHtml(saving ? i18n.t('ops_swap_resolving') : i18n.t(confirmKey))}</button>
                </footer>
            </div>
        `;
        document.body.classList.add('ops-modal-open');
        this._bindSwapResolveEscape();
    },

    confirmSwapRejectModal() {
        const mod = this._swapResolveModal;
        if (!mod || mod.saving || mod.mode !== 'reject') return;
        const el = document.getElementById('ops-swap-reject-reason');
        const reason = (el ? el.value : mod.rejectionReason || '').trim();
        if (!reason) {
            mod.error = i18n.t('ops_swap_reject_reason_required');
            this.renderSwapResolveModal();
            return;
        }
        mod.rejectionReason = reason;
        mod.saving = true;
        mod.error = '';
        this.renderSwapResolveModal();
        this.resolveSwap(mod.swapId, 'rejected', { rejection_reason: reason });
    },

    confirmSwapApproveModal() {
        const mod = this._swapResolveModal;
        if (!mod || mod.saving || mod.mode !== 'approve') return;
        const el = document.getElementById('ops-swap-substitute');
        const uid = el ? parseInt(el.value, 10) || 0 : mod.replacementUserId;
        if (!uid) {
            mod.error = i18n.t('ops_swap_substitute_required');
            this.renderSwapResolveModal();
            return;
        }
        mod.replacementUserId = uid;
        mod.saving = true;
        mod.error = '';
        this.renderSwapResolveModal();
        this.resolveSwap(mod.swapId, 'approved', { replacement_user_id: uid });
    },

    _bindSwapResolveEscape() {
        if (this._swapResolveEscapeBound) return;
        this._swapResolveEscapeHandler = (e) => {
            if (e.key === 'Escape' && this._swapResolveModal && !this._swapResolveModal.saving) {
                this.closeSwapResolveModal();
            }
        };
        document.addEventListener('keydown', this._swapResolveEscapeHandler);
        this._swapResolveEscapeBound = true;
    },

    _unbindSwapResolveEscape() {
        if (!this._swapResolveEscapeBound) return;
        document.removeEventListener('keydown', this._swapResolveEscapeHandler);
        this._swapResolveEscapeBound = false;
    },

    async openVolunteerOps() {
        if (!this.auth.user) {
            this.router.navigate('login');
            return;
        }
        if (!this.canViewOps()) {
            if (this.isVolunteerApprovalPending()) {
                alert(i18n.t('volunteer_approval_pending_banner'));
            } else {
                alert(i18n.t('ops_no_access'));
            }
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

    findOpsScheduleRow(assignmentId) {
        if (!assignmentId) return null;
        const schedule = this.data.volunteerOps?.schedule || [];
        return schedule.find((r) => r.id === assignmentId) || null;
    },

    getPendingSwapForAssignment(assignmentId) {
        return (this.data.volunteerOps?.swap_requests || []).find(
            (s) => s.assignment_id === assignmentId && s.status === 'pending'
        ) || null;
    },

    canRemoveScheduleAssignment(item) {
        if (!item || !item.id) return false;
        if (!this.canEditScheduleScope(item.day, item.shift)) return false;
        const st = this.getCommitmentStatus(item.id);
        return st === 'pending' || st === 'declined' || st === 'accepted';
    },

    getRemoveScheduleWarningNote(assignmentId) {
        const st = this.getCommitmentStatus(assignmentId);
        if (st === 'accepted') {
            const presence = this.getCheckinStatus(assignmentId).status || 'pending';
            if (presence === 'checked_in') {
                return i18n.t('ops_remove_schedule_checked_in_note');
            }
            return i18n.t('ops_remove_schedule_accepted_note');
        }
        if (st === 'pending' && this.getCommitmentPendingReason(assignmentId) === 'schedule_changed') {
            return i18n.t('ops_remove_schedule_changed_note');
        }
        if (st === 'pending') {
            return i18n.t('ops_remove_schedule_pending_note');
        }
        return '';
    },

    buildScheduleScopeRowsExcluding(assignmentId) {
        const row = this.findOpsScheduleRow(assignmentId);
        if (!row || !row.day || !row.shift) return null;
        const rows = this.mapScheduleScopeRows(row.day, row.shift)
            .filter((r) => r.id !== assignmentId);
        return { day: row.day, shift: row.shift, rows };
    },

    mapScheduleScopeRows(day, shift) {
        return (this.data.volunteerOps?.schedule || [])
            .filter((r) => r.day === day && r.shift === shift)
            .map((r) => ({
                id: r.id,
                start: r.start || '',
                end: r.end || '',
                volunteer_ref: this.volunteerRefFromRow(r)
            }))
            .filter((r) => r.volunteer_ref);
    },

    scheduleAssignmentFingerprint(volunteerRef, start, end) {
        return `${volunteerRef || ''}|${start || ''}|${end || ''}`;
    },

    canEditScheduleRow(item) {
        if (!item || !item.id) return false;
        if (this.getCommitmentStatus(item.id) === 'declined') return false;
        return this.canEditScheduleScope(item.day, item.shift);
    },

    getDefaultRowTimesForShift(shift, slotStart, slotEnd) {
        const sh = this.getShiftCatalogEntry(shift);
        return {
            start: slotStart || sh?.start || '',
            end: slotEnd || sh?.end || ''
        };
    },

    buildScheduleScopeRowsForAdd(day, shift, newRow) {
        const rows = this.mapScheduleScopeRows(day, shift);
        rows.push({
            start: newRow.start || '',
            end: newRow.end || '',
            volunteer_ref: newRow.volunteer_ref || ''
        });
        return { day, shift, rows: rows.filter((r) => r.volunteer_ref) };
    },

    buildScheduleScopeRowsForEdit(assignmentId, patch) {
        const row = this.findOpsScheduleRow(assignmentId);
        if (!row || !row.day || !row.shift) return null;
        const rows = this.mapScheduleScopeRows(row.day, row.shift).map((r) => {
            if (r.id !== assignmentId) return r;
            return {
                id: r.id,
                start: patch.start || '',
                end: patch.end || '',
                volunteer_ref: patch.volunteer_ref || ''
            };
        });
        return {
            day: row.day,
            shift: row.shift,
            rows: rows.filter((r) => r.volunteer_ref)
        };
    },

    _bindOpsRowFormEscape() {
        if (this._opsRowFormEscapeBound) return;
        this._opsRowFormEscapeHandler = (e) => {
            if (e.key === 'Escape' && this._opsRowFormModal && !this._opsRowFormSaving) {
                this.closeOpsRowFormModal();
            }
        };
        document.addEventListener('keydown', this._opsRowFormEscapeHandler);
        this._opsRowFormEscapeBound = true;
    },

    _unbindOpsRowFormEscape() {
        if (this._opsRowFormEscapeHandler) {
            document.removeEventListener('keydown', this._opsRowFormEscapeHandler);
            this._opsRowFormEscapeHandler = null;
        }
        this._opsRowFormEscapeBound = false;
    },

    openAddScheduleRowModal(day, shift) {
        if (!this.canEditScheduleScope(day, shift)) return;
        const times = this.getDefaultRowTimesForShift(shift);
        this._opsRowFormModal = {
            mode: 'add',
            day,
            shift,
            assignmentId: '',
            start: times.start,
            end: times.end,
            volunteer_ref: '',
            error: ''
        };
        this._opsRowFormSaving = false;
        this.renderOpsRowFormModal();
    },

    openEditScheduleRowModal(assignmentId) {
        const item = this.findOpsScheduleRow(assignmentId);
        if (!item || !this.canEditScheduleRow(item)) return;
        const volunteerRef = this.volunteerRefFromRow(item);
        this._opsRowFormModal = {
            mode: 'edit',
            day: item.day,
            shift: item.shift,
            assignmentId: item.id,
            start: item.start || '',
            end: item.end || '',
            volunteer_ref: volunteerRef,
            originalFingerprint: this.scheduleAssignmentFingerprint(volunteerRef, item.start, item.end),
            originalCommitment: this.getCommitmentStatus(item.id),
            error: ''
        };
        this._opsRowFormSaving = false;
        this.renderOpsRowFormModal();
    },

    closeOpsRowFormModal() {
        this._opsRowFormModal = null;
        this._opsRowFormSaving = false;
        const overlay = document.getElementById('ops-row-form-overlay');
        if (overlay) overlay.remove();
        document.body.classList.remove('ops-modal-open');
        this._unbindOpsRowFormEscape();
    },

    renderOpsRowFormModal() {
        const mod = this._opsRowFormModal;
        if (!mod) return;
        const isAdd = mod.mode === 'add';
        const titleKey = isAdd ? 'ops_row_form_add_title' : 'ops_row_form_edit_title';
        const day = this.escapeHtml(this.getOpsDayLabel(mod.day));
        const shift = this.escapeHtml(mod.shift || '—');
        const loc = this.escapeHtml(this.resolveLocationNameForShift(mod.shift));
        const bounds = this.escapeHtml(this.getShiftBoundsLabel(mod.shift));
        const contextParts = [day, shift, loc !== '-' ? loc : '', bounds].filter(Boolean).join(' · ');
        const saving = !!this._opsRowFormSaving;
        const volOptions = this.buildVolunteerRefOptions(mod.volunteer_ref || '');
        const reconfirmNote = (!isAdd && mod.originalCommitment === 'accepted')
            ? `<p class="ops-confirm-warning">${this.escapeHtml(i18n.t('ops_row_form_reconfirm_note'))}</p>`
            : '';
        const errorHtml = mod.error
            ? `<p class="ops-confirm-error" role="alert">${this.escapeHtml(mod.error)}</p>`
            : '';
        let overlay = document.getElementById('ops-row-form-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'ops-row-form-overlay';
            overlay.className = 'ops-confirm-overlay';
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay && !this._opsRowFormSaving) this.closeOpsRowFormModal();
            });
            document.body.appendChild(overlay);
        }
        overlay.innerHTML = `
            <div class="ops-confirm-panel ops-confirm-panel--form" role="dialog" aria-modal="true" aria-labelledby="ops-row-form-title">
                <header class="ops-confirm-header">
                    <h2 id="ops-row-form-title">${this.escapeHtml(i18n.t(titleKey))}</h2>
                    <button type="button" class="ops-editor-close-btn" onclick="app.closeOpsRowFormModal()" aria-label="${this.escapeHtml(i18n.t('ops_editor_close_aria'))}" ${saving ? 'disabled' : ''}>×</button>
                </header>
                <div class="ops-confirm-body">
                    <p class="ops-confirm-meta text-muted">${contextParts}</p>
                    <div class="ops-row-form-grid">
                        <label class="ops-editor-field">
                            <span class="ops-editor-field-label">${this.escapeHtml(i18n.t('ops_time_start'))}</span>
                            <input type="time" id="ops-row-form-start" value="${this.escapeHtml(mod.start || '')}" ${saving ? 'disabled' : ''}>
                        </label>
                        <label class="ops-editor-field">
                            <span class="ops-editor-field-label">${this.escapeHtml(i18n.t('ops_time_end'))}</span>
                            <input type="time" id="ops-row-form-end" value="${this.escapeHtml(mod.end || '')}" ${saving ? 'disabled' : ''}>
                        </label>
                        <label class="ops-editor-field ops-editor-field--full">
                            <span class="ops-editor-field-label">${this.escapeHtml(i18n.t('ops_volunteer_label'))}</span>
                            <select id="ops-row-form-volunteer" ${saving ? 'disabled' : ''}>${volOptions}</select>
                        </label>
                    </div>
                    ${reconfirmNote}
                    ${errorHtml}
                </div>
                <footer class="ops-confirm-footer">
                    <button type="button" class="btn-block outline" onclick="app.closeOpsRowFormModal()" ${saving ? 'disabled' : ''}>${this.escapeHtml(i18n.t('ops_remove_declined_cancel'))}</button>
                    <button type="button" class="btn-block" onclick="app.saveOpsRowFormModal()" ${saving ? 'disabled' : ''}>${this.escapeHtml(saving ? i18n.t('ops_row_form_saving') : i18n.t(isAdd ? 'ops_row_form_save_add' : 'ops_row_form_save_edit'))}</button>
                </footer>
            </div>
        `;
        document.body.classList.add('ops-modal-open');
        this._bindOpsRowFormEscape();
    },

    async saveOpsRowFormModal() {
        const mod = this._opsRowFormModal;
        if (!mod || this._opsRowFormSaving) return;
        const startEl = document.getElementById('ops-row-form-start');
        const endEl = document.getElementById('ops-row-form-end');
        const volEl = document.getElementById('ops-row-form-volunteer');
        const start = startEl ? startEl.value : mod.start;
        const end = endEl ? endEl.value : mod.end;
        const volunteer_ref = volEl ? volEl.value : mod.volunteer_ref;
        if (!volunteer_ref || !start || !end) {
            mod.error = i18n.t('ops_row_form_required');
            this.renderOpsRowFormModal();
            return;
        }
        let scope;
        if (mod.mode === 'add') {
            scope = this.buildScheduleScopeRowsForAdd(mod.day, mod.shift, { start, end, volunteer_ref });
        } else {
            scope = this.buildScheduleScopeRowsForEdit(mod.assignmentId, { start, end, volunteer_ref });
        }
        if (!scope) {
            mod.error = i18n.t('ops_row_form_fail');
            this.renderOpsRowFormModal();
            return;
        }
        this._opsRowFormSaving = true;
        mod.error = '';
        this.renderOpsRowFormModal();
        try {
            const result = await API.saveScheduleScope(scope.day, scope.shift, scope.rows);
            if (result.data) this.data.volunteerOps = result.data;
            this.closeOpsRowFormModal();
            this.renderVolunteerOps();
            if (this.router.currentView === 'home') this.renderHomeVolunteerDashboard();
            this.updateNotificationsBadge();
        } catch (err) {
            this._opsRowFormSaving = false;
            mod.error = err.message || i18n.t('ops_row_form_fail');
            this.renderOpsRowFormModal();
        }
    },

    _bindRemoveDeclinedEscape() {
        if (this._removeDeclinedEscapeBound) return;
        this._removeDeclinedEscapeHandler = (e) => {
            if (e.key === 'Escape' && this._removeDeclinedModal && !this._removeDeclinedSaving) {
                this.closeRemoveDeclinedModal();
            }
        };
        document.addEventListener('keydown', this._removeDeclinedEscapeHandler);
        this._removeDeclinedEscapeBound = true;
    },

    _unbindRemoveDeclinedEscape() {
        if (this._removeDeclinedEscapeHandler) {
            document.removeEventListener('keydown', this._removeDeclinedEscapeHandler);
            this._removeDeclinedEscapeHandler = null;
        }
        this._removeDeclinedEscapeBound = false;
    },

    openRemoveDeclinedModal(assignmentId) {
        const item = this.findOpsScheduleRow(assignmentId);
        if (!item || !this.canRemoveScheduleAssignment(item)) return;
        this._removeDeclinedModal = { assignmentId, item };
        this._removeDeclinedSaving = false;
        this.renderRemoveDeclinedModal();
    },

    closeRemoveDeclinedModal() {
        this._removeDeclinedModal = null;
        this._removeDeclinedSaving = false;
        const overlay = document.getElementById('ops-remove-declined-overlay');
        if (overlay) overlay.remove();
        document.body.classList.remove('ops-modal-open');
        this._unbindRemoveDeclinedEscape();
    },

    renderRemoveDeclinedModal() {
        const mod = this._removeDeclinedModal;
        if (!mod) return;
        const item = mod.item;
        const name = this.escapeHtml(item.volunteer_name || i18n.t('ops_volunteer_default'));
        const day = this.escapeHtml(this.getOpsDayLabel(item.day));
        const shift = this.escapeHtml(item.shift || '—');
        const loc = this.escapeHtml(item.location || '—');
        const timeRange = this.escapeHtml(this.formatAssignmentTimeRange(item) || '—');
        const pendingSwap = this.getPendingSwapForAssignment(mod.assignmentId);
        const swapNote = pendingSwap
            ? `<p class="ops-confirm-warning">${this.escapeHtml(i18n.t('ops_remove_declined_swap_note'))}</p>`
            : '';
        const commitmentNote = this.getRemoveScheduleWarningNote(mod.assignmentId);
        const commitmentHtml = commitmentNote
            ? `<p class="ops-confirm-warning">${this.escapeHtml(commitmentNote)}</p>`
            : '';
        const saving = !!this._removeDeclinedSaving;
        let overlay = document.getElementById('ops-remove-declined-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'ops-remove-declined-overlay';
            overlay.className = 'ops-confirm-overlay';
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay && !this._removeDeclinedSaving) this.closeRemoveDeclinedModal();
            });
            document.body.appendChild(overlay);
        }
        overlay.innerHTML = `
            <div class="ops-confirm-panel" role="dialog" aria-modal="true" aria-labelledby="ops-remove-declined-title">
                <header class="ops-confirm-header">
                    <h2 id="ops-remove-declined-title">${this.escapeHtml(i18n.t('ops_remove_declined_title'))}</h2>
                    <button type="button" class="ops-editor-close-btn" onclick="app.closeRemoveDeclinedModal()" aria-label="${this.escapeHtml(i18n.t('ops_editor_close_aria'))}" ${saving ? 'disabled' : ''}>×</button>
                </header>
                <div class="ops-confirm-body">
                    <p class="ops-confirm-volunteer"><strong>${name}</strong></p>
                    <p class="ops-confirm-meta text-muted">${day} · ${shift} · ${loc}</p>
                    <p class="ops-confirm-meta text-muted">${timeRange}</p>
                    <p class="ops-confirm-note">${this.escapeHtml(i18n.t('ops_remove_declined_body'))}</p>
                    ${commitmentHtml}
                    ${swapNote}
                </div>
                <footer class="ops-confirm-footer">
                    <button type="button" class="btn-block outline" onclick="app.closeRemoveDeclinedModal()" ${saving ? 'disabled' : ''}>${this.escapeHtml(i18n.t('ops_remove_declined_cancel'))}</button>
                    <button type="button" class="btn-block ops-confirm-danger-btn" onclick="app.confirmRemoveDeclinedAssignment()" ${saving ? 'disabled' : ''}>${this.escapeHtml(saving ? i18n.t('ops_remove_declined_removing') : i18n.t('ops_remove_declined_confirm'))}</button>
                </footer>
            </div>
        `;
        document.body.classList.add('ops-modal-open');
        this._bindRemoveDeclinedEscape();
    },

    async confirmRemoveDeclinedAssignment() {
        const mod = this._removeDeclinedModal;
        if (!mod || this._removeDeclinedSaving) return;
        const scope = this.buildScheduleScopeRowsExcluding(mod.assignmentId);
        if (!scope) {
            alert(i18n.t('ops_remove_declined_fail'));
            return;
        }
        this._removeDeclinedSaving = true;
        this.renderRemoveDeclinedModal();
        try {
            const result = await API.saveScheduleScope(scope.day, scope.shift, scope.rows);
            if (result.data) this.data.volunteerOps = result.data;
            this.closeRemoveDeclinedModal();
            this.renderVolunteerOps();
            if (this.router.currentView === 'home') this.renderHomeVolunteerDashboard();
            this.updateNotificationsBadge();
        } catch (err) {
            this._removeDeclinedSaving = false;
            this.renderRemoveDeclinedModal();
            alert(err.message || i18n.t('ops_remove_declined_fail'));
        }
    },

    getSwapRequesterDisplayName(swap, row) {
        if (row && row.volunteer_name) return String(row.volunteer_name).trim();
        const rid = swap && swap.requester_id;
        if (rid) {
            const match = (this.data.volunteerOps?.schedule || []).find(
                (r) => Number(r.wp_user_id) === Number(rid)
            );
            if (match && match.volunteer_name) return String(match.volunteer_name).trim();
        }
        return i18n.t('ops_swap_requester_unknown');
    },

    formatOpsAssignmentBrief(row) {
        if (!row) return i18n.t('ops_swap_assignment_unknown');
        const day = row.day ? this.getOpsDayLabel(row.day) : '—';
        const shift = row.shift || '—';
        const loc = row.location || '—';
        let time = '';
        if (row.start) {
            time = row.end ? ` (${row.start} – ${row.end})` : ` (${row.start})`;
        }
        return i18n.t('ops_swap_context_line')
            .replace('{0}', day)
            .replace('{1}', shift)
            .replace('{2}', loc)
            .replace('{3}', time);
    },

    formatOpsSwapCreatedAt(createdAt) {
        if (!createdAt) return '';
        const s = String(createdAt).trim();
        const m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
        const formatted = m
            ? `${m[3]}/${m[2]}/${m[1]}${m[4] != null ? ` ${m[4]}:${m[5] || '00'}` : ''}`
            : s;
        return i18n.t('ops_swap_requested_at').replace('{0}', formatted);
    },

    formatSwapAvisoSummary(swap) {
        const row = this.findOpsScheduleRow(swap.assignment_id);
        const name = this.getSwapRequesterDisplayName(swap, row);
        if (!row) {
            return i18n.t('avisos_swap_summary_unknown').replace('{0}', name);
        }
        return i18n.t('avisos_swap_summary')
            .replace('{0}', name)
            .replace('{1}', row.day ? this.getOpsDayLabel(row.day) : '—')
            .replace('{2}', row.shift || '—')
            .replace('{3}', row.location || '—');
    },

    renderOpsSwapRequestCard(swap) {
        const row = this.findOpsScheduleRow(swap.assignment_id);
        const name = this.getSwapRequesterDisplayName(swap, row);
        const context = this.formatOpsAssignmentBrief(row);
        const when = this.formatOpsSwapCreatedAt(swap.created_at);
        const reason = (swap.reason || '').trim();
        const idEsc = String(swap.id).replace(/'/g, "\\'");
        const reasonHtml = reason
            ? `<p class="ops-swap-card__reason">${this.escapeHtml(i18n.t('ops_swap_card_reason').replace('{0}', reason))}</p>`
            : '';
        return `<div class="ops-schedule-card ops-swap-card">
            <p class="ops-swap-card__title"><strong>${this.escapeHtml(name)}</strong> — ${this.escapeHtml(i18n.t('ops_swap_card_requested'))}</p>
            <p class="ops-swap-card__context">${this.escapeHtml(context)}</p>
            ${when ? `<p class="ops-swap-card__meta text-muted">${this.escapeHtml(when)}</p>` : ''}
            ${reasonHtml}
            <div class="ops-swap-actions">
                <button type="button" class="ops-btn ops-btn--active" onclick="app.openSwapApproveModal('${idEsc}')">${this.escapeHtml(i18n.t('ops_swap_approve'))}</button>
                <button type="button" class="ops-btn" onclick="app.openSwapRejectModal('${idEsc}')">${this.escapeHtml(i18n.t('ops_swap_reject'))}</button>
            </div>
        </div>`;
    },

    formatOpsHistoryLine(h) {
        if (!h || typeof h !== 'object') return '';
        const at = String(h.at || '').trim();
        const type = String(h.type || '').trim();
        let detail = '';

        if (type === 'schedule_patch') {
            const day = h.day ? this.getOpsDayLabel(h.day) : '—';
            const shift = h.shift || '—';
            const count = h.row_count != null ? String(h.row_count) : '—';
            detail = i18n.t('ops_history_schedule_patch')
                .replace('{0}', day)
                .replace('{1}', shift)
                .replace('{2}', count);
            const removed = h.reconcile && Number(h.reconcile.removed_count) > 0
                ? Number(h.reconcile.removed_count)
                : 0;
            if (removed > 0) {
                detail += ' ' + i18n.t('ops_history_removed_count').replace('{0}', String(removed));
            }
        } else if (type === 'reallocation') {
            const id = h.assignment_id || '—';
            let extra = '';
            if (h.new_location) {
                extra += i18n.t('ops_history_to_location').replace('{0}', String(h.new_location));
            }
            if (h.new_shift) {
                extra += i18n.t('ops_history_to_shift').replace('{0}', String(h.new_shift));
            }
            detail = i18n.t('ops_history_reallocation').replace('{0}', id).replace('{1}', extra);
        } else if (type === 'substitution') {
            const row = this.findOpsScheduleRow(h.assignment_id);
            const context = this.formatOpsAssignmentBrief(row);
            const note = h.note ? i18n.t('ops_history_substitution_note').replace('{0}', String(h.note)) : '';
            detail = i18n.t('ops_history_substitution').replace('{0}', context).replace('{1}', note);
        } else if (type === 'swap_rejected') {
            const row = this.findOpsScheduleRow(h.assignment_id);
            const context = this.formatOpsAssignmentBrief(row);
            const note = h.note ? i18n.t('ops_history_swap_rejected_note').replace('{0}', String(h.note)) : '';
            detail = i18n.t('ops_history_swap_rejected').replace('{0}', context).replace('{1}', note);
        } else {
            detail = i18n.t('ops_history_generic').replace('{0}', type || '—');
            if (h.assignment_id) {
                detail += ` — ${i18n.t('ops_history_assignment')} ${h.assignment_id}`;
            }
        }

        return at ? `${at} — ${detail}` : detail;
    },

    renderOpsHistoryBlock(history) {
        if (!this.canManageOps() || !Array.isArray(history) || !history.length) {
            return '';
        }
        const items = history.slice(0, 15);
        const summary = i18n.t('ops_history_title_count').replace('{0}', String(items.length));
        const list = items.map((h) => `<li>${this.escapeHtml(this.formatOpsHistoryLine(h))}</li>`).join('');
        return `<details class="ops-history-details"><summary>${this.escapeHtml(summary)}</summary><ul class="ops-history-list">${list}</ul></details>`;
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

    isAssignmentEnded(item) {
        if (!item || !item.id) return false;
        const presenceSt = this.getCheckinStatus(item.id).status || 'pending';
        if (presenceSt === 'checked_out') return true;
        const startMs = this.getAssignmentStartMs(item);
        if (startMs == null) return false;
        const endMs = this.getAssignmentEndMs(item, startMs);
        return endMs != null && Date.now() > endMs;
    },

    isMyAssignmentArchived(item) {
        if (!item || !item.id) return false;
        if (this.getCommitmentStatus(item.id) === 'declined') return true;
        return this.isAssignmentEnded(item);
    },

    isMyAssignmentActionable(item) {
        if (!item || !item.id) return false;
        const commitSt = this.getCommitmentStatus(item.id);
        if (commitSt === 'pending' && this.canCommitAssignment(item)) return true;
        if (commitSt === 'accepted') {
            if (this.canCheckinAssignment(item)) return true;
            if (this.canCheckoutAssignment(item)) return true;
        }
        return false;
    },

    getMyActionableAssignments() {
        return this.getMyScheduleRows().filter((i) => this.isMyAssignmentActionable(i));
    },

    setOpsMineCommitmentFilter(value) {
        this._opsMineCommitmentFilter = value || '';
        this.renderVolunteerOps();
    },

    renderOpsMineCommitmentFilter() {
        const cur = this._opsMineCommitmentFilter || '';
        const opts = [
            ['', 'ops_filter_commitment_all'],
            ['pending', 'ops_filter_commitment_pending'],
            ['accepted', 'ops_filter_commitment_accepted'],
            ['declined', 'ops_filter_commitment_declined']
        ];
        const options = opts.map(([val, key]) =>
            `<option value="${val}"${cur === val ? ' selected' : ''}>${this.escapeHtml(i18n.t(key))}</option>`
        ).join('');
        return `<select id="ops-mine-commitment-filter" class="ops-mine-commitment-filter ops-filter-control" onchange="app.setOpsMineCommitmentFilter(this.value)" aria-label="${this.escapeHtml(i18n.t('ops_filter_commitment_label'))}">${options}</select>`;
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
            if (this.canRemoveScheduleAssignment(item)) {
                inlineActions += this.renderOpsIconButton('ops_remove_declined_btn', `app.openRemoveDeclinedModal('${idEsc}')`, 'remove', 'danger');
            }
            if (this.canEditScheduleRow(item)) {
                inlineActions += this.renderOpsIconButton('ops_edit_schedule_row_btn', `app.openEditScheduleRowModal('${idEsc}')`, 'edit', 'outline');
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
                <div class="ops-volunteer-status">${this.getCommitmentBadge(item.id)} ${this.getOpsStatusBadge(item.id, item)}</div>
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
        const canEditScope = showActions && this.canEditScheduleScope(day, shift);
        const addBtn = canEditScope
            ? `<button type="button" class="ops-btn ops-btn--accent ops-shift-add-btn" onclick="app.openAddScheduleRowModal('${dayEsc}','${shiftEsc}')">${this.escapeHtml(i18n.t('ops_add_schedule_row_btn'))}</button>`
            : '';
        const editBtn = canEditScope
            ? `<button type="button" class="ops-btn ops-shift-edit-btn" onclick="app.openScheduleEditor('${dayEsc}','${shiftEsc}')">${this.escapeHtml(i18n.t('ops_edit_this_shift'))}</button>`
            : '';
        const shiftActions = (addBtn || editBtn)
            ? `<div class="ops-shift-card-actions">${addBtn}${editBtn}</div>`
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
                    ${shiftActions}
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
        const allRows = this.getMyScheduleRows();
        const filterHtml = this.renderOpsMineCommitmentFilter();
        const headHtml = `<div class="ops-mine-block-head"><h3>${this.escapeHtml(i18n.t('ops_my_assignments'))}</h3>${filterHtml}</div>`;

        if (!allRows.length) {
            return `<section class="ops-mine-block ops-mine-block--shift">${headHtml}<p class="text-muted">${this.escapeHtml(i18n.t('ops_no_assignments'))}</p></section>`;
        }

        const filter = this._opsMineCommitmentFilter || '';
        if (filter) {
            const filtered = allRows.filter((i) => this.getCommitmentStatus(i.id) === filter);
            if (!filtered.length) {
                return `<section class="ops-mine-block ops-mine-block--shift">${headHtml}<p class="text-muted">${this.escapeHtml(i18n.t('ops_no_schedule_filtered'))}</p></section>`;
            }
            const scheduleHtml = this.renderOpsShiftSchedule(filtered, uid, true, {
                compact: true,
                wrapClass: 'ops-schedule-shift-view ops-schedule-shift-view--mine'
            });
            return `<section class="ops-mine-block ops-mine-block--shift">${headHtml}${scheduleHtml}</section>`;
        }

        const active = allRows.filter((i) => !this.isMyAssignmentArchived(i));
        const archived = allRows.filter((i) => this.isMyAssignmentArchived(i));
        const activeHtml = active.length
            ? this.renderOpsShiftSchedule(active, uid, true, {
                compact: true,
                wrapClass: 'ops-schedule-shift-view ops-schedule-shift-view--mine'
            })
            : `<p class="text-muted">${this.escapeHtml(i18n.t('ops_mine_no_active'))}</p>`;

        let archivedHtml = '';
        if (archived.length) {
            const archivedSchedule = this.renderOpsShiftSchedule(archived, uid, true, {
                compact: true,
                wrapClass: 'ops-schedule-shift-view ops-schedule-shift-view--mine ops-schedule-shift-view--archived'
            });
            const summary = i18n.t('ops_mine_archived_summary').replace('{0}', String(archived.length));
            archivedHtml = `<details class="ops-mine-archived-details"><summary>${this.escapeHtml(summary)}</summary>${archivedSchedule}</details>`;
        }

        return `<section class="ops-mine-block ops-mine-block--shift">${headHtml}${activeHtml}${archivedHtml}</section>`;
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
        const removeBtn = this.canRemoveScheduleAssignment(item)
            ? `<button type="button" class="ops-btn ops-btn--danger" onclick="app.openRemoveDeclinedModal('${String(item.id).replace(/'/g, "\\'")}')">${this.escapeHtml(i18n.t('ops_remove_declined_btn'))}</button>`
            : '';
        const editRowBtn = this.canEditScheduleRow(item)
            ? `<button type="button" class="ops-btn" onclick="app.openEditScheduleRowModal('${String(item.id).replace(/'/g, "\\'")}')">${this.escapeHtml(i18n.t('ops_edit_schedule_row_btn'))}</button>`
            : '';
        const actionCell = (actions || swapBtn || reallocBtn || removeBtn || editRowBtn)
            ? `<div class="ops-table-actions">${actions}${reallocBtn}${swapBtn}${editRowBtn}${removeBtn}</div>`
            : '';
        const timeRange = `${item.start || '-'} ${i18n.t('ops_time_to')} ${item.end || '-'}`;
        return `
            <tr class="ops-schedule-row${mineRow ? ' ops-schedule-row--mine' : ''}">
                <td data-label="${this.escapeHtml(i18n.t('ops_shift_label'))}">${this.escapeHtml(item.shift || '-')}</td>
                <td data-label="${this.escapeHtml(i18n.t('ops_time_label'))}">${this.escapeHtml(timeRange)}</td>
                <td data-label="${this.escapeHtml(i18n.t('ops_location_label'))}">${this.escapeHtml(item.location || '-')}</td>
                <td data-label="${this.escapeHtml(i18n.t('ops_volunteer_label'))}">${mineRow ? `<span class="ops-you-badge">${this.escapeHtml(i18n.t('ops_you_badge'))}</span> ` : ''}${this.renderOpsWhatsAppNameLink(item.volunteer_name || i18n.t('ops_volunteer_default'), item.volunteer_phone)}</td>
                <td data-label="${this.escapeHtml(i18n.t('ops_languages_label'))}">${this.escapeHtml((item.languages || []).join(', ') || '-')}</td>
                <td data-label="${this.escapeHtml(i18n.t('ops_status_label'))}">${this.getCommitmentBadge(item.id)} ${this.getOpsStatusBadge(item.id, item)}</td>
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

    syncOpsFiltersFromDom() {
        const dayEl = document.getElementById('ops-day-filter');
        if (!dayEl) return;
        this._opsLastFilters = {
            day: dayEl.value || '',
            shift: document.getElementById('ops-shift-filter')?.value || '',
            location: document.getElementById('ops-location-filter')?.value || '',
            name: document.getElementById('ops-name-filter')?.value || '',
            language: document.getElementById('ops-language-filter')?.value || '',
            responsible: document.getElementById('ops-responsible-filter')?.value || ''
        };
    },

    getOpsFilterValues() {
        const preset = this._opsFilterPreset || {};
        const lf = this._opsLastFilters || {};
        const dayEl = document.getElementById('ops-day-filter');
        const nameRaw = document.getElementById('ops-name-filter')?.value ?? lf.name ?? '';
        const languageRaw = document.getElementById('ops-language-filter')?.value ?? lf.language ?? '';
        return {
            selectedDay: dayEl?.value || preset.day || lf.day || '',
            selectedShift: document.getElementById('ops-shift-filter')?.value || preset.shift || lf.shift || '',
            selectedLocation: (document.getElementById('ops-location-filter')?.value || lf.location || '').trim(),
            selectedResponsible: (document.getElementById('ops-responsible-filter')?.value || lf.responsible || '').trim(),
            nameFilterValue: String(nameRaw),
            nameQuery: String(nameRaw).trim().toLowerCase(),
            languageFilterValue: String(languageRaw),
            languageQuery: String(languageRaw).trim().toLowerCase()
        };
    },

    filterOpsScheduleItems(schedule, f) {
        let items = (schedule || []).slice();
        if (f.selectedDay) items = items.filter((i) => i.day === f.selectedDay);
        if (f.selectedShift) items = items.filter((i) => i.shift === f.selectedShift);
        if (f.selectedLocation) items = items.filter((i) => (i.location || '') === f.selectedLocation);
        if (f.nameQuery) {
            items = items.filter((i) => String(i.volunteer_name || '').toLowerCase().includes(f.nameQuery));
        }
        if (f.languageQuery) {
            items = items.filter((i) => (i.languages || []).some((lang) => String(lang).toLowerCase().includes(f.languageQuery)));
        }
        if (f.selectedResponsible) {
            items = items.filter((i) => this.itemMatchesOpsResponsibleFilter(i, f.selectedResponsible));
        }
        return items;
    },

    handleOpsTextFilterInput(event) {
        const el = event && event.target;
        if (!el || (el.id !== 'ops-name-filter' && el.id !== 'ops-language-filter')) return;
        if (!this._opsLastFilters) this._opsLastFilters = {};
        if (el.id === 'ops-name-filter') {
            this._opsLastFilters.name = el.value;
        } else {
            this._opsLastFilters.language = el.value;
        }
        this.refreshOpsScheduleFromFilters();
    },

    refreshOpsScheduleFromFilters() {
        const scheduleEl = document.getElementById('ops-schedule-by-day');
        const ops = this.data.volunteerOps;
        if (!scheduleEl || !ops || !Array.isArray(ops.schedule)) {
            this.renderVolunteerOps();
            return;
        }
        const f = this.getOpsFilterValues();
        const items = this.filterOpsScheduleItems(ops.schedule, f);
        const uid = this.auth.user?.id;
        const viewMode = this.getOpsScheduleViewMode();
        scheduleEl.innerHTML = viewMode === 'list'
            ? this.renderOpsDayGroups(items, uid, true)
            : this.renderOpsShiftSchedule(items, uid, true);
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
        this.syncOpsFiltersFromDom();
        const f = this.getOpsFilterValues();
        if (preset.day || preset.shift) this._opsFilterPreset = null;

        const {
            selectedDay,
            selectedShift,
            selectedLocation,
            selectedResponsible,
            nameFilterValue,
            languageFilterValue
        } = f;

        const uid = this.auth.user.id;
        const myHtml = this.renderOpsMyAssignmentsBlock(uid);

        let swapPanel = '';
        const swaps = ops.swap_requests || [];
        if (this.canManageOps() || this.canReallocateOps()) {
            const pend = swaps.filter((s) => s.status === 'pending');
            swapPanel = `<div class="ops-swap-panel"><h3>${this.escapeHtml(i18n.t('ops_swap_requests_title'))}</h3>${pend.length ? pend.map((s) => this.renderOpsSwapRequestCard(s)).join('') : `<p class="text-muted">${this.escapeHtml(i18n.t('ops_swap_none'))}</p>`}</div>`;
        }

        const histBlock = this.renderOpsHistoryBlock(ops.history);

        const items = this.filterOpsScheduleItems(ops.schedule, f);

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
            ? `<button type="button" class="ops-btn ops-btn--accent ops-toolbar-action-btn" onclick="app.openScheduleEditor()">${this.escapeHtml(i18n.t('ops_schedule_edit_btn'))}</button>`
            : '';
        const exportBtn = this.canManageOps()
            ? `<button type="button" id="ops-export-pdf-btn" class="ops-btn ops-btn--accent ops-toolbar-action-btn" onclick="app.downloadOpsExportPdf()">${this.escapeHtml(i18n.t('ops_export_pdf'))}</button>`
            : '';

        container.innerHTML = `
            ${staleBanner}
            ${myHtml}
            ${swapPanel}
            ${histBlock}
            <div class="ops-toolbar">
                <div class="ops-filters">
                    <span class="ops-view-toggle ops-filters-span-full" role="group" aria-label="${this.escapeHtml(i18n.t('ops_view_mode_label'))}">
                        <button type="button" class="avisos-filter-chip${viewMode === 'shift' ? ' active' : ''}" onclick="app.setOpsScheduleViewMode('shift')">${this.escapeHtml(i18n.t('ops_view_by_shift'))}</button>
                        <button type="button" class="avisos-filter-chip${viewMode === 'list' ? ' active' : ''}" onclick="app.setOpsScheduleViewMode('list')">${this.escapeHtml(i18n.t('ops_view_list'))}</button>
                    </span>
                    <button type="button" class="avisos-filter-chip ops-filters-span-full" onclick="app.applyOpsFilterMyShift()">${this.escapeHtml(i18n.t('ops_filter_my_shift'))}</button>
                    <select id="ops-day-filter" class="ops-filter-control" onchange="app.renderVolunteerOps()">
                        <option value="">${this.escapeHtml(i18n.t('ops_filter_all_days'))}</option>
                        <option value="sexta" ${selectedDay === 'sexta' ? 'selected' : ''}>${this.escapeHtml(this.getOpsDayLabel('sexta'))}</option>
                        <option value="sabado" ${selectedDay === 'sabado' ? 'selected' : ''}>${this.escapeHtml(this.getOpsDayLabel('sabado'))}</option>
                        <option value="domingo" ${selectedDay === 'domingo' ? 'selected' : ''}>${this.escapeHtml(this.getOpsDayLabel('domingo'))}</option>
                    </select>
                    <select id="ops-shift-filter" class="ops-filter-control" onchange="app.renderVolunteerOps()">
                        <option value="">${this.escapeHtml(i18n.t('ops_filter_all_shifts'))}</option>
                        <option value="A1" ${selectedShift === 'A1' ? 'selected' : ''}>A1</option>
                        <option value="B1" ${selectedShift === 'B1' ? 'selected' : ''}>B1</option>
                        <option value="A2" ${selectedShift === 'A2' ? 'selected' : ''}>A2</option>
                        <option value="B2" ${selectedShift === 'B2' ? 'selected' : ''}>B2</option>
                    </select>
                    <select id="ops-location-filter" class="ops-filter-control" onchange="app.renderVolunteerOps()">
                        <option value="">${this.escapeHtml(i18n.t('ops_filter_all_locations'))}</option>
                        ${locOptions}
                    </select>
                    ${responsibleNames.length ? `<select id="ops-responsible-filter" class="ops-filter-control" onchange="app.renderVolunteerOps()" aria-label="${this.escapeHtml(i18n.t('ops_filter_responsible_label'))}">
                        <option value="">${this.escapeHtml(i18n.t('ops_filter_all_responsibles'))}</option>
                        ${responsibleOptions}
                    </select>` : ''}
                    <input id="ops-name-filter" class="ops-filter-control ops-filters-span-full" value="${this.escapeHtml(nameFilterValue)}" oninput="app.handleOpsTextFilterInput(event)" placeholder="${this.escapeHtml(i18n.t('ops_filter_name_placeholder'))}">
                    <input id="ops-language-filter" class="ops-filter-control ops-filters-span-full" value="${this.escapeHtml(languageFilterValue)}" oninput="app.handleOpsTextFilterInput(event)" placeholder="${this.escapeHtml(i18n.t('ops_filter_language_placeholder'))}">
                </div>
                <div class="ops-toolbar-actions">${editBtn}${exportBtn}</div>
            </div>
            ${governanceHtml ? `<details class="ops-governance-details"><summary>${this.escapeHtml(i18n.t('ops_governance_title'))}</summary><div class="ops-governance-grid">${governanceHtml}</div></details>` : ''}
            <h3 class="ops-schedule-heading">${this.escapeHtml(i18n.t('ops_team_schedule'))}</h3>
            <div id="ops-schedule-by-day" class="ops-schedule-by-day">${scheduleHtml}</div>
        `;
    },

    renderEmergency() {
        const container = document.getElementById('emergency-container');
        if (!container) return;

        const evento = this.data.evento || {};
        const info = evento.info_uteis || {};
        const lang = this.getEmergencyLangKey();
        let services = Array.isArray(evento.emergency_services) ? evento.emergency_services : [];
        if (!services.length && Array.isArray(evento.telefones_emergencia) && evento.telefones_emergencia.length) {
            services = evento.telefones_emergencia.map((p) => ({
                number: p.numero,
                label: { pt: p.nome || '', en: p.nome || '', es: p.nome || '' },
                when: { pt: '', en: '', es: '' }
            }));
        }
        const showInternal = !!info.emergency_phone_active;
        const internalPhone = (info.emergency_phone || '').trim();
        const medicalLoc = (info.medical_loc || '').trim();

        let html = `
            <div class="emergency-hero">
                <h3 class="emergency-hero-title">${this.escapeHtml(i18n.t('emergency_help_title'))}</h3>
                <p class="emergency-hero-desc">${this.escapeHtml(i18n.t('emergency_help_desc'))}</p>
            </div>`;

        if (services.length) {
            html += '<div class="emergency-services-list">';
            html += services.map((svc) => {
                const label = this.escapeHtml(this.pickLocalizedField(svc.label, lang) || svc.number || '');
                const when = this.escapeHtml(this.pickLocalizedField(svc.when, lang));
                const displayNumber = this.escapeHtml(svc.number || '');
                const tel = this.formatTelHref(svc.number);
                const callLabel = this.escapeHtml(i18n.t('emergency_call_now'));
                if (!tel) return '';
                return `
                <div class="emergency-service-row">
                    <div class="emergency-service-body">
                        <span class="emergency-service-title">${label} — ${displayNumber}</span>
                        ${when ? `<p class="emergency-service-when">${when}</p>` : ''}
                    </div>
                    <a href="tel:${tel}" class="emergency-service-call">${callLabel}</a>
                </div>`;
            }).join('');
            html += '</div>';
        }

        if (showInternal && internalPhone) {
            const tel = this.formatTelHref(internalPhone);
            html += `
            <div class="emergency-internal-block">
                <span class="emergency-internal-label">${this.escapeHtml(i18n.t('emergency_internal_title'))}</span>
                <a href="tel:${tel}" class="emergency-hero-call">${this.escapeHtml(internalPhone)}</a>
            </div>`;
        }

        if (medicalLoc) {
            html += `
            <div class="emergency-medical-loc">
                <strong>${this.escapeHtml(i18n.t('emergency_medical_loc_title'))}</strong>
                <span>${this.escapeHtml(medicalLoc)}</span>
            </div>`;
        }

        if (!services.length && !(showInternal && internalPhone)) {
            html += `<div class="emergency-empty">${this.escapeHtml(i18n.t('emergency_empty_contacts'))}</div>`;
        }

        container.innerHTML = html;
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

    eventInfoFlagActive(info, key) {
        if (!info || typeof info !== 'object') return false;
        return info[key] === true;
    },

    renderEventTransportCard(info) {
        if (!this.eventInfoFlagActive(info, 'trans_section_active')) return '';
        const items = [];
        if (info.trans_shuttle && info.trans_shuttle.active) {
            items.push(`<div class="transport-item"><div class="icon">🚌</div><h4>${this.escapeHtml(info.trans_shuttle.title || '')}</h4><p>${this.escapeHtml(info.trans_shuttle.desc || '')}</p></div>`);
        }
        if (info.trans_public && info.trans_public.active) {
            items.push(`<div class="transport-item"><div class="icon">🚇</div><h4>${this.escapeHtml(info.trans_public.title || '')}</h4><p>${this.escapeHtml(info.trans_public.desc || '')}</p></div>`);
        }
        if (info.trans_taxi && info.trans_taxi.active) {
            items.push(`<div class="transport-item"><div class="icon">🚕</div><h4>${this.escapeHtml(info.trans_taxi.title || '')}</h4><p>${this.escapeHtml(info.trans_taxi.desc || '')}</p></div>`);
        }
        if (!items.length) return '';
        return `<div class="info-card"><div class="card-title">🚍 ${this.escapeHtml(i18n.t('event_transport_title'))}</div><div class="transport-grid">${items.join('')}</div></div>`;
    },

    renderEventWifiCard(info) {
        if (!this.eventInfoFlagActive(info, 'wifi_section_active')) return '';
        const ssid = this.escapeHtml(info.wifi_ssid || i18n.t('event_wifi_not_disclosed'));
        const pass = this.escapeHtml(info.wifi_pass || '—');
        return `
            <div class="info-card highlight-blue">
                <h3 style="color:white; margin-bottom:1rem;">📶 ${this.escapeHtml(i18n.t('event_useful_info_title'))}</h3>
                <div style="margin-bottom: 1rem;">
                    <div style="font-size:0.8rem; opacity:0.8;">${this.escapeHtml(i18n.t('event_wifi_ssid_label'))}</div>
                    <div style="font-weight:bold; font-size:1.1rem;">${ssid}</div>
                </div>
                <div>
                    <div style="font-size:0.8rem; opacity:0.8;">${this.escapeHtml(i18n.t('event_wifi_pass_label'))}</div>
                    <div style="font-weight:bold; font-size:1.1rem;">${pass}</div>
                </div>
            </div>`;
    },

    renderEventCredCard(info) {
        if (!this.eventInfoFlagActive(info, 'cred_section_active')) return '';
        const hours = this.escapeHtml(info.cred_hours || i18n.t('event_cred_hours_fallback'));
        const docs = this.escapeHtml(info.cred_docs || i18n.t('event_cred_docs_fallback'));
        return `
            <div class="info-card">
                <div class="card-title">🆔 ${this.escapeHtml(i18n.t('event_cred_title'))}</div>
                <div class="timeline-item">
                    <div class="time">${this.escapeHtml(i18n.t('event_cred_hours_label'))}</div>
                    <div class="desc">${hours}</div>
                </div>
                <div class="timeline-item">
                    <div class="time">${this.escapeHtml(i18n.t('event_cred_docs_label'))}</div>
                    <div class="desc">${docs}</div>
                </div>
            </div>`;
    },

    renderEventPressContactCard(info) {
        const pc = info && info.press_contact;
        if (!pc || !pc.active || !pc.phone) return '';
        const label = this.escapeHtml(pc.label || i18n.t('event_press_contact_default_label'));
        const name = pc.name ? `<p class="event-press-contact-name">${this.escapeHtml(pc.name)}</p>` : '';
        const note = pc.note ? `<p class="event-press-contact-note text-muted">${this.escapeHtml(pc.note)}</p>` : '';
        const tel = this.formatTelHref(pc.phone);
        const wa = this.buildWhatsAppUrl(pc.phone);
        const callBtn = tel
            ? `<a href="tel:${this.escapeHtml(tel)}" class="btn-block outline event-contact-btn">${this.escapeHtml(i18n.t('event_call_btn'))}</a>`
            : '';
        const waBtn = wa
            ? `<a href="${this.escapeHtml(wa)}" class="btn-block event-contact-btn event-contact-btn--whatsapp" target="_blank" rel="noopener noreferrer">${this.escapeHtml(i18n.t('event_whatsapp_btn'))}</a>`
            : '';
        const actions = (callBtn || waBtn)
            ? `<div class="event-contact-actions">${callBtn}${waBtn}</div>`
            : '';
        const instructionsLink = (this.auth.user && this.getPressInstructionsPostId())
            ? `<button type="button" class="event-press-instructions-link" onclick="app.openPressInstructionsPost(); return false;">${this.escapeHtml(i18n.t('event_press_instructions_link'))}</button>`
            : '';
        return `
            <div class="info-card event-press-contact-card">
                <div class="card-title">📰 ${label}</div>
                ${name}
                ${note}
                ${actions}
                ${instructionsLink}
            </div>`;
    },

    async renderEventInfo() {
        const container = document.getElementById('event-container');
        const evt = this.data.evento;

        if (!evt) return;

        const heroImage = evt.foto || 'images/convention-center.jpg';
        const info = evt.info_uteis || {};
        const enderecoEsc = this.escapeHtml(evt.endereco || '');
        const enderecoJs = String(evt.endereco || '').replace(/'/g, "\\'");
        const pc = info.press_contact;
        if (this.auth.user && pc && pc.active && pc.phone && !this.getPressInstructionsPostId()) {
            await this.loadNews(1, false);
            this.updateHomePressInstructionsBtn();
        }
        const transportHtml = this.renderEventTransportCard(info);
        const wifiHtml = this.renderEventWifiCard(info);
        const credHtml = this.renderEventCredCard(info);
        const pressHtml = this.renderEventPressContactCard(info);
        const supportEmail = (evt.contatos && evt.contatos.email) ? evt.contatos.email : '';
        const supportChat = info.support_chat || '#';

        container.innerHTML = `
            <div class="event-hero" style="background-image: url('${heroImage}');">
                <div class="hero-overlay">
                    <h1>${this.escapeHtml(evt.name_evento || '')}</h1>
                </div>
            </div>

            <div class="event-grid">
                <div class="event-main">
                    <div class="info-card">
                        <div class="card-title text-primary">${this.escapeHtml(i18n.t('event_location_title'))}</div>
                        <h3>${this.escapeHtml(evt.local || i18n.t('event_location_default'))}</h3>
                        <p>${enderecoEsc}</p>
                        <div class="event-location-actions">
                            <button type="button" class="action-btn outline small" onclick="navigator.clipboard.writeText('${enderecoJs}')">
                                📋 ${this.escapeHtml(i18n.t('event_copy_address'))}
                            </button>
                            <button type="button" class="action-btn primary small" onclick="window.open('https://maps.google.com/?q=${encodeURIComponent(evt.endereco || '')}', '_blank')">
                                🗺️ ${this.escapeHtml(i18n.t('event_open_map'))}
                            </button>
                        </div>
                    </div>
                    ${transportHtml}
                    <div class="map-preview" id="event-map-preview"></div>
                </div>

                <div class="event-sidebar">
                    ${wifiHtml}
                    ${credHtml}
                    ${pressHtml}
                    <div class="info-card highlight-red">
                        <div class="card-title" style="color: #d63384;">⛑️ ${this.escapeHtml(i18n.t('event_safety_title'))}</div>
                        <div class="info-safety-medical">
                            <span class="info-safety-medical__label">${this.escapeHtml(i18n.t('event_medical_post'))}</span>
                            <span class="info-safety-medical__text">${this.escapeHtml(info.medical_loc || i18n.t('event_medical_fallback'))}</span>
                        </div>
                        <div class="info-safety-row">
                            <span>${this.escapeHtml(i18n.t('event_emergency_label'))}</span>
                            <strong style="color:var(--danger-color);">${this.escapeHtml(info.emergency_phone || '192')}</strong>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="card-title">${this.escapeHtml(i18n.t('event_support_title'))}</div>
                        <p class="text-muted" style="font-size:0.9rem; margin-bottom:1rem;">${this.escapeHtml(i18n.t('event_support_desc'))}</p>
                        <button type="button" class="btn-block outline" onclick="window.open('${this.escapeHtml(supportChat)}', '_blank')">
                            ${this.escapeHtml(i18n.t('event_support_chat'))}
                        </button>
                        <button type="button" class="btn-block event-support-email-btn" onclick="window.location.href='mailto:${this.escapeHtml(supportEmail)}'">
                            📧 ${this.escapeHtml(i18n.t('event_support_email'))}
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
    },

    formatDelegateSupportDateTime(value) {
        if (!value) return '—';
        const normalized = String(value).replace(' ', 'T');
        const d = new Date(normalized);
        if (Number.isNaN(d.getTime())) return value;
        try {
            return d.toLocaleString(i18n.current === 'en' ? 'en-US' : (i18n.current === 'es' ? 'es-ES' : 'pt-BR'), {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return value;
        }
    },

    defaultDelegateSupportDateTimeLocal() {
        const d = new Date();
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0, 16);
    },

    mysqlToDelegateDatetimeLocal(value) {
        if (!value) return this.defaultDelegateSupportDateTimeLocal();
        const normalized = String(value).replace(' ', 'T');
        const d = new Date(normalized);
        if (Number.isNaN(d.getTime())) return this.defaultDelegateSupportDateTimeLocal();
        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
        return d.toISOString().slice(0, 16);
    },

    findDelegateSupportItem(id) {
        const items = this._delegateSupportItems || [];
        return items.find((item) => item.id === id) || null;
    },

    findNearestLocalName(lat, lng) {
        const locais = Array.isArray(this.data.locais) ? this.data.locais : [];
        let best = null;
        let bestDist = Infinity;
        locais.forEach((item) => {
            if (item.lat == null || item.lng == null) return;
            const dist = this.calculateDistance(lat, lng, item.lat, item.lng);
            if (dist < bestDist) {
                bestDist = dist;
                best = item;
            }
        });
        if (best && bestDist <= 0.5 && best.nome) {
            return best.endereco ? `${best.nome} — ${best.endereco}` : best.nome;
        }
        return null;
    },

    fillDelegateSupportLocationFromGps() {
        const input = document.getElementById('delegate-support-location');
        if (!input) return;
        const loc = this.data.userLocation;
        if (!loc || loc.lat == null || loc.lng == null) {
            if (!navigator.geolocation) {
                alert(i18n.t('delegate_support_location_unavailable'));
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.data.userLocation = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                    this.fillDelegateSupportLocationFromGps();
                },
                () => alert(i18n.t('delegate_support_location_unavailable')),
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
            );
            return;
        }
        const nearest = this.findNearestLocalName(loc.lat, loc.lng);
        input.value = nearest || i18n.t('delegate_support_location_gps', loc.lat.toFixed(5), loc.lng.toFixed(5));
    },

    renderDelegateSupportForm() {
        const container = document.getElementById('delegate-support-form-container');
        if (!container) return;
        if (!this.auth.user || !this.canViewOps()) {
            container.innerHTML = `<div class="loading">${this.escapeHtml(i18n.t('ops_no_permission'))}</div>`;
            return;
        }
        container.innerHTML = `
            <div class="delegate-support-banner" role="note">${this.escapeHtml(i18n.t('delegate_support_banner'))}</div>
            <form id="delegate-support-form" class="profile-form delegate-support-form" onsubmit="app.submitDelegateSupportForm(event)">
                <div class="form-group">
                    <label for="delegate-support-occurred-at">${this.escapeHtml(i18n.t('delegate_support_occurred_at'))}</label>
                    <input type="datetime-local" id="delegate-support-occurred-at" class="form-input" required value="${this.escapeHtml(this.defaultDelegateSupportDateTimeLocal())}">
                </div>
                <div class="form-group">
                    <label for="delegate-support-location">${this.escapeHtml(i18n.t('delegate_support_location'))}</label>
                    <div class="delegate-support-location-row">
                        <input type="text" id="delegate-support-location" class="form-input" required maxlength="200" autocomplete="off">
                        <button type="button" class="btn-block outline delegate-support-gps-btn" onclick="app.fillDelegateSupportLocationFromGps()">${this.escapeHtml(i18n.t('delegate_support_use_location'))}</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="delegate-support-delegate-name">${this.escapeHtml(i18n.t('delegate_support_delegate_name'))}</label>
                    <input type="text" id="delegate-support-delegate-name" class="form-input" required maxlength="120" autocomplete="name">
                </div>
                <div class="form-group">
                    <label for="delegate-support-contact-name">${this.escapeHtml(i18n.t('delegate_support_contact_name'))}</label>
                    <input type="text" id="delegate-support-contact-name" class="form-input" required maxlength="120" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="delegate-support-description">${this.escapeHtml(i18n.t('delegate_support_description'))}</label>
                    <textarea id="delegate-support-description" class="form-input" rows="4" required minlength="10" maxlength="2000"></textarea>
                </div>
                <div id="delegate-support-form-msg" class="profile-form-msg" style="display:none;" role="status"></div>
                <button type="submit" id="delegate-support-submit-btn" class="btn-block">${this.escapeHtml(i18n.t('delegate_support_submit'))}</button>
            </form>`;
    },

    async submitDelegateSupportForm(event) {
        event.preventDefault();
        if (!navigator.onLine) {
            alert(i18n.t('delegate_support_offline'));
            return;
        }
        const occurredAt = document.getElementById('delegate-support-occurred-at')?.value || '';
        const location = document.getElementById('delegate-support-location')?.value.trim() || '';
        const delegateName = document.getElementById('delegate-support-delegate-name')?.value.trim() || '';
        const contactName = document.getElementById('delegate-support-contact-name')?.value.trim() || '';
        const description = document.getElementById('delegate-support-description')?.value.trim() || '';
        const msgEl = document.getElementById('delegate-support-form-msg');
        const btn = document.getElementById('delegate-support-submit-btn');

        if (!occurredAt || !location || !delegateName || !contactName || description.length < 10) {
            if (msgEl) {
                msgEl.style.display = 'block';
                msgEl.className = 'profile-form-msg error';
                msgEl.textContent = i18n.t('delegate_support_required');
            }
            return;
        }
        if (msgEl) msgEl.style.display = 'none';
        if (btn) {
            btn.disabled = true;
            btn.textContent = i18n.t('delegate_support_saving');
        }
        try {
            await API.submitDelegateSupportReport({
                occurred_at: occurredAt,
                location,
                delegate_name: delegateName,
                contact_name: contactName,
                description
            });
            if (msgEl) {
                msgEl.style.display = 'block';
                msgEl.className = 'profile-form-msg success';
                msgEl.textContent = i18n.t('delegate_support_success');
            }
            const form = document.getElementById('delegate-support-form');
            if (form) form.reset();
            const when = document.getElementById('delegate-support-occurred-at');
            if (when) when.value = this.defaultDelegateSupportDateTimeLocal();
        } catch (e) {
            if (msgEl) {
                msgEl.style.display = 'block';
                msgEl.className = 'profile-form-msg error';
                msgEl.textContent = e.message || i18n.t('delegate_support_fail');
            }
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = i18n.t('delegate_support_submit');
            }
        }
    },

    async renderDelegateSupportList() {
        const container = document.getElementById('delegate-support-list-container');
        if (!container) return;
        if (!this.auth.user || !this.canManageOps()) {
            container.innerHTML = `<div class="loading">${this.escapeHtml(i18n.t('ops_no_permission'))}</div>`;
            return;
        }
        container.innerHTML = `<div class="loading">${this.escapeHtml(i18n.t('loading'))}</div>`;
        try {
            const data = await API.getDelegateSupportReports();
            const items = Array.isArray(data.items) ? data.items : [];
            this._delegateSupportItems = items;
            const toolbar = `
                <div class="delegate-support-toolbar">
                    <button type="button" id="delegate-support-export-csv-btn" class="ops-btn ops-btn--accent" onclick="app.downloadDelegateSupportExport('csv')">${this.escapeHtml(i18n.t('delegate_support_export_csv'))}</button>
                    <button type="button" id="delegate-support-export-pdf-btn" class="ops-btn ops-btn--accent" onclick="app.downloadDelegateSupportExport('pdf')">${this.escapeHtml(i18n.t('delegate_support_export_pdf'))}</button>
                </div>`;
            const msgHtml = this._delegateSupportListMsg
                ? `<div class="profile-form-msg success delegate-support-list-msg" role="status">${this.escapeHtml(this._delegateSupportListMsg)}</div>`
                : '';
            if (!items.length) {
                container.innerHTML = `${toolbar}${msgHtml}<div class="loading">${this.escapeHtml(i18n.t('delegate_support_list_empty'))}</div>`;
                this._delegateSupportListMsg = '';
                return;
            }
            const rows = items.map((item) => {
                const idJs = JSON.stringify(item.id || '');
                const lbl = {
                    occurred: this.escapeHtml(i18n.t('delegate_support_occurred_at')),
                    location: this.escapeHtml(i18n.t('delegate_support_location')),
                    delegate: this.escapeHtml(i18n.t('delegate_support_delegate_name')),
                    contact: this.escapeHtml(i18n.t('delegate_support_contact_name')),
                    volunteer: this.escapeHtml(i18n.t('delegate_support_volunteer_label')),
                    description: this.escapeHtml(i18n.t('delegate_support_description')),
                    actions: this.escapeHtml(i18n.t('ops_actions_label'))
                };
                return `<tr class="delegate-support-row">
                    <td data-label="${lbl.occurred}">${this.escapeHtml(this.formatDelegateSupportDateTime(item.occurred_at))}</td>
                    <td data-label="${lbl.location}">${this.escapeHtml(item.location || '')}</td>
                    <td data-label="${lbl.delegate}">${this.escapeHtml(item.delegate_name || '')}</td>
                    <td data-label="${lbl.contact}">${this.escapeHtml(item.contact_name || '')}</td>
                    <td data-label="${lbl.volunteer}">${this.escapeHtml(item.volunteer_name || '')}</td>
                    <td class="delegate-support-desc-cell" data-label="${lbl.description}">${this.escapeHtml(item.description || '')}</td>
                    <td class="delegate-support-actions-cell" data-label="${lbl.actions}">
                        <div class="delegate-support-actions-group">
                            <button type="button" class="delegate-support-action-btn" onclick='app.openDelegateSupportEditModal(${idJs})'>${this.escapeHtml(i18n.t('delegate_support_edit'))}</button>
                            <button type="button" class="delegate-support-action-btn delegate-support-action-btn--danger" onclick='app.openDelegateSupportDeleteModal(${idJs})'>${this.escapeHtml(i18n.t('delegate_support_delete'))}</button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
            container.innerHTML = `${toolbar}${msgHtml}
                <div class="delegate-support-table-wrap">
                    <table class="delegate-support-table">
                        <thead>
                            <tr>
                                <th class="delegate-support-col-time">${this.escapeHtml(i18n.t('delegate_support_occurred_at'))}</th>
                                <th class="delegate-support-col-location">${this.escapeHtml(i18n.t('delegate_support_location'))}</th>
                                <th class="delegate-support-col-name">${this.escapeHtml(i18n.t('delegate_support_delegate_name'))}</th>
                                <th class="delegate-support-col-name">${this.escapeHtml(i18n.t('delegate_support_contact_name'))}</th>
                                <th class="delegate-support-col-name">${this.escapeHtml(i18n.t('delegate_support_volunteer_label'))}</th>
                                <th>${this.escapeHtml(i18n.t('delegate_support_description'))}</th>
                                <th>${this.escapeHtml(i18n.t('ops_actions_label'))}</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>`;
            this._delegateSupportListMsg = '';
        } catch (e) {
            container.innerHTML = `<div class="loading">${this.escapeHtml(e.message || i18n.t('error_generic'))}</div>`;
        }
    },

    openDelegateSupportEditModal(id) {
        const item = this.findDelegateSupportItem(id);
        if (!item || !this.canManageOps()) return;
        this._delegateSupportEditModal = { id: item.id, item, error: '' };
        this._delegateSupportEditSaving = false;
        this.renderDelegateSupportEditModal();
    },

    closeDelegateSupportEditModal() {
        this._delegateSupportEditModal = null;
        this._delegateSupportEditSaving = false;
        const overlay = document.getElementById('delegate-support-edit-overlay');
        if (overlay) overlay.remove();
        document.body.classList.remove('ops-modal-open');
    },

    renderDelegateSupportEditModal() {
        const mod = this._delegateSupportEditModal;
        if (!mod) return;
        const item = mod.item;
        const saving = !!this._delegateSupportEditSaving;
        const errorHtml = mod.error
            ? `<div class="profile-form-msg error">${this.escapeHtml(mod.error)}</div>`
            : '';
        let overlay = document.getElementById('delegate-support-edit-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'delegate-support-edit-overlay';
            overlay.className = 'ops-confirm-overlay';
            document.body.appendChild(overlay);
        }
        overlay.innerHTML = `
            <div class="ops-confirm-panel delegate-support-edit-panel" role="dialog" aria-modal="true">
                <header class="ops-confirm-header">
                    <h3>${this.escapeHtml(i18n.t('delegate_support_edit_title'))}</h3>
                </header>
                <div class="ops-confirm-body profile-form">
                    ${errorHtml}
                    <div class="form-group">
                        <label for="delegate-support-edit-occurred-at">${this.escapeHtml(i18n.t('delegate_support_occurred_at'))}</label>
                        <input type="datetime-local" id="delegate-support-edit-occurred-at" class="form-input" required value="${this.escapeHtml(this.mysqlToDelegateDatetimeLocal(item.occurred_at))}">
                    </div>
                    <div class="form-group">
                        <label for="delegate-support-edit-location">${this.escapeHtml(i18n.t('delegate_support_location'))}</label>
                        <input type="text" id="delegate-support-edit-location" class="form-input" required maxlength="200" value="${this.escapeHtml(item.location || '')}">
                    </div>
                    <div class="form-group">
                        <label for="delegate-support-edit-delegate-name">${this.escapeHtml(i18n.t('delegate_support_delegate_name'))}</label>
                        <input type="text" id="delegate-support-edit-delegate-name" class="form-input" required maxlength="120" value="${this.escapeHtml(item.delegate_name || '')}">
                    </div>
                    <div class="form-group">
                        <label for="delegate-support-edit-contact-name">${this.escapeHtml(i18n.t('delegate_support_contact_name'))}</label>
                        <input type="text" id="delegate-support-edit-contact-name" class="form-input" required maxlength="120" value="${this.escapeHtml(item.contact_name || '')}">
                    </div>
                    <div class="form-group">
                        <label for="delegate-support-edit-description">${this.escapeHtml(i18n.t('delegate_support_description'))}</label>
                        <textarea id="delegate-support-edit-description" class="form-input" rows="4" required minlength="10" maxlength="2000">${this.escapeHtml(item.description || '')}</textarea>
                    </div>
                </div>
                <footer class="ops-confirm-footer">
                    <button type="button" class="btn-block outline" onclick="app.closeDelegateSupportEditModal()" ${saving ? 'disabled' : ''}>${this.escapeHtml(i18n.t('delegate_support_delete_cancel'))}</button>
                    <button type="button" class="btn-block" onclick="app.saveDelegateSupportEditModal()" ${saving ? 'disabled' : ''}>${this.escapeHtml(saving ? i18n.t('delegate_support_saving') : i18n.t('delegate_support_save_changes'))}</button>
                </footer>
            </div>`;
        document.body.classList.add('ops-modal-open');
    },

    async saveDelegateSupportEditModal() {
        const mod = this._delegateSupportEditModal;
        if (!mod || this._delegateSupportEditSaving) return;
        if (!navigator.onLine) {
            mod.error = i18n.t('delegate_support_offline');
            this.renderDelegateSupportEditModal();
            return;
        }
        const payload = {
            occurred_at: document.getElementById('delegate-support-edit-occurred-at')?.value || '',
            location: document.getElementById('delegate-support-edit-location')?.value.trim() || '',
            delegate_name: document.getElementById('delegate-support-edit-delegate-name')?.value.trim() || '',
            contact_name: document.getElementById('delegate-support-edit-contact-name')?.value.trim() || '',
            description: document.getElementById('delegate-support-edit-description')?.value.trim() || ''
        };
        if (!payload.occurred_at || !payload.location || !payload.delegate_name || !payload.contact_name || payload.description.length < 10) {
            mod.error = i18n.t('delegate_support_required');
            this.renderDelegateSupportEditModal();
            return;
        }
        this._delegateSupportEditSaving = true;
        mod.error = '';
        this.renderDelegateSupportEditModal();
        try {
            await API.updateDelegateSupportReport(mod.id, payload);
            this.closeDelegateSupportEditModal();
            this._delegateSupportListMsg = i18n.t('delegate_support_updated_success');
            await this.renderDelegateSupportList();
        } catch (e) {
            this._delegateSupportEditSaving = false;
            mod.error = e.message || i18n.t('delegate_support_fail');
            this.renderDelegateSupportEditModal();
        }
    },

    openDelegateSupportDeleteModal(id) {
        const item = this.findDelegateSupportItem(id);
        if (!item || !this.canManageOps()) return;
        this._delegateSupportDeleteModal = { id: item.id, item };
        this._delegateSupportDeleteSaving = false;
        this.renderDelegateSupportDeleteModal();
    },

    closeDelegateSupportDeleteModal() {
        this._delegateSupportDeleteModal = null;
        this._delegateSupportDeleteSaving = false;
        const overlay = document.getElementById('delegate-support-delete-overlay');
        if (overlay) overlay.remove();
        document.body.classList.remove('ops-modal-open');
    },

    renderDelegateSupportDeleteModal() {
        const mod = this._delegateSupportDeleteModal;
        if (!mod) return;
        const item = mod.item;
        const saving = !!this._delegateSupportDeleteSaving;
        let overlay = document.getElementById('delegate-support-delete-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'delegate-support-delete-overlay';
            overlay.className = 'ops-confirm-overlay';
            document.body.appendChild(overlay);
        }
        overlay.innerHTML = `
            <div class="ops-confirm-panel" role="dialog" aria-modal="true">
                <header class="ops-confirm-header">
                    <h3 id="delegate-support-delete-title">${this.escapeHtml(i18n.t('delegate_support_delete_title'))}</h3>
                </header>
                <div class="ops-confirm-body">
                    <p class="ops-confirm-note">${this.escapeHtml(i18n.t('delegate_support_delete_body'))}</p>
                    <p><strong>${this.escapeHtml(item.delegate_name || '—')}</strong> · ${this.escapeHtml(this.formatDelegateSupportDateTime(item.occurred_at))}</p>
                </div>
                <footer class="ops-confirm-footer">
                    <button type="button" class="btn-block outline" onclick="app.closeDelegateSupportDeleteModal()" ${saving ? 'disabled' : ''}>${this.escapeHtml(i18n.t('delegate_support_delete_cancel'))}</button>
                    <button type="button" class="btn-block ops-confirm-danger-btn" onclick="app.confirmDelegateSupportDelete()" ${saving ? 'disabled' : ''}>${this.escapeHtml(saving ? i18n.t('delegate_support_saving') : i18n.t('delegate_support_delete_confirm'))}</button>
                </footer>
            </div>`;
        document.body.classList.add('ops-modal-open');
    },

    async confirmDelegateSupportDelete() {
        const mod = this._delegateSupportDeleteModal;
        if (!mod || this._delegateSupportDeleteSaving) return;
        if (!navigator.onLine) {
            alert(i18n.t('delegate_support_offline'));
            return;
        }
        this._delegateSupportDeleteSaving = true;
        this.renderDelegateSupportDeleteModal();
        try {
            await API.deleteDelegateSupportReport(mod.id);
            this.closeDelegateSupportDeleteModal();
            this._delegateSupportListMsg = i18n.t('delegate_support_deleted_success');
            await this.renderDelegateSupportList();
        } catch (e) {
            this._delegateSupportDeleteSaving = false;
            alert(e.message || i18n.t('delegate_support_fail'));
            this.renderDelegateSupportDeleteModal();
        }
    },

    async downloadDelegateSupportExport(format) {
        const csvBtn = document.getElementById('delegate-support-export-csv-btn');
        const pdfBtn = document.getElementById('delegate-support-export-pdf-btn');
        const btn = format === 'pdf' ? pdfBtn : csvBtn;
        const prev = btn ? btn.textContent : '';
        if (btn) {
            btn.disabled = true;
            btn.textContent = i18n.t('delegate_support_exporting');
        }
        try {
            const blob = await API.downloadDelegateSupportExport(format);
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = format === 'pdf' ? 'zelo-delegados.pdf' : 'zelo-delegados.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        } catch (e) {
            alert(e.message || i18n.t('delegate_support_export_error'));
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = prev || i18n.t(format === 'pdf' ? 'delegate_support_export_pdf' : 'delegate_support_export_csv');
            }
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

    window.addEventListener('online', () => {
        updateNetworkStatus();
        if (typeof app !== 'undefined' && app.retryStaleCriticalData) {
            app.retryStaleCriticalData();
        }
    });
    window.addEventListener('offline', () => {
        updateNetworkStatus();
        if (typeof app !== 'undefined' && app.updateNetworkDegradedBanner) {
            app.updateNetworkDegradedBanner();
        }
    });

    // Initial check
    updateNetworkStatus();

    // Update External Links
    const linkLostPwd = document.getElementById('link-lost-password');
    if (linkLostPwd && API.siteUrl) {
        linkLostPwd.href = `${API.siteUrl}/wp-login.php?action=lostpassword`;
    }
});
