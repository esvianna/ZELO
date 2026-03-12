const app = {
    data: {
        locais: [],
        evento: null,
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
                }

                // Render Home components if on home
                if (viewId === 'home') {
                    app.renderHomeNotice();
                    app.renderEventBanner();
                    app.renderHomeMap();
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
            this.updateUI();
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
                    body: JSON.stringify({
                        username: username,
                        password: password // *Note*: sending password plain over HTTPS is standard for this simple auth
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Success
                    this.user = data.user;
                    this.user.nonce = data.nonce;

                    // Persist
                    localStorage.setItem('zelo_user', JSON.stringify(this.user));

                    // Update UI
                    this.updateUI();

                    // Redirect
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

        logout() {
            this.user = null;
            localStorage.removeItem('zelo_user');
            this.updateUI();
            app.router.navigate('home');
        },

        handleIconClick() {
            if (this.user) {
                app.router.navigate('profile');
            } else {
                app.router.navigate('login');
            }
        },

        updateUI() {
            const iconContainer = document.getElementById('user-auth-indicator');
            if (!iconContainer) return;

            if (this.user) {
                // Show Avatar
                const avatarUrl = this.user.avatar || 'images/default-avatar.png'; // Fallback
                iconContainer.innerHTML = `<img src="${avatarUrl}" alt="User">`;

                // Update Profile View if active
                const pName = document.getElementById('profile-name');
                const pEmail = document.getElementById('profile-email');
                const pRole = document.getElementById('profile-role');
                const pAvatar = document.getElementById('profile-avatar');

                if (pName) pName.textContent = this.user.name;
                if (pEmail) pEmail.textContent = this.user.email;
                if (pRole) pRole.textContent = this.user.roles[0] || i18n.t('visitor_role');
                if (pAvatar) pAvatar.src = avatarUrl;

            } else {
                // Show Login Icon (SVG)
                iconContainer.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>`;
                iconContainer.removeAttribute('title'); // Remove title or set to 'Login'
                iconContainer.setAttribute('title', 'Entrar');
            }
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

        // Mock data loading or real API
        try {
            // Init Auth
            this.auth.init();

            // Load initial data
            const [locais, evento] = await Promise.all([
                API.getLocais(), // Fetch all initially
                API.getEvento()
            ]);

            this.data.locais = locais || [];
            this.data.evento = evento || {};

            console.log('Data loaded', this.data);

            // Render header if we are already on home (which we usually are at init)
            this.renderEventBanner();
            this.renderHomeMap();

        } catch (err) {
            console.error('Failed to load data', err);
            // Show offline message if needed
        }

        // Handle URL hash if we implement deep linking, for now simple init
        this.router.navigate('home');

        // Request location
        this.getUserLocation();
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
        if (!coords.lat || !coords.lng || mapEl._leaflet_id) return;

        setTimeout(() => {
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

        const categoryIcons = {
            'hospital': { bg: '#dbeafe', icon: '\u{1F3E5}' },
            'farmacia': { bg: '#d1fae5', icon: '\u{1F48A}' },
            'emergencia': { bg: '#ffe5e8', icon: '\u{1F691}' },
            'cultura': { bg: '#fef3c7', icon: '\u{1F3DB}\uFE0F' },
            'compras': { bg: '#fce7f3', icon: '\u{1F6CD}\uFE0F' },
            'lazer': { bg: '#d1fae5', icon: '\u{1F333}' }
        };

        resultsEl.style.display = 'block';
        resultsEl.innerHTML = matches.map(item => {
            const cat = categoryIcons[item.category] || { bg: '#f0f0f0', icon: '\u{1F4CD}' };
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

        // Set Title based on category
        if (category === 'farmacia') {
            title.textContent = i18n.t('pharmacies');
        } else if (category === 'hospital') {
            title.textContent = i18n.t('hospitals');
        } else if (category === 'cultura') {
            title.textContent = i18n.t('culture');
        } else if (category === 'compras') {
            title.textContent = i18n.t('shopping');
        } else if (category === 'lazer') {
            title.textContent = i18n.t('leisure');
        } else {
            title.textContent = i18n.t('places');
        }


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
        const categoryMeta = {
            'hospital': { icon: '\u{1F3E5}', bg: '#dbeafe', color: '#3b82f6', label: i18n.t('category_hospital') },
            'farmacia': { icon: '\u{1F48A}', bg: '#d1fae5', color: '#10b981', label: i18n.t('category_pharmacy') },
            'emergencia': { icon: '\u{1F691}', bg: '#ffe5e8', color: '#e63946', label: i18n.t('emergency') },
            'cultura': { icon: '\u{1F3DB}\uFE0F', bg: '#fef3c7', color: '#f59e0b', label: i18n.t('culture') },
            'compras': { icon: '\u{1F6CD}\uFE0F', bg: '#fce7f3', color: '#ec4899', label: i18n.t('shopping') },
            'lazer': { icon: '\u{1F333}', bg: '#d1fae5', color: '#059669', label: i18n.t('leisure') }
        };

        html += paginatedItems.map(item => {
            // Determine distance text
            let distText = '';
            if (this.data.userLocation && item._userDistance) {
                distText = item._userDistance.toFixed(1) + ' km';
            } else if (item.distance) {
                distText = item.distance + ' km';
            }

            const meta = categoryMeta[item.category] || { icon: '\u{1F4CD}', bg: '#f0f4f8', color: '#666', label: '' };
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
        const categoryMeta = {
            'hospital': { icon: '\u{1F3E5}', bg: '#dbeafe', color: '#3b82f6', gradient: 'linear-gradient(135deg, #1e40af, #3b82f6)' },
            'farmacia': { icon: '\u{1F48A}', bg: '#d1fae5', color: '#10b981', gradient: 'linear-gradient(135deg, #047857, #10b981)' },
            'emergencia': { icon: '\u{1F691}', bg: '#ffe5e8', color: '#e63946', gradient: 'linear-gradient(135deg, #991b1b, #e63946)' },
            'cultura': { icon: '\u{1F3DB}\uFE0F', bg: '#fef3c7', color: '#f59e0b', gradient: 'linear-gradient(135deg, #b45309, #f59e0b)' },
            'compras': { icon: '\u{1F6CD}\uFE0F', bg: '#fce7f3', color: '#ec4899', gradient: 'linear-gradient(135deg, #9d174d, #ec4899)' },
            'lazer': { icon: '\u{1F333}', bg: '#d1fae5', color: '#059669', gradient: 'linear-gradient(135deg, #065f46, #059669)' }
        };
        const meta = categoryMeta[item.category] || { icon: '\u{1F4CD}', bg: '#f0f4f8', color: '#666', gradient: 'linear-gradient(135deg, #374151, #6b7280)' };

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
        const categoryLabel = {
            'hospital': i18n.t('category_hospital'), 'farmacia': i18n.t('category_pharmacy'),
            'cultura': i18n.t('culture'), 'compras': i18n.t('shopping'), 'lazer': i18n.t('leisure')
        }[item.category] || i18n.t('places');

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

                    const icon = MapManager.createIcon(item.category === 'farmacia' ? 'green' : (item.category === 'hospital' ? 'red' : 'blue'));
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
