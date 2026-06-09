# Issue #15 — Carrossel de novidades na home (B9)

> **Issue:** [esvianna/ZELO#15](https://github.com/esvianna/ZELO/issues/15)  
> **Status:** **Done** — smoke OK (2026-06-04)  
> **Relacionadas:** [#26](https://github.com/esvianna/ZELO/issues/26) novidades (logados), [#18](https://github.com/esvianna/ZELO/issues/18) branding, [#14](https://github.com/esvianna/ZELO/issues/14) programação (**descartada** ADR-029)  
> **Versões:** plugin **2.13.3** + PWA **126**  
> **Última atualização:** 2026-06-04

---

## 1. Objetivo

Carrossel horizontal de **novidades** na **home**, para utilizadores **logados**, alimentado por **posts WP** (#26) com flag de destaque. Swipe mobile, fallback offline e card «Novidades» quando não houver slides.

---

## 2. Decisões fechadas (2026-06-04)

| # | Pergunta | Decisão |
|---|----------|---------|
| **D1** | Público-alvo | **Só logados** |
| **D2** | Fonte | **Posts WP** — meta `_zelo_carousel` + imagem destacada |
| **D3** | Relação com banner | **Mantém** `#home-event-banner` (público); carrossel **acima** na secção novidades (`#home-news-card`) |
| **D5** | Voluntários ops | **Visível** (não oculto como welcome) |

Ver **ADR-030** em `DECISIONS.md`.

---

## 3. Implementação

### Backend (`inc/zelo-news.php`)

- Meta `ZELO_NEWS_META_CAROUSEL` (`_zelo_carousel`).
- Checkbox admin «Destaque no carrossel da home» (requer «Publicar na PWA» + imagem destacada).
- `zelo_news_query( carousel_only => true )` — filtra meta carrossel + `_thumbnail_id` EXISTS.
- `GET /zelo/v1/news?carousel_only=1` — máx. 8 itens, página 1; cache transiente inclui flag.

### Frontend (PWA 126)

- `API.getNews({ carousel_only: true })` + snapshot `zelo_news_carousel_v1_{userId}`.
- `loadNewsCarousel()` no login e `init()`.
- `renderHomeNewsCard()` — carrossel scroll-snap se houver posts com imagem; senão card fallback.
- CSS `.home-news-carousel-*`; i18n `news_carousel_view_all`.
- Logout limpa snapshot carrossel.

---

## 4. Como testar

1. **Admin WP:** criar/editar post → «Publicar na PWA» + «Destaque no carrossel da home» + **imagem destacada** → publicar.
2. **Logado:** home mostra carrossel com swipe; dots; toque abre `blog-post`; «Ver todas as novidades» → lista.
3. **Anónimo:** sem carrossel (`#home-news-card` oculto).
4. **Sem posts carrossel:** card «Novidades» fallback (comportamento #26).
5. **Offline:** após visita online logada, snapshot carrossel; badge stale se rede falhar.
6. Regressão: banner evento, grelha serviços, bottom nav.

Ver `TESTING.md` §4 item **2ac**.

---

## 5. Opções descartadas (histórico da análise)

| Opção | Motivo descarte |
|-------|-----------------|
| Slides em `zelo_event_data` (admin) | Decisão D2 = posts WP |
| Endpoint público `/highlights` | D1 = só logados |
| JSON estático no repo | Não administrável |
| Substituir banner evento | D3 = manter banner público |

---

## 6. Riscos mitigados

| Risco | Mitigação |
|-------|-----------|
| Home sobrecarregada | Máx. 8 slides; scroll-snap nativo (zero deps) |
| Sem imagem | Query exige featured image; admin avisa na descrição |
| Offline | Snapshot dedicado + stale badge |
| Duplicar banner | Canais distintos: banner **público** vs carrossel **logado** |
