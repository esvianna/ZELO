const API = {
    baseUrl: 'https://zelo.quadrodeanuncios.com.br/wp-json/zelo/v1',
    siteUrl: 'https://zelo.quadrodeanuncios.com.br', // Base WP URL for links

    // Cached data for offline support
    cache: {
        locais: null,
        evento: null
    },

    async getLocais(params = {}) {
        // Add timestamp to prevent caching
        params._t = Date.now();
        const query = new URLSearchParams(params).toString();
        const url = `${this.baseUrl}/locais?${query}`;

        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
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
        const url = `${this.baseUrl}/evento`;
        try {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
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
    }
};
