# Issue #15 — Carrossel de destaques na home (B9)

> **Issue:** [esvianna/ZELO#15](https://github.com/esvianna/ZELO/issues/15)  
> **Status:** Backlog — **análise / planejamento** (aguarda decisões marcadas abaixo)  
> **Relacionadas:** ADR-012 (carrossel → Fase 2), [#26](https://github.com/esvianna/ZELO/issues/26) novidades (logados), [#18](https://github.com/esvianna/ZELO/issues/18) branding, [#14](https://github.com/esvianna/ZELO/issues/14) programação (**descartada** ADR-029)  
> **Última atualização:** 2026-06-04

---

## 1. Objetivo

Carrossel de **destaques** na **home visitante** (Fase 2 PRD) — slides com imagem/título/link, administrável ou estático, com swipe mobile e fallback offline.

---

## 2. Pedido original (issue)

> Carrossel de destaques na home (Fase 2 PRD), administrável ou estático conforme decisão.

**Critérios de aceite:**

- [ ] Fonte de dados definida (options, REST, estático).
- [ ] Performance e acessibilidade (swipe, contraste).
- [ ] Fallback quando offline.

**Notas:** Fase 2; prioridade após ops do evento.

---

## 3. Contexto no código hoje

### 3.1 Home — blocos existentes (sem carrossel)

| Ordem (visitante) | Peça | Função |
|-------------------|------|--------|
| 1 | `#home-volunteer-dashboard` | Painel ops (**só** `view_ops`) |
| 2 | `#home-news-card` | Atalho blog (**logados**, oculto se ops) |
| 3 | `#home-weather-widget` | Clima |
| 4 | Search | Locais/serviços |
| 5 | `#home-welcome` | «Bem-vindo» (oculto se ops) |
| 6 | `#home-event-banner` | **1 slide** — foto evento, nome, local → Info |
| 7 | `#home-notice-container` | Faixa aviso (warning/critical → avisos) |
| 8 | Grelha 2×2 | Hospitais, Cultura, Compras, Lazer |
| 9 | Ops quick actions | Escala, mapa evento, novidades (logados) |
| 10 | Map card | Mapa geral |

**Carrossel:** **inexistente** — nenhum swipe, dots, nem lista de slides.

### 3.2 ADR-012 (2026-05-28)

> «Carrossel de destaques e inbox servidor → **Fase 2** (`ROADMAP.md`).»

MVP entregue: banner evento **único** + nav 5 itens + widget tempo + hub avisos.

### 3.3 Fontes de conteúdo candidatas

| Fonte | Público | Situação |
|-------|---------|----------|
| `zelo_event_data.foto` + banner JS | Todos | **Já usado** — 1 destaque |
| `home_notice` | Todos | Faixa texto, não carrossel |
| `GET /news` (#26) | **Só logados** | Posts com `featured_image`; sem meta «destaque carrossel» |
| CPT/locais | Todos | Não desenhado para promo home |
| Slides em `wp_options` | Configurável | **Não existe** |

### 3.4 Protótipo Stitch

`home_zelo_refinada_pwa` — banner «Destaque / Evento Oficial» com CTA; **um** card hero, não carrossel multi-slide.

### 3.5 Stack frontend

- Vanilla JS — **sem** Swiper/Glide/npm.
- Padrão offline: snapshot `localStorage` + badge stale (evento, clima, news, indoor — ADR-024/025).
- CSS: `.event-banner-*` pronto para hero; faltaria `.home-carousel-*`.

---

## 4. Gap vs critérios de aceite

| Critério | Hoje | Gap |
|----------|------|-----|
| Fonte de dados | Banner + evento API | Modelo multi-slide + admin ou REST |
| Performance/a11y | Banner estático OK | Touch swipe, focus, contraste, lazy load imagens |
| Offline | `zelo_evento` snapshot | Slides no mesmo snapshot ou endpoint público cacheável |

---

## 5. Decisões de produto (abertas)

| # | Pergunta | Opções | Notas |
|---|----------|--------|-------|
| **D1** | **Público-alvo** | **A)** Todos visitantes · **B)** Só logados · **C)** Substituir banner actual | #26 restringe posts a logados |
| **D2** | **Fonte** | **A)** Slides em `zelo_event_data` (admin) · **B)** Meta `_zelo_carousel` em posts + API pública · **C)** JSON estático no repo · **D)** Reutilizar só `foto` + links manuais | A mais simples para visitante anónimo |
| **D3** | **Relação com banner** | **A)** Carrossel **substitui** `#home-event-banner` · **B)** Carrossel **acima** do banner · **C)** Carrossel só quando >1 slide | Evitar duplicação visual |
| **D4** | **Conteúdo típico** | Promo evento, mapa indoor, novidades, link externo | Definir com equipa do evento |
| **D5** | **Voluntários ops** | Ocultar carrossel (como welcome) ou manter | Home ops já densa |
| **D6** | **i18n slides** | PT only · imagem por idioma · texto traduzido no admin | MVP: PT + rótulos UI i18n |

---

## 6. Opções técnicas

### Opção A — Slides no admin evento (MVP recomendado)

```json
// zelo_event_data.carousel_slides[]
{ "image_url": "...", "title": "...", "subtitle": "", "link": "evento|mapa-evento|https://...", "active": true }
```

- `GET /evento` inclui `carousel_slides[]` (filtrar active).
- PWA: componente scroll-snap horizontal + dots; substitui ou envolve banner.
- Offline: já cacheia `zelo_evento`.
- Admin: repeater na página «Dados do Evento».

**Esforço:** ~1–1,5 sessões (plugin admin + PWA CSS/JS + i18n + TESTING).

### Opção B — Posts WP «destaque carrossel»

- Meta `_zelo_in_carousel` + `_zelo_carousel_order`.
- **Problema:** `/news` exige login (ADR-023) — visitante anónimo **não vê**.
- Exigiria `GET /highlights` **público** (só posts flagged) ou mudança de política #26.

**Esforço:** ~1,5–2 sessões + decisão auth.

### Opção C — Carrossel estático (build)

- Array em `app-v5.js` ou JSON em `assets/`.
- Deploy para alterar — **não** administrável.

**Esforço:** ~0,5 sessão — frágil para evento recorrente.

### Opção D — Sem carrossel (descarte)

- Banner evento + `home_notice` + (logados) card novidades **bastam**.
- Alinhado a descartes recentes (#8, #9, #10, #14).

---

## 7. Proposta UX (se implementar — Opção A)

```
┌─ Carrossel (scroll-snap) ─────────────┐
│  [slide 1: mapa evento]  [slide 2]   │  ● ○ ○
└───────────────────────────────────────┘
```

- Gestos: swipe horizontal; botões prev/next opcionais (a11y).
- Tap slide → `router.navigate` ou URL externa.
- Lazy-load imagens; `prefetch` 1.º slide no SW (same-origin).
- Máx. slides sugerido: **5** (performance).

**Colocação:** substituir `#home-event-banner` quando `carousel_slides.length > 0`; fallback banner actual se vazio.

---

## 8. Permissões

| Surface | Auth |
|---------|------|
| Slides visitante | **Público** (via `/evento`) |
| Admin CRUD slides | `manage_options` |
| Posts #26 no carrossel | Só se D2=B + endpoint público |

---

## 9. Riscos

| Risco | Mitigação |
|-------|-----------|
| Home sobrecarregada | Máx. slides; ocultar para ops (D5) |
| Imagens pesadas CDN externo | Same-origin ou limite tamanho; placeholder offline |
| Duplicar banner evento | D3 — substituir, não empilhar |
| Conflito #26 | Canais distintos: carrossel **público** vs blog **logado** |
| Sem lib carousel | CSS scroll-snap nativo (zero dependência) |

---

## 10. Recomendação (para aprovação)

| Tema | Proposta |
|------|----------|
| **Prioridade** | Low (Project) — polish UX; **não** bloqueia operação |
| **Valor actual** | Banner evento + aviso + serviços já comunicam o essencial |
| **Se implementar** | Opção **A** (slides admin em `zelo_event_data`); substituir banner; público; scroll-snap |
| **Se descartar** | ADR curto; manter banner único (ADR-012 MVP cumprido) |
| **Próximo passo** | Confirmar se há **conteúdo** para 2+ slides no evento real; senão **descartar** |

---

## 11. Cenário descarte (como #14 / #9)

Descartar se:

- Um único banner com foto do evento chega.
- Destaques promocionais vão para `home_notice` ou novidades (#26, logados).
- Equipa não vai manter slides atualizados no admin.

**Alternativa mínima:** enriquecer banner existente (data do evento, 2.º CTA) — issue **#18** branding.

---

## 12. Sub-tarefas (se Ready)

1. Admin repeater slides + sanitização URLs.
2. API `/evento` + migração default vazia.
3. PWA carousel + fallback banner.
4. i18n + TESTING.md (visitante anónimo, offline, swipe).
5. SW prefetch imagens same-origin (opcional).

---

## 13. Como testar (rascunho)

1. Admin: 3 slides activos com links internos/externos.
2. Home anónima: carrossel visível; swipe; dot activo.
3. Offline após 1.ª visita: slides do cache `zelo_evento`; badge stale se aplicável.
4. Voluntário ops: comportamento D5 (oculto ou visível).
5. Regressão: search, grelha, bottom nav, banner fallback com 0 slides.

---

## 14. Comparação backlog UX

| | **#15 Carrossel** | **#18 Branding** | **#17 Emergência** |
|---|-------------------|------------------|---------------------|
| Tipo | Conteúdo promocional | Visual evento | Hierarquia S.O.S |
| Existe parcial | Banner 1 slide | `foto`, logo API | CSS cards existente |
| Esforço MVP | 1–1,5 sess | 0,5–1 sess | 0,5 sess (CSS) |
| Depende conteúdo | Sim (slides) | Cores/logo fornecidos | Pouco |

**Backlog restante UX/infra:** [#17](https://github.com/esvianna/ZELO/issues/17), [#18](https://github.com/esvianna/ZELO/issues/18), [#22](https://github.com/esvianna/ZELO/issues/22) (Medium).
