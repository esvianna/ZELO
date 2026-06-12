# CHANGELOG.md — Repositório ZELO

Histórico em nível de **projeto** (backend + frontend + docs). Detalhes finos do plugin WordPress estão em `backend-plugin/zelo-assistente/CHANGELOG.md`.

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).

---

## [Unreleased]

### Adicionado — atalhos para instruções Imprensa e Autoridades (PWA 140, #35)

| Versão | Entrega |
|--------|---------|
| PWA **140** | Link «Ver instruções completas» no card Info Evento; botão rápido Home (Operação de Voluntários); resolve post Novidades por slug `imprensa-autoridades`; i18n pt/en/es |

### Corrigido — toggle vista escala no grid mobile (PWA 139, #33)

| Versão | Entrega |
|--------|---------|
| PWA **139** | `.ops-view-toggle` com `display: flex` + `width: 100%` para respeitar `grid-column: 1 / -1` na toolbar |

### Adicionado — informações do evento configuráveis (PWA 138 + plugin 2.13.8, #34)

| Versão | Entrega |
|--------|---------|
| PWA **138** | Secções Como chegar, Wi‑Fi e Credenciamento opcionais; card Imprensa/autoridades (Ligar + WhatsApp) acima de Segurança; i18n view Info |
| Plugin **2.13.8** | Toggles admin + `press_contact` em `GET /evento`; compatibilidade com eventos já configurados |

### Ajustado — layout superior da escala operacional (PWA 137, #33)

| Versão | Entrega |
|--------|---------|
| PWA **137** | Filtro «Minhas designações»: «Todos os status»; selects/inputs unificados (`.ops-filter-control`); grid mobile da toolbar; botões «Montar escala» e «Exportar PDF» com mesma altura numa linha |
| Validação | **Done** no Project (#33) |

### Alterado — governança GitHub Project

| Área | Entrega |
|------|---------|
| Docs / regras | Mover cards via CLI (`gh project item-edit`) obrigatório para agentes; sync **Done** → `PROJECT_STATUS` + `CHANGELOG`; IDs do Project 3 em `AGENTS.md`, `docs/GITHUB-WORKFLOW.md`, `.cursor/rules/` |

### Adicionado — adicionar/editar linha na escala sem editor completo (PWA 136, #32)

| Versão | Entrega |
|--------|---------|
| PWA **136** | «+ Adicionar» no card do turno; ícone/botão Editar por linha; modal formulário (voluntário + horário); `POST /ops/schedule`; aviso reconfirmação; paridade vista Lista |

### Ajustado — visualização pedidos de substituição (PWA 132, #29)

| Versão | Entrega |
|--------|---------|
| PWA **132** | Painel swap, hub avisos e histórico ops com nome, dia/turno/local, motivo e data — sem IDs técnicos na UI |
| Validação | **Done** no Project (#29) |

### Ajustado — designações: home só ações pendentes; escala com filtro e arquivo (PWA 133, #30)

| Versão | Entrega |
|--------|---------|
| PWA **133** | Home «Minhas designações» lista só itens com ação (confirmar, check-in/out); empty state «Tudo em dia»; escala com filtro por estado e seção colapsável «Recusadas e encerradas»; badge presença oculto se recusado; «Aguardando dia do evento» para aceitas futuras |
| Validação | **Done** no Project (#30) |

### Corrigido — badge presença em dia passado (PWA 135)

| Versão | Entrega |
|--------|---------|
| PWA **135** | «Aguardando dia do evento» só em designações **futuras**; dia passado com check-in pendente volta a mostrar «Pendente» |

### Adicionado — excluir linha recusada da escala (PWA 134 + plugin 2.13.7, #31)

| Versão | Entrega |
|--------|---------|
| PWA **134** | Botão remover (ícone/btn) em linhas `declined` para gestor/responsável do turno; modal de confirmação; reutiliza `POST /ops/schedule`; aviso se swap pendente; histórico com contagem removida |
| Plugin **2.13.7** | Cancela swaps `pending` ao remover designação da escala |
| Validação | **Done** no Project (#31) |

### Ajustado — histórico escala ops (PWA 131)

| Versão | Entrega |
|--------|---------|
| PWA **131** | «Últimas alterações» colapsável (`<details>`); textos legíveis por tipo (`schedule_patch`, realocação, substituição) |

### Corrigido — avatar perfil (PWA 130 + plugin 2.13.6)

| Versão | Entrega |
|--------|---------|
| PWA **130** | Pré-visualização imediata; feedback junto ao avatar; cache-bust após upload; nonce via `persistAuthUser` |
| Plugin **2.13.6** | URL avatar: fallback medium/full se thumbnail em falta |

### Ajustado — config API escopo mínimo (#21, ADR-034)

| Versão | Entrega |
|--------|---------|
| PWA **129** | Login usa só `API.baseUrl`; comentário same-origin em `api-v5.js`; `TESTING.md` + `DEPLOY-ZELO-PWA.md` |

### Decisão — testes automatizados descartados (#20, ADR-033)

- Issue [#20](https://github.com/esvianna/ZELO/issues/20) fechada; validação manual via `TESTING.md` permanece fonte de verdade.

### Adicionado — rate limit login REST (#22)

| Versão | Entrega |
|--------|---------|
| Plugin **2.13.5** | `inc/rate-limit.php`; login 30/15 min IP + 10/15 min user; register refactor; filtro `zelo_rate_limit_enabled` |

### Decisão — Branding splash/home descartado (#18, ADR-031)

- Issue [#18](https://github.com/esvianna/ZELO/issues/18) fechada; banner evento + logo/foto admin bastam.

### Ajustado — emergência (#17)

| Versão | Entrega |
|--------|---------|
| Plugin **2.13.4** + PWA **128** | 3 serviços públicos no admin (PT/EN/ES); discagem directa `tel:`; telefone interno opcional (checkbox off); home simétrica 3 colunas |

### Adicionado — hierarquia visual emergência (#17)

| Versão | Entrega |
|--------|---------|
| PWA **127** | Card emergência full-width (PRD); view emergência com hero + telefone destaque; destaque lista/pesquisa categoria `emergencia`; a11y S.O.S. |

### Adicionado — carrossel novidades home (#15, ADR-030)

| Versão | Entrega |
|--------|---------|
| Plugin **2.13.3** + PWA **126** | Meta `_zelo_carousel`; `GET /news?carousel_only=1`; carrossel scroll-snap na home (logados); snapshot offline; fallback card novidades |

### Decisão — Programação visitante descartada (#14, ADR-029)

- Issue [#14](https://github.com/esvianna/ZELO/issues/14) fechada; **Done** no Project 3: programa no **JW Library** e **impresso** no evento; sem área Programação na PWA.

### Decisão — motor notificações descartado (#9 + #16, ADR-028)

- Issues [#9](https://github.com/esvianna/ZELO/issues/9) e [#16](https://github.com/esvianna/ZELO/issues/16) fechadas; **Done** no Project 3: hub sino + cron e-mail + `localStorage` para «lido»; sem inbox REST.

### Decisão — cobertura posto/idioma descartada (#10, ADR-027)

- Issue [#10](https://github.com/esvianna/ZELO/issues/10) fechada; **Done** no Project 3: escala PWA (filtros local/idioma #11), export PDF e admin «Cobertura escala» (presença) cobrem a operação; sem painel matriz dedicado.

### Decisão — Web Push descartado (#8, ADR-026)

- Issue [#8](https://github.com/esvianna/ZELO/issues/8) fechada; **Done** no Project 3: voluntários veem avisos **in-app** na PWA (Novidades #26); sem VAPID/subscribe real. Stub `POST /ops/push/subscribe` (501) mantido.

### Adicionado — blog/notificações (#26)

| Versão | Entrega |
|--------|---------|
| Plugin **2.13.0** + PWA **116** | Posts WP na PWA: meta box, `/news`, hub avisos, views blog, card home + menu |
| Plugin **2.13.1** + PWA **117** | Fix título com entidades HTML; vídeos responsivos no detalhe; cache `/news` ao gravar post |
| Plugin **2.13.2** + PWA **118** | Título/resumo: decode completo + hífen ASCII; cache API v2 + snapshot cliente |
| PWA **119** | Card «Novidades» na secção Operação de Voluntários (home) |
| PWA **120** | Rótulo do card: «Novidades» (sem «/Blog») |
| PWA **121** | Home «Minhas designações»: texto explicativo fora do flex; botões empilhados no mobile |
| PWA **123** | ADR-025: detalhe novidades offline (`zelo_news_item_v1_*` + prefetch ao listar) |
| PWA **124** | Mapa evento: header + bottom nav visíveis no Diagrama (mobile); fim do fullscreen automático |
| PWA **125** | Diagrama: viewport do mapa em largura total (edge-to-edge) |

**Testes:** `TESTING.md` §4 — item **2aa**; §12 **O6**.

### Adicionado — mapa indoor (#28, **In review**)

| Versão | Entrega |
|--------|---------|
| Plugin **2.12.0** + PWA **109** | Aba «Mapa evento» (CRUD, editor pinos, direções pt/en/es), `GET /indoor-map`, fluxo Orientar + Diagrama; dept. 8–35 ocultos na API |
| PWA **110–111** | Mobile: fit-to-screen, pinch-to-zoom, Diagrama em tela cheia, botões Mapa completo / Ir ao destino |
| PWA **113–114** | Combobox «Para onde?» (filtro sem perder foco iOS); rótulo **Pavim.** na lista de destinos |
| Plugin **2.12.3** + PWA **115** | Balcão 1 (azul) vs Balcão 2 (teal), número no pino, legenda no diagrama (`booth_slot`) |
| PWA **122** | ADR-024: snapshot `zelo_indoor_map` + prefetch imagem; mapa do evento offline após 1.ª visita online |
| PWA **124** | Diagrama mobile: header, «Voltar» e bottom nav sempre visíveis (sem tela cheia) |

### Corrigido — mapa indoor (#28)

| Versão | Fix |
|--------|-----|
| PWA **112** | Diagrama em branco no iPhone (viewport h=0 → `scale(0)`); ResizeObserver + retry layout |
| PWA **113** | Combobox — só uma letra por re-render total do mapa |
| Plugin **2.12.1** | Admin: direções por destino gravam (POST indexado por `id` do local) |
| Plugin **2.12.2** | Admin: «Salvar abas» deixava tela branca (redirect após output); aba via POST + `user_meta` |

**Testes:** `TESTING.md` §4 — itens **2x**, **2y**, **2z**.

### Adicionado
- PWA build **108**: ícone olho nos campos senha (login, cadastro, perfil) — ZELO#24; formulário de perfil (nome, e-mail, telefone, senha, idiomas, foto) — ZELO#25.
- **Docs:** fluxo GitHub — agentes param em **In review**; Done só após validação humana.
- Plugin **2.11.9**: `PATCH /auth/profile` ampliado + `POST /auth/profile/avatar`.
- PWA build **107**: persistência da última view após F5 (`sessionStorage` + hash `#viewId`; guardas auth escala/perfil; ZELO#27).
- **Infra (ZELO#19):** `.gitignore` na raiz — PHP/Composer, IDE, OS, segredos; exceções PWA e FPDF em `README.md`.
- **Docs (ZELO#23):** `PROJECT_STATUS.md` e `ROADMAP.md` alinhados com plugin **2.11.7**, PWA **106**, issues Done/Backlog do Project 3 (ADR-020).
- PWA build **106**: filtro por **responsável do turno** na escala (select a partir de `shift_contacts`; combinável com dia/turno/local/nome/idioma) — ZELO#11 (fechada).
- Backlog [#27](https://github.com/esvianna/ZELO/issues/27): persistir última view da PWA após refresh.
- Plugin **2.11.4** + PWA **105**: links WhatsApp no nome dos voluntários e do responsável do turno (só com telefone cadastrado) — ZELO#12 (fechada).

### Adicionado
- Plugin **2.11.7**: fix export PDF FPDF margens (PHP 8.2); rate limit só após export OK.
- Plugin **2.11.6**: PDF export — governança compacta (lado a lado); quebra de página por dia.
- Plugin **2.11.5**: export PDF agrupado dia → turno → faixa → voluntários; responsável do turno no cabeçalho (ZELO#7).

### Corrigido
- Plugin **2.11.3**: payload `/ops/voluntarios` — voluntário vê status de compromisso dos colegas na escala da equipa (alinhado ao responsável).

### Adicionado
- PWA build **104**: ícones da escala por turno mais compactos (32px); nome alinhado ao centro vertical dos botões.
- PWA build **103**: escala por turno — ações (confirmar, check-in/out, realocar, swap) em **ícones** na linha do nome, para voluntário e responsável; `aria-label`/`title` i18n; vista lista inalterada.

### Adicionado
- Governança de backlog: GitHub [Project ZELO](https://github.com/users/esvianna/projects/3), issues em `esvianna/ZELO`, gate **Ready** antes de codificar; `docs/GITHUB-WORKFLOW.md`, ADR-020, regra Cursor `zelo-github-backlog.mdc`, template de issue.
- Migração de tarefas: SITE-NOVO-VTIS#1/#2 → ZELO#1/#2 (issues antigas fechadas com referência).

### Adicionado
- PWA build **102**: escala da equipa em vista **Por turno** (dia → turno → faixa → voluntários); toggle Lista; «Montar este turno» no card; Minhas designações no mesmo layout.

### Corrigido
- Plugin **2.11.2**: `schedule_changed` guarda `prior_commitment` com data/utilizador do aceite anterior (auditoria).
- Plugin **2.11.1**: ao guardar turno na PWA, compromissos inalterados são preservados; só linhas novas/alteradas exigem reconfirmação (`pending_reason: schedule_changed`).
- PWA build **101**: aviso «A sua escala mudou — confirme»; e-mail no cron (`schedule_changed`).

### Adicionado
- Plugin **2.11.0**: `POST /ops/schedule` (CRUD escopado por governança); cap `zelo_edit_schedule`; payload `permissions` + catálogos para editor; escala completa em leitura para `zelo_view_ops`; `reallocate` com checagem de supervisão.
- PWA build **99**: escala da equipa (filtros, destaque «Você»); editor «Montar escala»; API `saveScheduleScope`.
- PWA build **100**: UX do modal Montar escala (cards mobile, rodapé fixo, confirmação ao guardar).
- Plugin **2.10.1**: local do posto associado ao turno (aba Turnos); escala deriva `location` automaticamente.
- Plugin **2.10.0**: escala com início/fim customizáveis por linha (dentro do turno no catálogo); duplicata por dia+turno+pessoa+horário.
- PWA build **98**: escala detalhada ordenada por horário de início dentro de cada dia.

### Planejado (backlog UX)
- Labels de catálogo admin em EN/ES (fase 2b i18n).

### Corrigido
- Plugin **2.9.3**: export PDF — fontes FPDF em `inc/lib/font/` + `FPDF_FONTPATH`.
- Plugin **2.9.2**: export PDF — correção `str_replace` / `AllowDynamicProperties` em PHP 8+.
- Plugin **2.9.1**: export PDF escala — exceções FPDF/PHP 8.2 tratadas; PDF em paisagem; mensagem JSON em falha (em vez de página crítica WP).
- PWA build **90**: mensagem de erro do export PDF lê JSON da API.
- PWA build **91**: `escapeHtml` no turno (`shift`) dos homens-chave na governança da escala.
- PWA build **92**: re-render de blocos JS na troca de idioma (`refreshViewForLanguage` / `zelo:langChanged`).
- PWA build **93**: idiomas no cadastro/perfil — checkboxes em vez de `<select multiple>`.
- PWA build **94**: layout dos checkboxes de idioma (espaçamento, alinhamento).
- PWA build **95**: lista compacta de idiomas — divisórias leves, sem caixa por linha.
- PWA build **96**: i18n — `updateDOM(notifyApp)` evita `zelo:langChanged` no boot; listener registado ao carregar `app-v5.js`.
- PWA build **97**: cards compactos em «Minhas designações» (home voluntário).

### Adicionado
- Plugin **2.9.4**: admin Onboarding — confirmar cadastro pendente (e-mail não verificado).
- PWA build **89**: snapshots offline (`zelo_locais`, `zelo_volunteer_ops`), badge stale, `default-avatar.png` precache, escala por dia em tabela, botão exportar PDF, i18n auditoria PT/EN/ES, home extras expandida.

### Adicionado (anterior)
- Plugin **2.8.0**: idiomas no perfil — `roster.language_ids`, `user_meta` `zelo_language_ids`, herança na API, migração escala→roster; REST `GET /ops/languages`, `PATCH /auth/profile`.
- PWA build **85**: cadastro e perfil com multi-select de idiomas (opcional).
- Plugin **2.7.1**: rótulos dia+data (`zelo_ops_day_label`), governança sexta/sábado/domingo, turnos default 07:00–12:30 / 12:30–18:30, migração idempotente.
- PWA build **83**: `getOpsDayLabel` com data de `event_dates`; filtros da escala com data.
- Guia «Cadastrar escala Congresso» em `docs/DEPLOY-ZELO-PWA.md`.
- Plugin **2.7.0**: fluxo confirmação voluntários — compromisso antecipado (`zelo_volunteer_commitments`), prazo e janelas de presença no admin, vínculo cadastro↔roster com aprovação (`zelo_link_requests`), alerta supervisor na recusa, validação check-in/out, REST `/ops/assignments/{id}/commit`, `/ops/onboarding`, `/ops/link-requests/*`, stub push.
- PWA build **81**: UI aceitar/recusar turno, check-in/out com janelas, ações de supervisor, hub avisos (`commitment-*`, `checkout-*`), prompt notificações, handlers push no SW (preparação).
- PWA build **78**: bottom nav 5 itens (S.O.S. central), header com sino (hub avisos MVP) e menu (Instalar/cache), widget tempo na home, view `avisos`.
- Plugin **2.6.5**: endpoint público `GET /zelo/v1/clima` (proxy Open-Meteo, cache 30 min, coordenadas do evento).
- PWA build **77**: view Previsão do tempo, cache `localStorage` (`zelo_clima`).

### Corrigido
- PWA build **86**: `getLanguageCatalog()` — não cacheia `[]` em falha de API; nova visita a cadastro/perfil tenta de novo.
- Plugin **2.7.2**: aba Onboarding — exibe todas as designações da escala; contagem por voluntário alinhada ao vínculo real (nome/WP/roster).
- PWA build **84**: `canCheckinAssignment` — sem `item.end`, `endMs` volta a ser `null` (build 82 tinha alterado para fallback de 4h por engano); `canCheckoutAssignment` restaura chamada direta a `getAssignmentEndMs` após guard de `startMs`.
- PWA build **82**: `canCheckoutAssignment` / `getAssignmentEndMs` — evita `NaN` quando `getAssignmentStartMs` retorna null (janela de checkout bloqueada de forma segura).
- PWA build **80**: `doReallocate()` atualiza badge de avisos e painel home após realocação.

### Alterado
- PWA build **79**: hub avisos — check-in pendente para **qualquer** designação sua (não só “hoje”).
- PWA build **78**: removido **TEMPO** do bottom nav; previsão acessível pelo widget na home.

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
