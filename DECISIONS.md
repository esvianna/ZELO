# DECISIONS.md — Registro de decisões (ADR)

Decisões técnicas do projeto ZELO. Formato curto: **contexto → decisão → consequências**.

Novas decisões: adicione no topo com data `YYYY-MM-DD`.

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
