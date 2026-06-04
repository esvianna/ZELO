# CHANGELOG.md — Repositório ZELO

Histórico em nível de **projeto** (backend + frontend + docs). Detalhes finos do plugin WordPress estão em `backend-plugin/zelo-assistente/CHANGELOG.md`.

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).

---

## [Unreleased]

### Adicionado
- Plugin **2.11.4** + PWA **105**: links WhatsApp no nome dos voluntários e do responsável do turno (só com telefone cadastrado).

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
- Escala: filtros responsável/voluntário; links WhatsApp.
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
