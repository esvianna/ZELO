# Issue #14 — Área Programação na home visitante (B5 PRD)

> **Issue:** [esvianna/ZELO#14](https://github.com/esvianna/ZELO/issues/14)  
> **Status:** **Descartado** (ADR-029, 2026-06-04) — programação no app JW Library e impresso no evento; fora do ZELO.  
> **Relacionadas:** ROADMAP **B5**, [#18](https://github.com/esvianna/ZELO/issues/18) branding, [#26](https://github.com/esvianna/ZELO/issues/26) novidades (voluntários logados — **não** é programação do evento)  
> **Última atualização:** 2026-06-04

---

## 1. Objetivo

Implementar botão/área **«Programação»** na **home do visitante**, conforme PRD — acesso rápido ao **programa oficial do congresso/evento** (sessões, horários, credenciamento), **sem login**, mobile-first.

**Não confundir com:** «Escala de voluntários» (`/ops/voluntarios`) — isso é operação interna (#1, ADR-018), não programação pública do evento.

---

## 2. Pedido original (issue)

> Implementar botão/área «Programação» na home do visitante, conforme PRD.

**Critérios de aceite:**

- [ ] Destino definido (URL externa, secção in-app ou PDF).
- [ ] Acessível sem login; mobile-first.
- [ ] i18n e branding do evento respeitados.

**Nota:** conteúdo e fonte da programação aprovados antes de **Ready**.

**PRD** (`docs/product_requirements_document.md` §10): criar botão «Programação» e definir colocação — perto do nome do evento, bottom nav ou card dedicado.

---

## 3. Contexto no código hoje

### 3.1 Home visitante (PWA)

| Peça | Situação |
|------|----------|
| Banner evento | `#home-event-banner` → navega `view-evento` (Info) |
| Grelha 2×2 | Hospitais, Cultura, Compras, Lazer |
| Secção ops | Escala voluntários, Mapa evento, Novidades (#26, **só logados**) |
| Mapa localização | Card → `view-mapa` |
| Bottom nav 5 itens | Início · Mapa · S.O.S · **Info** (`evento`) · Perfil/Operação (ADR-012) |
| **Programação** | **Inexistente** — sem card, botão ou rota |

### 3.2 View Info (`renderEventInfo` / `GET /evento`)

- Hero, localização, transporte, Wi‑Fi, credenciamento, segurança.
- Credenciamento mostra `cred_hours` ou fallback **«Consulte a programação»** — texto placeholder, **sem link nem dados de programa**.
- `contatos.site` existe na API (`zelo_event_data.site`) mas **não** há botão «Programação» na home nem secção dedicada na view Info.

### 3.3 Admin WordPress

`admin-settings.php` → `zelo_event_data` (`wp_options`):

| Campo existente | Uso potencial |
|-----------------|---------------|
| `site` | Site oficial — **pode** albergar programação externa |
| `foto`, `logo` | Branding |
| `home_notice` + `link` | Aviso genérico — **não** substituto semântico de Programação |
| `cred_hours` | Texto livre — horário credenciamento, não programa completo |

**Não existe:** `program_url`, `program_pdf`, bloco HTML de sessões, REST `/programacao`.

### 3.4 Protótipo Stitch

`docs/stitch_zelo/home_zelo_refinada_pwa/` — banner menciona «Programação completa e guias» + botão «Ver Detalhes»; **não implementado** na PWA actual.

### 3.5 Decisões históricas relevantes

- **ADR-016:** «Grade/headcount/**programação** permanecem fora de escopo» refere-se à **escala ops**, não a esta issue visitante.
- **#26:** Posts/notícias para **voluntários logados** — canal diferente.

---

## 4. Gap vs critérios de aceite

| Critério | Hoje | Gap |
|----------|------|-----|
| Destino definido | Nenhum | Escolher URL / PDF / view in-app |
| Sem login | Info e home já públicos | Nova rota/card deve ser pública |
| Mobile-first | Padrão existente (`action-card`) | Wireframe + CSS |
| i18n | Chaves inexistentes para «Programação» | `i18n.js` pt/en/es |
| Branding | Cores/cards já seguem evento | Ícone/card alinhado ao PRD |

---

## 5. Decisões de produto (abertas)

| # | Pergunta | Opções | Esforço |
|---|----------|--------|---------|
| **D1** | **Destino** | **A)** URL externa (site/PDF hospedado) · **B)** PDF same-origin (upload WP → URL) · **C)** View in-app com HTML admin · **D)** View in-app com lista de sessões (CRUD) | A ≈ 0,5 sessão · D ≈ 2–3 sessões |
| **D2** | **Colocação** | **A)** Card na grelha visitante (2×2 → 2×3 ou linha extra) · **B)** CTA no banner evento · **C)** Secção na view **Info** + atalho home · **D)** 6.º item nav — **rejeitado** por ADR-012 | A ou B recomendado |
| **D3** | **Fonte de conteúdo** | Equipa publica no site oficial vs edita no admin ZELO vs PDF estático por evento | D1 depende disto |
| **D4** | **Idioma do conteúdo** | Só PT · PDF/link por idioma · sessões com título pt/en/es | MVP: PT + rótulos i18n |
| **D5** | **Offline** | Link externo falha offline · PDF cache SW se same-origin · HTML snapshot `zelo_evento` | A: mensagem amigável |
| **D6** | **Reutilizar `site`?** | Campo genérico vs novo `program_url` dedicado | Campo dedicado evita ambiguidade |

---

## 6. Opções técnicas (comparativo)

### Opção A — Link externo (MVP recomendado se conteúdo já no site)

1. Admin: `program_url` (+ opcional `program_label`) em `zelo_event_data`.
2. `GET /evento` expõe `programacao: { active, url, open_in: '_blank' }`.
3. Home: `action-card` «Programação» (visitante, acima ou dentro da grelha serviços).
4. Click → `window.open(url)` ou in-app browser.

**Prós:** Rápido; equipa do evento mantém programa no site habitual.  
**Contras:** Sai da PWA; offline limitado.

### Opção B — PDF

1. Admin: URL do PDF (media library WP).
2. PWA: view simples com `<iframe>` / link download + prefetch SW (same-origin).

**Prós:** Documento oficial fixo; funciona offline parcial.  
**Contras:** UX PDF mobile variável; sem pesquisa.

### Opção C — Secção HTML na view Info

1. Admin: textarea/WYSIWYG `program_html` (sanitized `wp_kses_post`).
2. View `evento` ou nova `view-programacao` renderiza HTML.
3. Home: card aponta para `router.navigate('programacao')`.

**Prós:** Tudo in-app; cache com `zelo_evento`.  
**Contras:** Edição manual; sem estrutura de sessões.

### Opção D — CRUD sessões (épico)

1. `wp_options` ou CPT `zelo_session` com dia, hora, título, local, track.
2. `GET /programacao` público; view timeline na PWA.

**Prós:** Melhor UX; filtros por dia.  
**Contras:** Maior scope; overlap com CMS externo do evento.

---

## 7. Proposta técnica MVP (Opção A + atalho Info)

| Camada | Entrega |
|--------|---------|
| **Plugin** | Campos admin `program_active`, `program_url`, `program_pdf_url` (opcional); expor em `zelo_get_evento()` |
| **PWA** | Card home + i18n; `renderEventInfo()` secção «Programação» se URL/PDF configurado |
| **Router** | Opcional `view-programacao` só se Opção B/C |
| **SW** | Prefetch PDF se same-origin (padrão ADR-024/025) |
| **TESTING.md** | Item visitante: card visível; abre destino; anónimo OK |

**Estimativa:** **0,5–1 sessão** (Opção A); **1–1,5** (B/C); **2–3** (D).

---

## 8. Wireframe sugerido (Opção A — card home)

```
┌─ SERVIÇOS ──────────────────────────┐
│ [Hospitais] [Cultura]               │
│ [Compras]   [Lazer]                 │
│ [📅 Programação]  ← novo card       │
└─────────────────────────────────────┘
```

Alternativa: botão secundário no banner evento («Ver programação») além do card.

---

## 9. Permissões e público

| Surface | Auth |
|---------|------|
| Card home Programação | **Público** (como mapa/lazer) |
| `GET /evento` (campos novos) | **Público** (já existente) |
| Admin settings | `manage_options` |

Voluntários logados veem o **mesmo** card visitante; escala ops permanece separada.

---

## 10. Riscos

| Risco | Mitigação |
|-------|-----------|
| URL externa incorrecta/offline | Validar URL no admin; toast i18n `program_unavailable` |
| Confusão com «Escala voluntários» | Rótulo claro «Programação do evento» vs `nav_volunteer_schedule` |
| Grelha home cheia | Card em nova linha ou substituir card menos usado (decisão produto) |
| Conteúdo desactualizado | Link para fonte canónica (site oficial); aviso stale se snapshot |

---

## 11. Recomendação (para aprovação)

| Tema | Proposta |
|------|----------|
| **Prioridade** | Low no Project — UX visitante; útil se houver URL/PDF oficial pronto |
| **MVP** | **Opção A:** `program_url` no admin + card home + link na view Info; i18n; abrir em nova aba |
| **Não fazer no MVP** | CRUD sessões (Opção D), 6.º item nav, integração escala ops |
| **Próximo passo** | Confirmar **D1** (URL vs PDF vs in-app) e **D2** (card vs banner); obter URL/PDF oficial do evento → **Ready** |

---

## 12. Cenário descarte (como #8/#9/#10)

Descartar se:

- Programação vive **só** no site oficial e o campo `site` + view Info bastam (link manual no `home_notice` ou credenciamento).
- Visitantes não usam a PWA para programa — só mapa/locais.

**Alternativa mínima sem issue:** expor `contatos.site` como botão «Site oficial» na view Info (hoje subutilizado).

---

## 13. Como testar (rascunho — após implementação)

1. Admin: activar URL programação; guardar.
2. Home anónima: card «Programação» visível; toque abre destino.
3. Trocar idioma PWA: rótulo traduzido; conteúdo PT (MVP).
4. Offline: comportamento definido (mensagem ou PDF cache).
5. Regressão: grelha serviços, ops, bottom nav inalterados.

---

## 14. Comparação com issues recentes

| | **#14 Programação** | **#15 Carrossel** |
|---|---------------------|-------------------|
| Público | Visitante anónimo | Visitante |
| Código hoje | Placeholder texto only | Inexistente |
| MVP típico | 0,5–1 sessão (link) | 1–2 sessões |
| Blocker | Fonte de conteúdo (D1/D3) | Fonte imagens + performance |

**Próxima no backlog após #14:** [#15](https://github.com/esvianna/ZELO/issues/15) carrossel ou infra [#22](https://github.com/esvianna/ZELO/issues/22).
