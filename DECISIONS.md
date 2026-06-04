# DECISIONS.md — Registro de decisões (ADR)

Decisões técnicas do projeto ZELO. Formato curto: **contexto → decisão → consequências**.

Novas decisões: adicione no topo com data `YYYY-MM-DD`.

---

## ADR-020 — Backlog no GitHub Project (2026-06-04)

**Contexto:** Tarefas estavam em `SITE-NOVO-VTIS` (repo privado do site); código vive em `esvianna/ZELO` (público). Transferência nativa de issues privado→público é bloqueada pelo GitHub.

**Decisão:** Backlog oficial no [Project 3](https://github.com/users/esvianna/projects/3) com issues em **`esvianna/ZELO`**. Nova demanda → issue em **Backlog** + plano para aprovação; codificação (humano ou agente) só com status **Ready** ou superior, salvo aprovação explícita do plano. `PROJECT_STATUS.md` / `ROADMAP.md` complementam, não substituem o quadro. Regras em `.cursor/rules/zelo-github-backlog.mdc` e `docs/GITHUB-WORKFLOW.md`.

**Consequências:** Issues migradas: SITE-NOVO-VTIS#1/#2 fechadas → ZELO#1/#2. Template `.github/ISSUE_TEMPLATE/tarefa.yml`.

---

## ADR-019 — Reconciliação de compromissos ao editar escala (2026-06-02)

**Contexto:** Guardar um turno na PWA (`POST /ops/schedule`) apagava compromissos de todas as linhas do scope, obrigando reconfirmação mesmo sem alteração; não havia aviso «escala mudou».

**Decisão:** Após normalizar linhas do scope, `zelo_ops_reconcile_schedule_scope` compara fingerprint por `assignment_id` (`wp_user_id`, `roster_volunteer_id`, `start`, `end`). Linha inalterada mantém compromisso e check-in. Linha nova ou fingerprint diferente → `zelo_commitment_mark_schedule_changed` (`pending_reason: schedule_changed`) e limpeza de check-in; snapshot `prior_commitment` preserva aceite/recusa anterior (2.11.2). Linha removida → `zelo_ops_cleanup_orphan_assignment_data` só para IDs ausentes na nova escala. PWA: aviso dedicado nos Avisos; e-mail no cron horário (`schedule_changed`, dedup). Aceitar/recusar substitui o registo de compromisso (limpa motivo). Admin WP (save aba Escala) fora do escopo v1.

**Consequências:** Plugin **2.11.1+**; PWA build **101**. Histórico `schedule_patch` inclui `reconcile` (contagens).

---

## ADR-018 — Escala na PWA: leitura para voluntários e edição escopada (2026-06-02)

**Contexto:** Responsáveis de turno precisam montar a escala no evento sem wp-admin; voluntários logados precisam ver a equipa (não só as próprias linhas).

**Decisão:** `POST /zelo/v1/ops/schedule` faz merge por `day`+`shift` com validação existente (`zelo_validate_schedule_rows`). Permissão: `zelo_edit_schedule` + `zelo_user_can_supervise_assignment` por turno (governança); gestores (`zelo_manage_ops`) editam tudo. Payload inclui `permissions.schedule_edit`, `permissions.schedule_view: full` e catálogos mínimos só para editores. Voluntários com `zelo_view_ops` recebem escala completa em leitura; compromissos/check-ins permanecem só nas próprias designações. PWA: bloco «Minhas designações», filtros, botão «Montar escala», overlay editor. `POST /ops/reallocate` exige supervisão na linha. Concorrência: last-write-wins em `wp_options`.

**Consequências:** Plugin **2.11.0**; PWA build **99**. Governança preenchida no admin é pré-requisito para edição escopada.

---

## ADR-017 — Local vinculado ao turno (2026-06-02)

**Contexto:** Na escala, o mesmo posto físico repete-se para todas as faixas de um turno (A1, B1…); selecionar local em cada linha era redundante.

**Decisão:** `catalogs.shifts[].location_id` referencia `catalogs.locations[]`. Na normalização/API, `schedule[].location` é **sempre derivado** do turno da linha. Admin: select de local na aba Turnos; coluna Local removida da Escala. Migração copia o local mais frequente na escala legada para cada turno sem `location_id`.

**Consequências:** Plugin **2.10.1**; PWA inalterada (campo `location` por linha mantido na API). Salvar escala exige turno com local configurado.

---

## ADR-016 — Horário customizado por linha da escala (2026-06-02)

**Contexto:** Operação precisa subdividir turnos macro (A1 07:00–12:30) em faixas (ex. 07:00–08:15) sem novo modelo de slots nem CPT.

**Decisão:** `schedule[].start` e `schedule[].end` opcionais por linha; se preenchidos, têm prioridade sobre o catálogo de turnos; se vazios, fallback ao turno. Admin grava via `sched_start[]`/`sched_end[]`. Validação: `start < end` e faixa contida no turno; duplicata = mesmo dia + turno + voluntário + mesmo par horário. Compromisso/check-in/lembretes/export continuam por `assignment_id` com janelas derivadas da faixa da linha.

**Consequências:** Plugin **2.10.0**; PWA build **98** (ordenação por `start` na tabela). JSON avançado sem validação no save (comportamento pré-existente). Grade/headcount/programação permanecem fora de escopo.

---

## ADR-015 — Snapshots offline + escala legível + export PDF (2026-05-28)

**Contexto:** Escala e locais não persistiam em `localStorage`; avatar offline falhava; UI da escala difícil de ler; export REST era stub 501; strings novas só em PT.

**Decisão:** PWA grava `zelo_locais`, `zelo_volunteer_ops` (+ `_mine`) em snapshot com badge stale (como clima/evento). `default-avatar.png` no precache SW. Escala agrupada por dia em tabela responsiva; governança em `<details>`. `GET /ops/export` com FPDF vendored (`inc/lib/fpdf.php`), permissão `zelo_manage_ops`, PDF por dia com governança e status compromisso/presença. Auditoria i18n PT/EN/ES nas telas auditadas; labels de catálogo admin permanecem no idioma cadastrado (exceção até campos `label_en`/`label_es`).

**Consequências:** Plugin **2.9.0**; PWA build **89**. Home «Mais opções» expandida por defeito (`localStorage zelo_home_extras_open=0` para recolher).

---

## ADR-014 — Idiomas no perfil do voluntário (2026-05-28)

**Contexto:** Idiomas eram multi-select em cada linha da escala (71+ repetições); não havia campo no roster nem no cadastro PWA.

**Decisão:** Catálogo global (`catalogs.languages`) inalterado; **capacidade** em `roster_volunteers.language_ids` e `user_meta` `zelo_language_ids`. Admin preenche na aba Voluntários; voluntário no cadastro/perfil (opcional). Coluna Idiomas removida da escala; `schedule[].languages` na API é **derivado** do voluntário. Migração idempotente copia idiomas legados da escala para o roster. REST: `GET /ops/languages`, `PATCH /auth/profile`.

**Consequências:** Plugin **2.8.0**; PWA build **85**. Requisito de idioma por posto/local fica fora de escopo.

---

## ADR-013 — Compromisso antecipado vs presença (check-in/out) (2026-05-28)

**Contexto:** `zelo_volunteer_checkins.pending` misturava “não aceitei o turno” com “não fiz check-in”; avisos e e-mails não distinguiam as fases.

**Decisão:** Três camadas: onboarding (roster ↔ WP com aprovação admin), **compromisso** (`zelo_volunteer_commitments`: pending/accepted/declined + prazo `commitment_deadline` no admin), **presença** (check-ins existentes com validação de janela configurável). Recusa alerta supervisor (governança com `*_supervisor_id`). Supervisores podem agir em nome (`on_behalf`). Push e motor unificado: Fase 3–4.

**Consequências:** Plugin **2.7.0**; PWA build **81**; novas rotas REST `/ops/assignments/{id}/commit`, `/ops/onboarding`, `/ops/link-requests/*`.

---

## ADR-012 — Nav 5 itens + header sino/menu + hub avisos MVP (2026-05-28)

**Contexto:** Bottom nav com 6 itens desalinhava o S.O.S.; tempo e avisos competiam por espaço na barra.

**Decisão:** Bottom nav com 5 destinos (`INÍCIO · MAPA · S.O.S · INFO · PERFIL/OPERAÇÃO`); widget de tempo na home; sino no header para hub `view-avisos` (agregação cliente + `zelo_avisos_read` em localStorage); menu header para Instalar PWA e limpar cache. Carrossel de destaques e inbox servidor → Fase 2 (`ROADMAP.md`).

**Consequências:** PWA build **78**; view `view-tempo` mantida sem tab na barra.

---

## ADR-011 — Previsão do tempo via Open-Meteo (2026-05-28)

**Contexto:** Visitantes precisam de previsão meteorológica no local do evento, com visualização simples, offline após primeira carga e sem expor API keys no frontend.

**Decisão:** Proxy WordPress `GET /zelo/v1/clima` → Open-Meteo; lat/lng de `zelo_event_data`; cache servidor (`set_transient`, 30 min, filtro `zelo_weather_cache_ttl`); PWA com `localStorage` (`zelo_clima`) e view dedicada + item **TEMPO** no bottom nav (build 77).

**Consequências:** Endpoint público (como `/evento`); sem PII; atribuição Open-Meteo na UI; toggle `weather_enabled` no admin. Plugin **2.6.5**.

---

## ADR-010 — Catálogos de escala em `wp_options` (2026-05-28)

**Contexto:** Admin da escala usava campos texto livres (dia, turno, local, idiomas, `wp_user_id` manual); voluntários sem conta WP precisam constar na escala.

**Decisão:** Estender `zelo_volunteer_ops_data` com `catalogs` (`shifts`, `locations`, `languages`, `roster_volunteers`) e `roster_volunteer_id` nas linhas de `schedule`. Admin com abas CRUD + select na escala (WP `wp:{id}` ou roster `rv:{id}`). Validação servidor: duplicado dia+turno por pessoa. Sem CPT/MySQL extra.

**Consequências:** Migração idempotente a partir da escala existente; PWA inalterada (consome mesmos campos string). Plugin **2.6.0**.

---

## ADR-008 — Governança e documentação viva (2026-05-27)

**Contexto:** Retomada do projeto após pausas; código gerado por IA sem memória persistente.

**Decisão:** Manter `PROJECT_STATUS.md` como fonte de continuidade; `AGENTS.md` como entrada para IAs; regras em `.cursor/rules/`.

**Consequências:** Toda sessão relevante deve atualizar status/changelog; custo baixo de manutenção documental.

---

## ADR-009 — Pacote A: app orientado a voluntários (2026-05-27)

**Contexto:** O sistema passa a ser usado principalmente pelo departamento de informações; visitantes podem acessar o site sem divulgação ativa.

**Decisão:** Pacote A — home com painel operacional pós-login; bottom nav "Operação"; `GET /ops/voluntarios?mine=1` para voluntário comum; badges de check-in; remoção do bypass público de ops; build PWA 65.

**Consequências:** Visitante anônimo mantém home clássica; voluntário logado prioriza escala e check-in. Check-in em designação alheia permanece limitação até Pacote B.

---

## ADR-007 — Acesso público temporário a ops (2026-05, revogado)

**Contexto:** Necessidade de demonstrar escala de voluntários sem login durante apresentação.

**Decisão:** `add_filter( 'zelo_ops_voluntarios_public_read', '__return_true', 1 )` em `zelo-assistente.php` (marcado TEMPORÁRIO).

**Consequências:** GET `/ops/voluntarios` expôs escala temporariamente.

**Status:** **Revogado** em 2026-05-27 (plugin 2.5.1 / Pacote A).

---

## ADR-006 — Cadastro de voluntários com verificação de e-mail (2026-05)

**Contexto:** Criar contas WP para voluntários sem abrir wp-admin.

**Decisão:** Endpoints `/auth/register` e `/auth/verify-email`; meta `zelo_email_verified`; rate limit 8/hora/IP.

**Consequências:** Usuários legado sem meta são tratados como verificados; login bloqueado até verificação para novos.

---

## ADR-005 — Dados de operação em `wp_options` (2026-05)

**Contexto:** MVP de escala, check-ins, swaps e histórico sem migração de schema.

**Decisão:** Armazenar JSON em options (`zelo_volunteer_ops_*`, swaps, etc.) editáveis pelo admin **Zelo → Operação Voluntários**.

**Consequências:** Simples de implementar; limites de tamanho/performance se escala crescer muito; backup crítico.

**Alternativa futura:** Tabelas customizadas ou CPT dedicado se houver multi-evento ou auditoria formal.

---

## ADR-004 — Same-origin para autenticação (MVP)

**Contexto:** Login REST com cookies de sessão WordPress e header `X-WP-Nonce`.

**Decisão:** PWA servida no **mesmo domínio** que o WordPress (HTTPS). Documentado em `docs/DEPLOY-ZELO-PWA.md`.

**Consequências:** Cross-domain exige JWT/App Passwords + CORS — fora do MVP.

---

## ADR-003 — APIs públicas de conteúdo (visitante)

**Contexto:** PWA offline e acesso sem login para visitantes.

**Decisão:** `permission_callback => __return_true` em GET `/locais`, `/evento`, `/categorias`, `/indoor-map`.

**Consequências:** Dados são públicos por design; não colocar segredos nesses payloads.

---

## ADR-002 — PWA vanilla + Service Worker network-first (2024–2026)

**Contexto:** Equipe pequena, deploy estático, necessidade offline em eventos.

**Decisão:** Sem bundler; JS modular por arquivos (`app-v5.js`, `api-v5.js`); cache versionado por query string + `CACHE_NAME`.

**Consequências:** Disciplina rígida de versionamento (`DEPLOYMENT_RULES.md`); risco de desalinhamento se esquecido.

---

## ADR-001 — WordPress como backend (inicial)

**Contexto:** Necessidade de painel admin, importadores e REST rápido.

**Decisão:** Plugin `zelo-assistente` com CPT `zelo_local` e REST namespace `zelo/v1`.

**Consequências:** Acoplamento ao ecossistema WP; benefício de roles, cron e admin nativos.

---

## Template para nova ADR

```markdown
## ADR-XXX — Título (YYYY-MM-DD)

**Contexto:** …
**Decisão:** …
**Consequências:** …
**Alternativas consideradas:** …
```
