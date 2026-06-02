# AGENTS.md — Guia para assistentes de IA (ZELO)

Este arquivo é o **ponto de entrada** para qualquer agente (Cursor, Copilot, etc.) que trabalhe neste repositório.

## O que é o ZELO

Solução **Backend (plugin WordPress) + Frontend (PWA estática)** para assistir visitantes e voluntários em grandes eventos (congressos, salões do reino). Funciona com foco em **offline-first** e **same-origin** com WordPress para autenticação por cookies/nonce.

| Módulo | Caminho | Stack |
|--------|---------|--------|
| Backend | `backend-plugin/zelo-assistente/` | PHP, WordPress REST API, CPT `zelo_local`, `wp_options` |
| Frontend | `frontend-pwa/` | HTML/CSS/JS vanilla, Leaflet, Service Worker |
| Docs operacionais | `docs/`, raiz | Deploy, PRD UX, regras de cache |

## Antes de alterar código (obrigatório)

1. Leia **`PROJECT_STATUS.md`** — onde paramos, pendências e próximos passos.
2. Consulte **`DECISIONS.md`** — decisões já tomadas; não as reverta sem registro novo.
3. Para escopo de produto: `docs/product_requirements_document.md` (UX/branding).
4. Para deploy da PWA: **`DEPLOYMENT_RULES.md`** (versionamento de cache).
5. Para deploy voluntários/auth: **`docs/DEPLOY-ZELO-PWA.md`**.
6. Para histórico de releases do plugin: **`backend-plugin/zelo-assistente/CHANGELOG.md`**.
7. Avalie **impacto**: API pública, cache offline, roles/capabilities, dados em produção.
8. **Preserve** comportamento existente; evite refatorações amplas não solicitadas.
9. **Não** altere URLs de produção em `api-v5.js` sem confirmação explícita do usuário.

## Depois de alterar código (obrigatório)

1. Atualize **`PROJECT_STATUS.md`** (seção “Última sessão” e pendências, se aplicável).
2. Registre em **`CHANGELOG.md`** (raiz) e, se for release do plugin, em **`backend-plugin/zelo-assistente/CHANGELOG.md`**.
3. Decisões novas ou mudança de direção → **`DECISIONS.md`** (formato ADR curto).
4. Riscos de segurança novos → **`SECURITY.md`**.
5. Como testar → descreva em **`TESTING.md`** ou na seção de testes do `PROJECT_STATUS.md`.
6. Próximo passo lógico → atualize **`ROADMAP.md`** ou `PROJECT_STATUS.md`.
7. Se mudou **frontend** (CSS/JS/HTML): siga **`DEPLOYMENT_RULES.md`** e alinhe `zelo-build.js`, `index.html` e `sw.js`.

## Mapa de arquivos de governança

| Arquivo | Função |
|---------|--------|
| `PROJECT_STATUS.md` | **Status vivo** — onde paramos, feito, pendente, riscos |
| `ROADMAP.md` | Prioridades e fases de produto |
| `CHANGELOG.md` | Histórico do repositório (projeto inteiro) |
| `DECISIONS.md` | ADRs — decisões técnicas |
| `SECURITY.md` | Princípios e checklist de segurança |
| `TESTING.md` | Como validar mudanças |
| `.cursor/rules/*.mdc` | Regras automáticas no Cursor |

## Convenções do código

### Backend (PHP / WordPress)

- Prefixo de funções: `zelo_`.
- Namespace REST: `zelo/v1`.
- Sempre `ABSPATH` guard no topo dos includes.
- Sanitizar entradas (`sanitize_*`, `esc_*`); escapar saídas no admin.
- `permission_callback` explícito em **toda** rota REST (nunca assumir privado).
- Capabilities custom: `zelo_view_ops`, `zelo_checkin_ops`, `zelo_reallocate_volunteer`, `zelo_manage_ops`.

### Frontend (PWA)

- Lógica principal: `assets/js/app-v5.js` (router, views, auth, ops).
- API: `assets/js/api-v5.js` — `baseUrl` / `siteUrl` e cache em memória/localStorage.
- Build exibido: `assets/js/zelo-build.js` → `window.ZELO_APP_BUILD`.
- i18n: `assets/js/i18n.js` (pt_br, en, es).
- Service Worker: `sw.js` — estratégia predominantemente **network-first**.

### Versionamento (crítico)

Três números costumam coexistir — **mantenha alinhados ao publicar**:

- `ZELO_VERSION` em `zelo-assistente.php` (plugin).
- `window.ZELO_APP_BUILD` + `?v=` em `index.html` + lista em `sw.js` + `CACHE_NAME` em `sw.js`.

Desalinhamento = usuários presos em cache antigo.

## Endpoints REST principais

| Método | Rota | Auth | Notas |
|--------|------|------|-------|
| GET | `/locais` | Público | Filtro lat/lng/radius |
| GET | `/evento` | Público | Dados do evento, avisos |
| GET | `/categorias` | Público | Categorias dinâmicas |
| GET | `/indoor-map` | Público | Mapa indoor |
| GET | `/clima` | Público | Previsão do tempo (Open-Meteo, coords do evento) |
| POST | `/auth/login` | Público | Retorna nonce WP |
| POST | `/auth/register` | Público | Rate limit por IP |
| GET | `/auth/verify-email` | Público | Token de verificação |
| GET | `/ops/voluntarios` | Auth ou público* | *Ver filtro temporário |
| POST | `/ops/checkin`, `/checkout`, `/reallocate` | Capabilities | `reallocate` exige supervisão na linha |
| POST | `/ops/schedule` | `zelo_edit_schedule` + escopo governança | Merge por `day`+`shift` |
| GET | `/ops/export` | `zelo_manage_ops` | PDF/CSV escala |
| * | `/ops/swap-requests` | Capabilities | Substituições |

## O que NÃO fazer sem pedido explícito

- Remover o filtro temporário `zelo_ops_voluntarios_public_read` sem plano de segurança.
- Refatorar `app-v5.js` em módulos ou framework.
- Introduzir dependências npm/build no frontend (hoje é estático).
- Commitar segredos (API keys Google, senhas).
- Force push em `main`.

## Idioma

Documentação de governança e comunicação com o usuário: **português (PT-BR)**. O PRD em `docs/` está em inglês (referência de produto).
