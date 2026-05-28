# CHANGELOG.md — Repositório ZELO

Histórico em nível de **projeto** (backend + frontend + docs). Detalhes finos do plugin WordPress estão em `backend-plugin/zelo-assistente/CHANGELOG.md`.

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).

---

## [Unreleased]

### Adicionado
- Plugin **2.6.5**: endpoint público `GET /zelo/v1/clima` (proxy Open-Meteo, cache 30 min, coordenadas do evento).
- PWA build **77**: view Previsão do tempo, item **TEMPO** no bottom nav, cache `localStorage` (`zelo_clima`).

### Alterado
- PWA build **76**: botões da escala operacional (Check-in, Check-out, Realocar, substituição) com estilo `ops-btn` alinhado ao tema (`btn-block`).

---

---

## [2026-05-28] — Plugin 2.6.0 (UX escala admin)

### Adicionado
- Backend `zelo-assistente` **2.6.0**: catálogos de escala, abas CRUD, voluntários sem conta WP (roster), validação de duplicados.

---

## [2026-05-28] — PWA build 75 (init ops retry)

### Corrigido
- `app.init`: após `refreshSession` ok, `loadVolunteerOps(true)` para não ficar preso em `_opsAuthFailed` de tentativa anterior.

---

## [2026-05-28] — PWA build 74 (auth ops pós-login)

### Corrigido
- Login: não limpar `_opsAuthFailed` quando `refreshSession` ok mas `loadVolunteerOps` falha (401/403).
- Init: `clearOpsAuthFailure` só após escala carregar com sucesso.
- Mensagem de sessão: referência ao plugin **2.5.3+**.

---

## [2026-05-28] — PWA build 73 (higiene console/HTML)

### Alterado
- `index.html`: meta `mobile-web-app-capable` (Chrome); `autocomplete` em nome/e-mail/telefone no cadastro; `type="tel"` no telefone.
- Log de registro do Service Worker: `SW registered` (typo corrigido).
- Cache PWA: `zelo-cache-v73`, assets `?v=73`.

---

## [2026-05-27] — Pacote A (voluntários dept. informações)

### Adicionado
- Painel operacional na home após login (`#home-volunteer-dashboard`).
- Bottom nav **OPERAÇÃO** para perfis com `view_ops`.
- Badges visuais de check-in (pendente / no posto / saiu).
- i18n PT/EN/ES para textos operacionais.

### Alterado
- `loadVolunteerOps()`: `mine=1` para voluntário comum; escala completa para supervisores/homem-chave.
- `getVolunteerOps`: apenas autenticação same-origin (sem retry público).
- Secção “cidade/mapas” colapsável na home quando voluntário logado.

### Segurança
- Removido filtro `zelo_ops_voluntarios_public_read` (plugin **2.5.1**).

### Infraestrutura
- PWA build **65**, cache `zelo-cache-v65`.
- Estrutura de governança técnica (docs + `.cursor/rules/`).

---

## [2026-05] — Operação voluntários e auth

### Adicionado
- Plugin **v2.5.0**: mapa indoor, cadastro/verificação de e-mail, histórico ops, datas do evento para cron.
- PWA: views login, registro, email-verified, escala, perfil; integração ops (check-in, realocação, swaps).
- Filtro temporário de leitura pública em `/ops/voluntarios` (apresentação — **remover**).

### Alterado
- Versionamento de cache PWA evoluindo (build 62+, cache v64+).
- `zelo-build.js` como fonte do número exibido no rodapé.

---

## [2026-03] — Categorias, importadores e filtros

### Adicionado
- Categorias dinâmicas (admin + API).
- Importador Google Places AJAX com barra de progresso.
- Filtros PWA: bairro, cidade, aberto agora; miniaturas e hero image nos detalhes.

### Corrigido
- Múltiplas iterações no parser de horários e sanitização de endereços (ver changelog do plugin 2.4.x).

---

## [1.0.0] — Lançamento inicial

### Adicionado
- Plugin WordPress `zelo-assistente` (CPT locais, API locais/evento, importador OSM).
- PWA offline-first com Leaflet, emergência, lista e mapa.

---

## Referência rápida de versões (verificar no código)

| Componente | Onde ver | Valor observado em 2026-05-27 |
|------------|----------|--------------------------------|
| Plugin WP | `zelo-assistente.php` → `ZELO_VERSION` | 2.5.0 |
| Build PWA | `zelo-build.js` | 65 |
| Cache SW | `sw.js` → `CACHE_NAME` | zelo-cache-v65 |
| Plugin | `zelo-assistente.php` | 2.5.1 |
