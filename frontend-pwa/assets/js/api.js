const API = {
    baseUrl: '/wp-json/zelo/v1',

    // Cached data for offline support
    cache: {
        locais: null,
        evento: null
    },

    async getLocais(params = {}) {
        const query = new URLSearchParams(params).toString();
        const url = `${this.baseUrl}/locais?${query}`;
        
        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();
            
            // Update cache
            this.cache.locais = data;
            localStorage.setItem('zelo_locais', JSON.stringify(data));
            
            return data;
        } catch (error) {
            console.warn('Fetch failed, trying cache', error);
            // Fallback to local storage
            const cached = localStorage.getItem('zelo_locais');
            if (cached) {
                return JSON.parse(cached);
            }
            throw error;
        }
    },

    async getEvento() {
        const url = `${this.baseUrl}/evento`;
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
    }
};
