const i18n = {
    current: 'pt_br', // Default
    
    // Core translation dictionary
    dict: {
        pt_br: {
            // General
            "welcome_title": "Bem-vindo ao Zelo",
            "welcome_subtitle": "Cuidado e atenção para você.",
            "emergency": "Emergência",
            "pharmacies": "Farmácias",
            "hospitals": "Hospitais",
            "view_map": "Ver Mapa Geral",
            "event_info": "Informações do Evento",
            "search_placeholder": "Buscar por nome...",
            "back": "Voltar",
            "loading": "Carregando...",
            "places": "Locais",
            "details": "Detalhes",
            "restricted_access": "Acesso Restrito",
            "login_prompt": "Entre com sua conta para acessar recursos exclusivos.",
            "username_email": "Usuário ou Email",
            "password": "Senha",
            "login_btn": "Entrar",
            "forgot_password": "Esqueci minha senha",
            "my_profile": "Meu Perfil",
            "visitor_role": "Visitante",
            "edit_profile": "Alterar Avatar / Dados",
            "update_app": "↻ Atualizar App / Limpar Cache",
            "logout": "Sair",
            
            // Bottom Nav
            "nav_home": "INÍCIO",
            "nav_map": "MAPA",
            "nav_sos": "S.O.S",
            "nav_info": "INFO",
            "nav_weather": "TEMPO",
            "nav_profile": "PERFIL",
            "nav_ops": "OPERAÇÃO",
            "weather_title": "Previsão do tempo",
            "weather_updated": "Atualizado às",
            "weather_offline": "Offline",
            "weather_stale": "Dados podem estar desatualizados",
            "weather_humidity": "Umidade",
            "weather_wind": "Vento",
            "weather_feels_like": "Sensação",
            "weather_today": "Hoje",
            "weather_week": "Próximos dias",
            "weather_hourly": "Próximas horas",
            "weather_unavailable": "Previsão indisponível no momento.",
            "weather_disabled": "Previsão do tempo desativada para este evento.",
            "weather_no_coords": "Coordenadas do evento não configuradas.",
            "weather_retry": "Tentar novamente",
            "weather_attribution": "Dados: Open-Meteo",
            "weather_precip": "Chuva",
            "weather_widget_tap": "Toque para ver a previsão completa",
            "header_menu_install": "Instalar aplicativo",
            "avisos_title": "Avisos",
            "avisos_filter_all": "Todos",
            "avisos_filter_personal": "Para você",
            "avisos_filter_event": "Evento",
            "avisos_mark_all_read": "Marcar todos como lidos",
            "avisos_empty": "Nenhum aviso no momento.",
            "avisos_view_all": "Ver avisos",
            "avisos_event_notice": "Aviso do evento",
            "avisos_shift_reminder": "Próximo turno",
            "avisos_commitment_pending": "Confirmar participação no turno",
            "avisos_checkin_pending": "Confirmar chegada",
            "avisos_checkout_pending": "Confirmar saída",
            "avisos_registration_pending": "Cadastro aguardando aprovação",
            "avisos_decline_supervisor": "Recusa de designação",
            "ops_commit_accept": "Aceitar turno",
            "ops_commit_decline": "Não posso",
            "ops_commit_on_behalf": "Confirmar por voluntário",
            "ops_commitment_pending": "Aguardando confirmação",
            "ops_commitment_accepted": "Participação confirmada",
            "ops_commitment_declined": "Participação recusada",
            "ops_commitment_deadline_passed": "Prazo encerrado — contacte o supervisor",
            "ops_link_pending": "Seu cadastro está aguardando vínculo com a escala pelo administrador.",
            "register_languages_label": "Idiomas que fala (opcional)",
            "profile_languages_title": "Idiomas que falo",
            "profile_languages_hint": "Opcional. Usado na escala operacional.",
            "profile_languages_save": "Salvar idiomas",
            "profile_languages_saved": "Idiomas salvos.",
            "ops_push_prompt": "Ativar notificações",
            "ops_push_prompt_body": "Receba lembretes de turno e confirmações no dispositivo.",
            "avisos_swap_pending": "Pedido de substituição",
            "avisos_swap_summary": "Designação {0} — aguarda aprovação",
            "role_volunteer": "Voluntário",
            "role_keyman": "Homem-chave",
            "role_group_supervisor": "Supervisor de grupo",
            "role_app_supervisor": "Supervisor do aplicativo",
            "role_admin": "Administrador",
            "ops_dashboard_title": "Olá",
            "ops_my_assignments": "Minhas designações",
            "ops_my_schedule": "Minha escala",
            "ops_detailed_schedule": "Escala detalhada",
            "ops_governance_title": "Responsáveis por dia",
            "ops_view_full_schedule": "Ver escala completa",
            "ops_quick_checkin": "Confirmar chegada",
            "ops_location": "Local",
            "ops_no_assignments": "Nenhuma designação vinculada à sua conta. Fale com o supervisor.",
            "ops_no_wp_user": "Nenhuma linha da escala vinculada ao seu utilizador (wp_user_id).",
            "ops_status_pending": "Pendente",
            "ops_status_checked_in": "No posto",
            "ops_status_checked_out": "Saiu",
            "home_visitor_extras_summary": "Mais opções (cidade e mapas)",
            "ops_session_expired": "Sua sessão expirou ou o acesso à escala não está válido. Entre novamente para atualizar.",
            
            // Map/List Filters
            "near_me": "Perto de mim",
            "az": "A-Z",
            "neighborhood": "Bairro",
            "city": "Cidade",
            "open_now": "🕒 Aberto Agora",
            "no_places_found": "Nenhum local encontrado com estes filtros.",
            "clear_filters": "Limpar Filtros",
            "previous": "Anterior",
            "next": "Próximo",
            "page_of": "Página {0} de {1}",
            
            // Details
            "category_pharmacy": "Farmácia",
            "category_hospital": "Hospital",
            "open_status": "Aberto Agora",
            "closed_status": "Fechado/Indisponível",
            "check_hours": "Ver Horários",
            "directions": "🗺️ Como chegar",
            "call_now": "📞 Ligar agora",
            "visitor_notes": "ℹ️ Observações para Visitantes",
            "hours_of_operation": "🕒 Horário de Funcionamento",
            "quick_info": "📋 Informações Rápidas",
            "phone": "Telefone",
            "website": "Website",
            "visit_website": "Acessar site oficial",
            "parking": "Estacionamento",
            "parking_available": "Disponível (Consultar local)",
            "emergency_help_title": "Precisa de ajuda imediata?",
            "emergency_help_desc": "Entre em contato com a linha de emergência.",
            "emergency_title": "Aviso do Evento",
            
            // Home Redesign v49
            "home_search_placeholder": "Buscar serviços ou locais...",
            "welcome_greeting": "Bem-vindo!",
            "welcome_subtitle_new": "Como podemos ajudar você hoje?",
            "emergency_actions_title": "AÇÕES DE EMERGÊNCIA",
            "discover_city_title": "DESCOBRIR A CIDADE",
            "location_title": "LOCALIZAÇÃO",
            "culture": "Cultura",
            "shopping": "Compras",
            "leisure": "Lazer",
            "event_tag": "DESTAQUE",
            "view_details": "Ver Detalhes",
            "share": "Compartilhar",
            "link_copied": "Copiado para a área de transferência!"
        },
        en: {
            "welcome_title": "Welcome to Zelo",
            "welcome_subtitle": "Care and attention for you.",
            "emergency": "Emergency",
            "pharmacies": "Pharmacies",
            "hospitals": "Hospitals",
            "view_map": "View Full Map",
            "event_info": "Event Information",
            "search_placeholder": "Search by name...",
            "back": "Back",
            "loading": "Loading...",
            "places": "Places",
            "details": "Details",
            "restricted_access": "Restricted Access",
            "login_prompt": "Sign in with your account to access exclusive features.",
            "username_email": "Username or Email",
            "password": "Password",
            "login_btn": "Sign In",
            "forgot_password": "Forgot my password",
            "my_profile": "My Profile",
            "visitor_role": "Visitor",
            "edit_profile": "Change Avatar / Data",
            "update_app": "↻ Update App / Clear Cache",
            "logout": "Logout",
            
            "nav_home": "HOME",
            "nav_map": "MAP",
            "nav_sos": "S.O.S",
            "nav_info": "INFO",
            "nav_weather": "WEATHER",
            "nav_profile": "PROFILE",
            "nav_ops": "OPERATION",
            "weather_title": "Weather forecast",
            "weather_updated": "Updated at",
            "weather_offline": "Offline",
            "weather_stale": "Data may be outdated",
            "weather_humidity": "Humidity",
            "weather_wind": "Wind",
            "weather_feels_like": "Feels like",
            "weather_today": "Today",
            "weather_week": "Next days",
            "weather_hourly": "Next hours",
            "weather_unavailable": "Forecast unavailable right now.",
            "weather_disabled": "Weather forecast is disabled for this event.",
            "weather_no_coords": "Event coordinates are not configured.",
            "weather_retry": "Try again",
            "weather_attribution": "Data: Open-Meteo",
            "weather_precip": "Rain",
            "weather_widget_tap": "Tap for full forecast",
            "header_menu_install": "Install app",
            "avisos_title": "Notices",
            "avisos_filter_all": "All",
            "avisos_filter_personal": "For you",
            "avisos_filter_event": "Event",
            "avisos_mark_all_read": "Mark all as read",
            "avisos_empty": "No notices at the moment.",
            "avisos_view_all": "View notices",
            "avisos_event_notice": "Event notice",
            "avisos_shift_reminder": "Upcoming shift",
            "avisos_commitment_pending": "Confirm shift participation",
            "avisos_checkin_pending": "Confirm check-in",
            "avisos_checkout_pending": "Confirm check-out",
            "avisos_registration_pending": "Registration pending approval",
            "avisos_decline_supervisor": "Assignment declined",
            "ops_commit_accept": "Accept shift",
            "ops_commit_decline": "Cannot attend",
            "ops_commit_on_behalf": "Confirm for volunteer",
            "ops_commitment_pending": "Awaiting confirmation",
            "ops_commitment_accepted": "Participation confirmed",
            "ops_commitment_declined": "Participation declined",
            "ops_commitment_deadline_passed": "Deadline passed — contact your supervisor",
            "ops_link_pending": "Your registration is awaiting schedule linkage by an administrator.",
            "register_languages_label": "Languages you speak (optional)",
            "profile_languages_title": "Languages I speak",
            "profile_languages_hint": "Optional. Used on the volunteer schedule.",
            "profile_languages_save": "Save languages",
            "profile_languages_saved": "Languages saved.",
            "ops_push_prompt": "Enable notifications",
            "ops_push_prompt_body": "Receive shift reminders on this device.",
            "avisos_swap_pending": "Swap request",
            "avisos_swap_summary": "Assignment {0} — pending approval",
            "role_volunteer": "Volunteer",
            "role_keyman": "Key man",
            "role_group_supervisor": "Group supervisor",
            "role_app_supervisor": "App supervisor",
            "role_admin": "Administrator",
            "ops_dashboard_title": "Hello",
            "ops_my_assignments": "My assignments",
            "ops_my_schedule": "My schedule",
            "ops_detailed_schedule": "Full schedule",
            "ops_governance_title": "Leaders by day",
            "ops_view_full_schedule": "View full schedule",
            "ops_quick_checkin": "Check in",
            "ops_location": "Location",
            "ops_no_assignments": "No assignments linked to your account. Contact your supervisor.",
            "ops_no_wp_user": "No schedule row linked to your user (wp_user_id).",
            "ops_status_pending": "Pending",
            "ops_status_checked_in": "On site",
            "ops_status_checked_out": "Checked out",
            "home_visitor_extras_summary": "More options (city and maps)",
            "ops_session_expired": "Your session expired or schedule access is invalid. Please sign in again.",
            
            "near_me": "Near me",
            "az": "A-Z",
            "neighborhood": "Neighborhood",
            "city": "City",
            "open_now": "🕒 Open Now",
            "no_places_found": "No places found with these filters.",
            "clear_filters": "Clear Filters",
            "previous": "Previous",
            "next": "Next",
            "page_of": "Page {0} of {1}",
            
            "category_pharmacy": "Pharmacy",
            "category_hospital": "Hospital",
            "open_status": "Open Now",
            "closed_status": "Closed/Unavailable",
            "check_hours": "Check Hours",
            "directions": "🗺️ Get Directions",
            "call_now": "📞 Call Now",
            "visitor_notes": "ℹ️ Visitor Notes",
            "hours_of_operation": "🕒 Hours of Operation",
            "quick_info": "📋 Quick Info",
            "phone": "Phone",
            "website": "Website",
            "visit_website": "Visit official site",
            "parking": "Parking",
            "parking_available": "Available (Check location)",
            "emergency_help_title": "Need immediate help?",
            "emergency_help_desc": "Contact the emergency line.",
            "emergency_title": "Event Notice",
            
            // Home Redesign v49
            "home_search_placeholder": "Search services or places...",
            "welcome_greeting": "Welcome!",
            "welcome_subtitle_new": "How can we help you today?",
            "emergency_actions_title": "EMERGENCY ACTIONS",
            "discover_city_title": "DISCOVER THE CITY",
            "location_title": "LOCATION",
            "culture": "Culture",
            "shopping": "Shopping",
            "leisure": "Leisure",
            "event_tag": "FEATURED",
            "view_details": "View Details",
            "share": "Share",
            "link_copied": "Copied to clipboard!"
        },
        es: {
            "welcome_title": "Bienvenido a Zelo",
            "welcome_subtitle": "Cuidado y atención para ti.",
            "emergency": "Emergencia",
            "pharmacies": "Farmacias",
            "hospitals": "Hospitales",
            "view_map": "Ver Mapa General",
            "event_info": "Información del Evento",
            "search_placeholder": "Buscar por nombre...",
            "back": "Volver",
            "loading": "Cargando...",
            "places": "Lugares",
            "details": "Detalles",
            "restricted_access": "Acceso Restringido",
            "login_prompt": "Inicia sesión con tu cuenta para acceder a funciones exclusivas.",
            "username_email": "Usuario o Correo",
            "password": "Contraseña",
            "login_btn": "Entrar",
            "forgot_password": "Olvidé mi contraseña",
            "my_profile": "Mi Perfil",
            "visitor_role": "Visitante",
            "edit_profile": "Cambiar Avatar / Datos",
            "update_app": "↻ Actualizar App / Limpiar Caché",
            "logout": "Salir",
            
            "nav_home": "INICIO",
            "nav_map": "MAPA",
            "nav_sos": "S.O.S",
            "nav_info": "INFO",
            "nav_weather": "CLIMA",
            "nav_profile": "PERFIL",
            "nav_ops": "OPERACIÓN",
            "weather_title": "Pronóstico del tiempo",
            "weather_updated": "Actualizado a las",
            "weather_offline": "Sin conexión",
            "weather_stale": "Los datos pueden estar desactualizados",
            "weather_humidity": "Humedad",
            "weather_wind": "Viento",
            "weather_feels_like": "Sensación",
            "weather_today": "Hoy",
            "weather_week": "Próximos días",
            "weather_hourly": "Próximas horas",
            "weather_unavailable": "Pronóstico no disponible en este momento.",
            "weather_disabled": "Pronóstico desactivado para este evento.",
            "weather_no_coords": "Coordenadas del evento no configuradas.",
            "weather_retry": "Intentar de nuevo",
            "weather_attribution": "Datos: Open-Meteo",
            "weather_precip": "Lluvia",
            "weather_widget_tap": "Toque para ver el pronóstico completo",
            "header_menu_install": "Instalar aplicación",
            "avisos_title": "Avisos",
            "avisos_filter_all": "Todos",
            "avisos_filter_personal": "Para ti",
            "avisos_filter_event": "Evento",
            "avisos_mark_all_read": "Marcar todos como leídos",
            "avisos_empty": "No hay avisos en este momento.",
            "avisos_view_all": "Ver avisos",
            "avisos_event_notice": "Aviso del evento",
            "avisos_shift_reminder": "Próximo turno",
            "avisos_commitment_pending": "Confirmar participación en el turno",
            "avisos_checkin_pending": "Confirmar llegada",
            "avisos_checkout_pending": "Confirmar salida",
            "avisos_registration_pending": "Registro pendiente de aprobación",
            "avisos_decline_supervisor": "Designación rechazada",
            "ops_commit_accept": "Aceptar turno",
            "ops_commit_decline": "No puedo",
            "ops_commit_on_behalf": "Confirmar por voluntario",
            "ops_commitment_pending": "Esperando confirmación",
            "ops_commitment_accepted": "Participación confirmada",
            "ops_commitment_declined": "Participación rechazada",
            "ops_commitment_deadline_passed": "Plazo cerrado — contacte al supervisor",
            "ops_link_pending": "Su registro espera vinculación con la escala por el administrador.",
            "register_languages_label": "Idiomas que habla (opcional)",
            "profile_languages_title": "Idiomas que hablo",
            "profile_languages_hint": "Opcional. Se usa en la escala operativa.",
            "profile_languages_save": "Guardar idiomas",
            "profile_languages_saved": "Idiomas guardados.",
            "ops_push_prompt": "Activar notificaciones",
            "ops_push_prompt_body": "Reciba recordatorios de turno en este dispositivo.",
            "avisos_swap_pending": "Solicitud de sustitución",
            "avisos_swap_summary": "Designación {0} — pendiente de aprobación",
            "role_volunteer": "Voluntario",
            "role_keyman": "Hombre clave",
            "role_group_supervisor": "Supervisor de grupo",
            "role_app_supervisor": "Supervisor de la aplicación",
            "role_admin": "Administrador",
            "ops_dashboard_title": "Hola",
            "ops_my_assignments": "Mis asignaciones",
            "ops_my_schedule": "Mi escala",
            "ops_detailed_schedule": "Escala detallada",
            "ops_governance_title": "Responsables por día",
            "ops_view_full_schedule": "Ver escala completa",
            "ops_quick_checkin": "Confirmar llegada",
            "ops_location": "Ubicación",
            "ops_no_assignments": "Ninguna asignación vinculada a su cuenta. Hable con el supervisor.",
            "ops_no_wp_user": "Ninguna fila de escala vinculada a su usuario (wp_user_id).",
            "ops_status_pending": "Pendiente",
            "ops_status_checked_in": "En el puesto",
            "ops_status_checked_out": "Salió",
            "home_visitor_extras_summary": "Más opciones (ciudad y mapas)",
            "ops_session_expired": "Su sesión expiró o el acceso a la escala no es válido. Inicie sesión de nuevo.",
            
            "near_me": "Cerca de mí",
            "az": "A-Z",
            "neighborhood": "Barrio",
            "city": "Ciudad",
            "open_now": "🕒 Abierto Ahora",
            "no_places_found": "No se encontraron lugares con estos filtros.",
            "clear_filters": "Limpiar Filtros",
            "previous": "Anterior",
            "next": "Siguiente",
            "page_of": "Página {0} de {1}",
            
            "category_pharmacy": "Farmacia",
            "category_hospital": "Hospital",
            "open_status": "Abierto Ahora",
            "closed_status": "Cerrado/No disponible",
            "check_hours": "Ver Horarios",
            "directions": "🗺️ Cómo llegar",
            "call_now": "📞 Llamar ahora",
            "visitor_notes": "ℹ️ Notas para Visitantes",
            "hours_of_operation": "🕒 Horario de Atención",
            "quick_info": "📋 Información Rápida",
            "phone": "Teléfono",
            "website": "Sitio Web",
            "visit_website": "Visitar sitio oficial",
            "parking": "Estacionamiento",
            "parking_available": "Disponible (Consultar lugar)",
            "emergency_help_title": "¿Necesitas ayuda inmediata?",
            "emergency_help_desc": "Comunícate a la línea de emergencia.",
            "emergency_title": "Aviso del Evento",
            
            // Home Redesign v49
            "home_search_placeholder": "Buscar servicios o lugares...",
            "welcome_greeting": "¡Bienvenido!",
            "welcome_subtitle_new": "¿Cómo podemos ayudarte hoy?",
            "emergency_actions_title": "ACCIONES DE EMERGENCIA",
            "discover_city_title": "DESCUBRIR LA CIUDAD",
            "location_title": "UBICACIÓN",
            "culture": "Cultura",
            "shopping": "Compras",
            "leisure": "Ocio",
            "event_tag": "DESTACADO",
            "view_details": "Ver Detalles",
            "share": "Compartir",
            "link_copied": "¡Copiado al portapapeles!"
        }
    },

    /**
     * Initialize i18n from localStorage
     */
    init() {
        const savedLang = localStorage.getItem('zelo_lang');
        if (savedLang && this.dict[savedLang]) {
            this.current = savedLang;
        } else {
            // Attempt to get from browser language if desired, or stick to default pt_br
            const browserLang = navigator.language.split('-')[0];
            if (this.dict[browserLang]) {
                 this.current = browserLang;
            } else {
                 this.current = 'pt_br';
            }
        }
        this.updateDOM();
    },

    /**
     * Change language and update DOM
     */
    setLanguage(lang) {
        if (this.dict[lang]) {
            this.current = lang;
            localStorage.setItem('zelo_lang', lang);
            this.updateDOM();
            
            // Re-render current view in app.js if needed (handled by an event or direct call)
            if (window.app && app.router) {
                app.router.navigate(app.router.currentView, app.router.lastParams || {});
            }
            
            // Re-render auth icon logic explicitly to be safe
            if (window.app && app.auth) {
                 app.auth.updateUI();
            }
        }
    },

    /**
     * Get translation for a specific key
     */
    t(key, ...args) {
        let text = this.dict[this.current][key] || this.dict['pt_br'][key] || key;
        
        // Simple string interpolation for {0}, {1}
        if (args.length > 0) {
            args.forEach((arg, i) => {
                text = text.replace(new RegExp(`\\{${i}\\}`, 'g'), arg);
            });
        }
        return text;
    },

    /**
     * Update all DOM elements with data-i18n attribute
     */
    updateDOM() {
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            const translation = this.t(key);
            
            // Handle placeholders for inputs
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                 if (el.hasAttribute('placeholder')) {
                      el.setAttribute('placeholder', translation);
                 }
            } else {
                 el.textContent = translation;
            }
        });
        
        // Update language selector UI if it exists
        const langSelect = document.getElementById('lang-selector');
        if (langSelect) {
            langSelect.value = this.current;
        }
        
        // Dispatch custom event for app components to listen
        document.dispatchEvent(new CustomEvent('zelo:langChanged', { detail: { lang: this.current } }));
    }
};

// Initialize early
document.addEventListener('DOMContentLoaded', () => {
    i18n.init();
});
