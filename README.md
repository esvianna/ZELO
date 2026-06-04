# Projeto ZELO - Assistente do Visitante

Este projeto consiste em uma solução completa (Backend + Frontend PWA) para auxiliar visitantes em grandes eventos (ex: Congressos em Salões do Reino), fornecendo informações essenciais como localização de farmácias, hospitais, contatos de emergência e dados do evento, com suporte a funcionamento offline.

## 🎯 Objetivo

O objetivo do **Zelo** é prover uma ferramenta rápida, acessível e resiliente para que departamentos de Primeiros Socorros e Indicadores possam orientar visitantes sobre serviços essenciais nas proximidades do local do evento. A aplicação deve funcionar mesmo em condições de conectividade intermitente.

## 📜 Regras e Estrutura

### Regras de Desenvolvimento
> 🚨 **ATENÇÃO:** Para garantir que as atualizações cheguem aos usuários, siga rigorosamente as regras definidas em [DEPLOYMENT_RULES.md](./DEPLOYMENT_RULES.md).

### Estrutura do Projeto
O projeto é dividido em dois módulos principais:

1.  **Backend (`/backend-plugin`)**: Um plugin WordPress (`zelo-assistente`) que atua como API e painel administrativo.
2.  **Frontend (`/frontend-pwa`)**: Uma Progressive Web App (PWA) estática que consome a API do plugin.

### Artefatos ignorados (`.gitignore`)

O repositório inclui [`.gitignore`](./.gitignore) na raiz (ROADMAP C1, [ZELO#19](https://github.com/esvianna/ZELO/issues/19)). Ignora segredos (`.env`), `vendor/`, caches de IDE/OS e relatórios de testes futuros.

**Exceções — permanecem versionados:**

| Caminho | Motivo |
|---------|--------|
| `frontend-pwa/**` | Deploy estático da PWA (HTML, CSS, JS, `sw.js`, imagens) |
| `backend-plugin/zelo-assistente/inc/lib/fpdf.php` | FPDF vendored para export PDF |
| `.cursor/rules/**` | Regras Cursor do projeto |

### Regras de Negócio e Lógica

#### 1. Backend (WordPress Plugin)
-   **Gerenciamento de Locais (`zelo_local`)**:
    -   Custom Post Type para cadastrar Farmácias, Hospitais e outros locais.
    -   Campos personalizados: Tipo (Hospital/Farmácia), Endereço, Telefone, Horário, Coordenadas (Lat/Lng).
    -   **Importador OSM**: Ferramenta integrada que busca dados do OpenStreetMap (via Overpass API) baseada em um raio (ex: 2km) do local do evento e cadastra/atualiza automaticamente os locais no sistema.
-   **Dados do Evento**:
    -   Configuração centralizada com Nome do Evento, Endereço, Coordenadas, Contatos (E-mail, Site) e Telefones de Emergência (ex: SAMU, Bombeiros, Polícia).
    -   **Aviso na Home**: Campo para mensagem de destaque na tela inicial do app (Info, Alerta, Crítico).
-   **API REST (`/wp-json/zelo/v1/`)**:
    -   **GET `/locais`**: Retorna locais próximos. Aceita parâmetros `lat`, `lng` e `radius` para filtrar e ordenar por distância.
    -   **GET `/evento`**: Retorna as informações gerais do evento, contatos de emergência e o aviso da home.

#### 2. Frontend (PWA)
-   **Tecnologias**: HTML5, CSS3, JavaScript (Vanilla), Leaflet.js (Mapas).
-   **Offline First**: Utiliza Service Worker (`sw.js`).
    -   **Assets (CSS, JS, Imagens, Fontes)**: Estratégia *Cache First* (prioriza cache para velocidade).
    -   **API Data**: Estratégia *Network First* (tenta buscar dados novos; se falhar, usa o cache).
-   **Funcionalidades**:
    -   **Home**: Acesso rápido a Emergência, Farmácias, Hospitais e **Avisos do Evento**.
    -   **Mapa**: Visualização interativa dos locais próximos usando Leaflet.
    -   **Lista**: Listagem de locais filtrada por categoria.
    -   **Emergência**: Lista rápida de telefones úteis.

## 🛠️ Instalação e Configuração

### Backend
1.  Copie a pasta `zelo-assistente` para o diretório de plugins do seu WordPress (`wp-content/plugins/`).
2.  Ative o plugin no painel administrativo.
3.  Vá em **Zelo Assistente > Configurações** (ou similar) para definir o local do evento.
4.  Use o **Importador** para povoar o banco de dados com locais próximos.

### Frontend
1.  Hospede o conteúdo da pasta `frontend-pwa` em um servidor web (pode ser no mesmo domínio do WP ou separado).
2.  Ajuste a URL da API no arquivo `assets/js/api.js` (se necessário) para apontar para o seu WordPress.

## 📝 Changelog

### [v43] - Aviso na Home e Melhorias de Cache
-   **Feature**: Adicionado componente de "Aviso do Evento" na tela inicial (abaixo das ações secundárias).
    -   Integrado com API `/evento` (campos `home_notice`).
    -   Suporte a tipos: Info, Warning, Critical.
-   **Fix**: Correção crítica no sistema de cache (Service Worker). Agora arquivos JS (`app-v5.js`, `api-v5.js`) são versionados explicitamente para garantir atualização imediata no cliente.
-   **Refactor**: Limpeza de código duplicado na API e logs de debug.

### [1.0.0] - Versão Inicial
-   **Lançamento do Projeto**: Estrutura base Backend + Frontend.
-   **Backend**:
    -   Implementação do CPT `zelo_local`.
    -   API Endpoints `/locais` e `/evento`.
    -   Importador automático do OpenStreetMap (Hospitais, Clínicas, Farmácias).
    -   Cálculo de distância (Haversine) no PHP para ordenação.
-   **Frontend**:
    -   Interface PWA responsiva e instalável.
    -   Service Worker com cacheamento de recursos e dados.
    -   Integração com mapas (Leaflet).
    -   Módulos de Emergência, Lista e Detalhes.
