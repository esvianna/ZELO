# DECISIONS.md — Registro de decisões (ADR)

Decisões técnicas do projeto ZELO. Formato curto: **contexto → decisão → consequências**.

Novas decisões: adicione no topo com data `YYYY-MM-DD`.

---

## ADR-044 — Export PDF mapa: legenda por pavimento + cabeçalho logo (#66, 2026-06-25)

**Contexto:** Com mais locais no mapa, legenda plana 32 mm (#64.8) quebra demais; cabeçalho genérico sem identidade visual.

**Decisão:** Mapa ~230 mm + legenda lateral ~50 mm agrupada por `place['floor']` (vazio → «Outros»); cabeçalho logo PWA + «Mapa» + `zelo_event_data`; data 8 pt canto superior direito; **sem** numeração de páginas; overflow legenda → pág. 2 (120 mm). Preservar pinos GD, números vetor FPDF, coluna lateral (sem overlay).

**Emenda 2026-06-27 (b):** título **fixo** «Mapa Congresso Internacional Curitiba 2026»; legenda overlay com **30 mm** reservados no canto inferior direito (legenda do diagrama) — plugin **2.26.3**.

**Consequências:** Plugin **2.26.0** → **2.26.1**; smoke **2z7j**; PWA/admin inalterados.

---

## ADR-041 — Swap: modais, substituto roster WP e notificações imediatas (#56) (2026-06-22)

**Contexto:** Aprovar/recusar pedido de substituição usava `prompt()` na PWA, sem notificar solicitante/substituto. Issue [#56](https://github.com/esvianna/ZELO/issues/56).

**Decisão:** Recusar exige justificativa (modal PWA + histórico ops `swap_rejected`). Aprovar exige substituto do **roster com `linked_wp_user_id`**. Notificação **imediata** via sino (feed derivado de `swap_requests`), `zelo_notify_deliver_timely` e SMS Comtele (#54) — templates backend **PT apenas**; fora do digest #44. Push nativo continua fora (ADR-026).

**Consequências:** Plugin **2.21.0**, PWA **165**. Sem aprovação «substituição pendente» sem substituto.

**Emenda 2026-06-22 (b):** lista de substitutos = **todos os utilizadores WP cadastrados** (`zelo_get_zelo_volunteer_users` — voluntário, homem-chave, supervisor, admin). Catálogo roster do admin deixa de ser critério — plugin **2.21.3** / PWA **168**.

**Emenda 2026-06-22 (a):** lista de substitutos inclui também linhas da **escala com `wp_user_id`** — substituída pela emenda (b).

---

## ADR-034 — Config API (#21) escopo mínimo (2026-06-04)

**Contexto:** Issue [#21](https://github.com/esvianna/ZELO/issues/21) (ROADMAP C3) previa estratégia por ambiente. Análise em `docs/ISSUE-21-CONFIG-AMBIENTE.md`. Produção same-origin já resolve `baseUrl`/`siteUrl` via `window.location.origin`; lacuna: hardcode duplicado no login (`app-v5.js`).

**Decisão:** **Escopo mínimo** — login usa só `API.baseUrl`; comentário em `api-v5.js`; docs `TESTING.md` e `DEPLOY-ZELO-PWA.md` alinhados. **Não** implementar override dev/staging nem `zelo-config.js`. Cross-domain continua fora do MVP.

**Consequências:** PWA **129**; issue #21 fechada. Fallback `tenhazelo.com.br` em `api-v5.js` mantido para edge cases.

---

## ADR-033 — Testes automatizados (#20) descartados (2026-06-04)

**Contexto:** Issue [#20](https://github.com/esvianna/ZELO/issues/20) (ROADMAP C2) previa PHPUnit REST + smoke E2E + GitHub Actions. Hoje só existe validação manual (`TESTING.md`); `.gitignore` já reserva entradas phpunit.

**Decisão:** **Não implementar** suite automatizada nem CI de testes neste ciclo. Manter `TESTING.md` como fonte de verdade; smoke manual antes de deploy/evento.

**Consequências:** Issue #20 fechada como *won't fix* / descartada. ROADMAP C2 marcado descartado. Documento `docs/ISSUE-20-TESTES-DESCARTE.md`.

---

## ADR-032 — Rate limit login REST (#22) (2026-06-04)

**Contexto:** Issue [#22](https://github.com/esvianna/ZELO/issues/22). Register (8/h/IP) e export (60 s/user) já existiam; `POST /auth/login` público sem throttle (`SECURITY.md`).

**Decisão:** Helper `inc/rate-limit.php` com transients WP. Login: **30/15 min/IP** + **10/15 min/username**; sucesso e falha contam. Register mantém 8/h/IP. verify-email e lost password fora do MVP. Filtro `zelo_rate_limit_enabled` para dev.

**Consequências:** Plugin **2.13.5**; sem bump PWA obrigatório (mensagem 429 do servidor). Documentação `SECURITY.md` + `TESTING.md`.

---

## ADR-031 — Branding splash/home (#18) descartado (2026-06-04)

**Contexto:** Issue [#18](https://github.com/esvianna/ZELO/issues/18) / ROADMAP B2 previa splash e branding reforçado na home (PRD §5.2). Análise em `docs/ISSUE-18-BRANDING-DESCARTE.md`. Banner evento + logo/nome no admin já cobrem identificação mínima.

**Decisão:** **Não implementar** splash dedicada nem faixa de branding além do banner actual. Sem evolução de cores/assets custom por evento neste ciclo.

**Consequências:** Issue #18 fechada como *won't fix* / descartada. B2 branding no ROADMAP marcado descartado.

---

## ADR-030 — Carrossel novidades na home (#15) via posts WP (2026-06-04)

**Contexto:** Issue [#15](https://github.com/esvianna/ZELO/issues/15) previa carrossel de destaques na home. Análise em `docs/ISSUE-15-CARROSSEL-HOME.md`. Banner evento único já cobre 1 destaque público; novidades (#26) são só logados.

**Decisão:** Implementar carrossel **horizontal scroll-snap** na home para **utilizadores logados**, alimentado por **posts WP** com meta «Destaque no carrossel da home» (`_zelo_carousel`) + imagem destacada. Endpoint `GET /news?carousel_only=1` (máx. 8). Fallback: card «Novidades» existente se não houver posts flagged. Banner evento **mantido** (público).

**Consequências:** Plugin **2.13.3** + PWA **126**. Snapshot offline `zelo_news_carousel_v1_{userId}`. Visível também para voluntários ops (não oculto). Issue #15 → **In review**.

---

## ADR-029 — Programação visitante (#14) descartada (2026-06-04)

**Contexto:** Issue [#14](https://github.com/esvianna/ZELO/issues/14) / ROADMAP B5 previa botão «Programação» na home visitante. Análise em `docs/ISSUE-14-PROGRAMACAO-VISITANTE.md`. Hoje a view Info só referencia «Consulte a programação» sem destino in-app.

**Decisão:** **Não implementar** área Programação na PWA. O programa oficial do evento estará no **app JW Library** e em **formato impresso** no local; fora do escopo do ZELO.

**Consequências:** Issue #14 fechada como *won't fix* / descartada. B5 no ROADMAP marcado descartado. Texto placeholder em credenciamento pode permanecer ou ser ajustado numa sessão futura menor (sem feature dedicada).

---

## ADR-028 — Motor notificações (#9) e inbox servidor (#16) descartados (2026-06-04)

**Contexto:** Issues [#9](https://github.com/esvianna/ZELO/issues/9) (C5) e [#16](https://github.com/esvianna/ZELO/issues/16) (B10) previam inbox REST + motor unificado via `zelo_notification_dispatch`. Análise em `docs/ISSUE-09-MOTOR-NOTIFICACOES.md`. Já existem hub sino (`buildAvisosFeed`, ADR-012), «lido» em `localStorage`, posts no feed (#26), cron e-mail (`zelo-volunteer-notifications.php`) e hook dispatch sem listeners.

**Decisão:** **Não implementar** motor unificado nem inbox persistido no servidor. Manter agregação cliente + e-mail cron + `localStorage` para «lido». Hook `zelo_notification_dispatch` permanece extensível sem evolução obrigatória.

**Consequências:** Issues #9 e #16 fechadas como *won't fix* / descartadas. ROADMAP C5 e B10 marcados descartados. #26 continua com `localStorage` para lido (decisão definitiva, não pendência #16).

---

## ADR-027 — Painel cobertura posto/idioma (#10) descartado (2026-06-04)

**Contexto:** Issue [#10](https://github.com/esvianna/ZELO/issues/10) / ROADMAP B2 previa painel agregado posto × idioma (lacunas/excesso). Análise em `docs/ISSUE-10-COBERTURA-POSTO-IDIOMA.md`. Já existem escala PWA com filtros local/idioma (#11), vista por turno, export PDF, check-in e admin «Cobertura escala» (designados vs presença por dia+turno).

**Decisão:** **Não implementar** painel dedicado posto×idioma. A equipa acede às informações com os recursos actuais. Manter admin parcial e filtros na PWA sem evolução até nova decisão explícita.

**Consequências:** Issue #10 fechada como *won't fix* / descartada. B2 no ROADMAP marcado como descartado. Metas configuráveis por idioma/posto ficam fora de escopo.

---

## ADR-026 — Web Push (#8) descartado; notificações in-app na PWA (2026-06-04)

**Status:** **Supersedido** por ADR-035 (2026-06-18).

**Contexto:** Issue [#8](https://github.com/esvianna/ZELO/issues/8) previa VAPID + `POST /ops/push/subscribe` real (hoje stub **501**). O pacote de confirmação voluntários (C4 no ROADMAP) incluía push nativo do browser.

**Decisão (histórica):** **Não implementar** Web Push como prioridade de produto. Voluntários terão acesso à PWA e verão avisos **in-app** (hub/sino + Novidades #26, badge stale, offline parcial ADR-025). Manter stub 501 e handlers `push`/`notificationclick` no SW sem evolução até nova decisão explícita.

**Consequências:** Issue #8 fechada como *won't fix* / descartada. Motor unificado (#9) e inbox servidor (#16) permanecem no backlog sem dependência de push. E-mail cron (`schedule_changed`) continua como canal assíncrono fora da PWA.

---

## ADR-035 — Web Push VAPID retomado (#36, supersede ADR-026) (2026-06-18)

**Contexto:** Retomada da [#36](https://github.com/esvianna/ZELO/issues/36) após decisão de produto. ADR-026 deixou stub 501; voluntários passam a poder receber push nativo além do hub in-app (#26) e e-mail cron.

**Decisão:** Implementar Web Push com **VAPID** (`minishlink/web-push`), subscriptions em tabela `wp_zelo_push_subscriptions`, admin em Operação Voluntários → Config. Eventos MVP: Novidades (`_zelo_as_notification`), escala alterada (imediato), check-in/check-out (cron). Unsubscribe **só no Perfil**; re-pedir consentimento no login via `zelo_push_consent_v2`. iOS/Safari: limitações aceites; fallback in-app mantido.

**Consequências:** Plugin **2.14.0** + PWA **141**. Deploy exige `vendor/` do Composer no servidor. Chave privada VAPID em `wp_options` — não commitar; gerar por ambiente.

---

## ADR-025 — Novidades: detalhe offline por post (2026-06-04)

**Contexto:** ADR-023 / issue #26 — lista `/news` já tinha snapshot `zelo_news_v2_*`, mas `GET /news/{id}` falhava offline com «Failed to fetch» ao abrir o artigo (só preview na listagem).

**Decisão:** Snapshot por post `zelo_news_item_v1_{userId}_{id}` após fetch OK; fallback offline; prefetch em background dos itens da página ao carregar lista online; prefetch da imagem destacada same-origin no SW; badge stale no detalhe; mensagem i18n `news_offline_unavailable` se nunca foi aberto online. Limpar snapshots de detalhe no logout. `loadNews()` no `init()` mesmo sem sessão WP renovada (lista em cache). Teste `TESTING.md` O6.

**Consequências:** PWA **123+**; media embutida no HTML (vídeos externos) pode falhar offline; posts nunca abertos online continuam indisponíveis offline.

---

## ADR-024 — Mapa do evento offline: snapshot + imagem no SW (2026-06-04)

**Contexto:** ADR-002/003 estabelecem PWA offline-first e API pública `/indoor-map`, mas a PWA não persistia JSON nem prefetch da imagem do diagrama — offline mostrava «não configurado» (gap vs. locais/clima/escala, ADR-015).

**Decisão:** Após fetch OK de `GET /indoor-map`, gravar snapshot `zelo_indoor_map` em `localStorage` (fallback em falha de rede). Prefetch same-origin da `image_url` para o `CacheStorage` do SW (build actual). Carregar no `init()` da app (público, como locais). Badge stale na view «Mapa do evento» quando dados vierem do cache. Testes em `TESTING.md` §12 (O5).

**Consequências:** PWA **122+**; alinhado a ADR-022. Admin que altera mapa exige visita online ou «Atualizar App» para refresh. Imagens em CDN externo ficam fora do prefetch (same-origin only).

---

## ADR-023 — Blog/novidades: logados, PT, entradas home + menu (2026-06-04)

**Contexto:** Issue #26 — publicar Posts WP na PWA como notificações e blog. Hub avisos MVP (ADR-012) usa feed cliente + `localStorage`.

**Decisão:** Canal **restrito a utilizadores logados** (cookie WP). Conteúdo admin **só PT**. Atalhos: **card na home** + **item no menu hambúrguer** + link no hub avisos. API `GET /news` exige sessão. Sem meta `_zelo_audience` no MVP. Bottom nav inalterada.

**Consequências:** Visitante anónimo não vê novidades de post. Ver `docs/ISSUE-26-BLOG-NOTIFICACOES.md`. Web Push (#8) e inbox servidor (#16) ficam fora do MVP #26.

---

## ADR-022 — Mapa indoor: diagrama, CRUD e direções por balcão (2026-06-04)

**Contexto:** Issue #28 — voluntários nos balcões (A1–B2) orientam visitantes com diagrama do estádio. Existe stub `indoor_map` + `GET /indoor-map`; catálogo ops tem `locations` e turnos sem coordenadas no plano.

**Decisão:** Sequência: (1) imagem + pinos → (2) CRUD `places[]` (`kind`: booth | department | facility | amenity | restricted) → (3) editor visual → (4) **Opção 1:** direções no formulário de cada destino (2 balcões × pt/en/es); **sem CSV**. Máx. **2 balcões**. PWA: origem = booths; destinos = demais kinds públicos.

**Consequências:** Reutiliza infra indoor; ver `docs/ISSUE-28-STADIUM-MAP.md`. Rotas normalizadas em `routes[]` ao gravar a partir dos blocos por destino.

**Alternativas consideradas:** pathfinding automático (v1 rejeitado); pinos fixos no PNG (rejeitado); direções sem origem no balcão (rejeitado).

---

## ADR-021 — Supervisão ops restrita à governança (2026-06-04)

**Contexto:** Auditoria ZELO#13: `zelo_user_can_supervise_assignment` devolvia `true` para qualquer utilizador com `zelo_reallocate_volunteer`, permitindo realocação/swap/compromisso em turnos alheios — divergente de ADR-018 e TESTING §4.5.

**Decisão:** Supervisão efectiva = `zelo_manage_ops` **ou** IDs em `zelo_resolve_shift_supervisor_user_ids` (homens-chave do turno, supervisores grupo A/B, supervisor app). Swaps GET/PATCH filtram por `zelo_user_can_resolve_swap_request`. Documentação em `docs/OPS-PERMISSIONS.md`.

**Consequências:** Plugin **2.11.8**; homem-chave fora da governança de um turno recebe **403** em acções de supervisor nesse turno. Gestores e supervisores de grupo/app inalterados.

---

## ADR-020 — Backlog no GitHub Project (2026-06-04)

**Contexto:** Tarefas estavam em `SITE-NOVO-VTIS` (repo privado do site); código vive em `esvianna/ZELO` (público). Transferência nativa de issues privado→público é bloqueada pelo GitHub.

**Decisão:** Backlog oficial no [Project 3](https://github.com/users/esvianna/projects/3) com issues em **`esvianna/ZELO`**. Nova demanda → issue em **Backlog** + plano para aprovação; codificação (humano ou agente) só com status **Ready** ou superior, salvo aprovação explícita do plano. `PROJECT_STATUS.md` / `ROADMAP.md` complementam, não substituem o quadro. Regras em `.cursor/rules/zelo-github-backlog.mdc` e `docs/GITHUB-WORKFLOW.md`.

**Consequências:** Issues migradas: SITE-NOVO-VTIS#1/#2 fechadas → ZELO#1/#2. Template `.github/ISSUE_TEMPLATE/tarefa.yml`.

**Atualização (2026-06-04):** agentes movem entregas para **In review**; **Done** só após validação humana (ver `docs/GITHUB-WORKFLOW.md`).

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

---

## ADR-036 — Aprovação de voluntário pós-e-mail (#41, 2026-06-18)

**Contexto:** Registo PWA atribuía `zelo_voluntario` imediatamente; verificação de e-mail não garantia autorização como voluntário do evento (lacuna ADR-013).

**Decisão:** Fluxo em três passos — registo → `subscriber` → verificar e-mail → fila `pending` → admin (`manage_options`) aprova (`zelo_voluntario`) ou reprova (`subscriber`). Fila e acções **só na PWA admin**. `/news` e `/indoor-map` exigem `zelo_view_ops`. Legados migrados para `approved` sem alterar roles.

**Consequências:** Visitantes e subscribers não acedem a ops, novidades internas nem mapa indoor; administradores notificados por e-mail + push.

**Alternativas consideradas:** Fila no admin WP Onboarding (rejeitada — D15); escala pública via filtro (rejeitada — R1).

---

## ADR-003 — APIs públicas de conteúdo (visitante)

**Contexto:** PWA offline e acesso sem login para visitantes.

**Decisão:** `permission_callback => __return_true` em GET `/locais`, `/evento`, `/categorias`. **`GET /indoor-map` passou a exigir `zelo_view_ops`** (ADR-036, #41).

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

## ADR-037 — E-mails de lembretes de escala: push-first + digest + fila (#44, 2026-06-20)

**Contexto:** Titan Mail Premium (HostGator): **300 e-mails/hora**, **1.000/dia**. Cron horário envia hoje **1 e-mail por designação** por janela (`zelo-volunteer-notifications.php`). Escala Curitiba/2026: **~46 voluntários**, **292 linhas** de designação (3 dias), média **6,3** designações/voluntário; **106/108** combinações vol+dia com **2+ slots** (máx. **4**/dia).

**Decisões de produto (2026-06-20):**

1. **E-mail continua obrigatório** para quem **não** tem push activo — não adoptar «só push» para check-in.
2. **Um único lembrete antecipado** por e-mail (24h **ou** 1 dia — não ambos); desactivar o segundo no admin.
3. **Push-first** para check-in, check-out e lembrete «X minutos antes»; e-mail só como fallback sem subscription.
4. **Digest por utilizador + dia** para lembretes antecipados informativos (`before_24h` / `before_1day` unificado), `schedule_changed`, `commitment_due`.
5. **Fila + throttle** (~250/h) com atraso até **1 h** aceitável para lembretes antecipados; **check-in sem atraso** na fila (prioridade alta).
6. **Monitorização:** contador diário/horário + alerta admin a 80% do teto Titan.

**Estimativa de volume (escala PDF 20/06/2026, ~85% accepted):**

| Cenário | E-mails/dia (ordem de grandeza) | Pico/h |
|---------|----------------------------------|--------|
| **Actual** (2 lembretes antecipados × linha + check-in e-mail) | **250–400+** ops | **40–90** (manhã 07:00) |
| **ADR-037** (digest + push-first 70%) | **~100–130** ops | **~15–25** |

Teto Titan (**1.000/dia**) fica confortável com ADR-037; **actual** aproxima-se do limite no pior dia se push adoption for baixa.

**Consequências:** Issue filha de implementação; alterar `zelo-volunteer-notifications.php`, possível fila em `wp_options`/tabela, admin Config (um toggle antecipado), `TESTING.md` novo § e-mail ops.

**Alternativas descartadas por agora:** ESP externo (SES/SendGrid); «só push» sem e-mail fallback.

---

## ADR-038 — Rede degradada: stale-while-revalidate + timeout (#46, 2026-06-20)

**Contexto:** Auditoria #46 — offline total (modo avião) funciona razoavelmente graças a snapshots `localStorage` (ADR-015, ADR-024, ADR-025) e precache SW (ADR-002). **Rede lenta/instável** era pior: `fetch` não falhava, snapshots não eram usados; `init()` bloqueava.

**Decisão (implementada PWA 151, issues #47–#50):**

1. **Stale-while-revalidate (SWR)** nos GET com snapshot: devolver cache imediato; revalidar em paralelo; evento `zelo:data-revalidated` actualiza UI.
2. **Timeout ~5 s** por request (`FETCH_TIMEOUT_MS`); após timeout usa snapshot.
3. **`init()` não bloqueante:** hidratação síncrona; `Promise.allSettled`; navegação inicial cedo; `refreshSession` em paralelo.
4. **SW** continua sem interceptar `/wp-json/` (ADR-002).
5. **Mutações ops** sem fila offline.
6. **Testes:** `TESTING.md` §12b.

**Consequências:** `api-v5.js` (`fetchWithStaleFallback`); `app-v5.js` (init, banner); i18n PT/EN/ES; PWA **151+**.

---

## ADR-039 — Registro apoio delegados estrangeiros (#51, 2026-06-20)

**Contexto:** Comunicado operacional — em emergência/caso delicado com delegado estrangeiro, voluntário contacta responsável no verso do crachá e deve registrar horário, local, nomes e descrição.

**Decisões de produto (aprovadas):**

1. **Notificação:** **sem** e-mail/push automático ao registrar; supervisores/gestores consultam lista na **PWA** e exportam **CSV/PDF**.
2. **Atalho voluntário:** só **Home → Operação** (não Escala).
3. **Retenção:** **arquivo permanente** (não TTL pós-evento); export para análise quando necessário.
4. **Formulário:** 5 campos do comunicado + metadados voluntário; banner «só emergência/delicado»; submit online; voluntário **não** edita após envio; gestor (`zelo_manage_ops`) **pode editar/excluir** na lista PWA.
5. **Permissões:** envio `zelo_view_ops`; listagem + export `zelo_manage_ops` (padrão export escala).

**Fora de scope MVP:** notificação imediata; rascunho offline; painel WP dedicado (consulta via PWA).

**Strings UI (locale `pt_br` — ortografia Brasil, não Portugal):**

| Chave i18n (proposta) | Texto pt_br |
|-----------------------|-------------|
| `delegate_support_card_title` | Apoio a delegados |
| `delegate_support_card_hint` | Registro — situações especiais |
| `delegate_support_title` | Registro — apoio a delegados |
| `delegate_support_banner` | Use apenas em emergência ou casos delicados (delegado perdido, desorientado, assistência especial). Situações simples de informação **não** precisam de registro. |
| `delegate_support_occurred_at` | Horário da ocorrência |
| `delegate_support_location` | Local |
| `delegate_support_use_location` | Usar local atual |
| `delegate_support_delegate_name` | Nome do delegado |
| `delegate_support_contact_name` | Pessoa contatada (verso do crachá) |
| `delegate_support_description` | Descrição da situação |
| `delegate_support_submit` | Enviar registro |
| `delegate_support_success` | Registro enviado. Obrigado. |
| `delegate_support_list_title` | Registros — apoio a delegados |
| `delegate_support_export_csv` | Exportar CSV |
| `delegate_support_export_pdf` | Exportar PDF |

**Consequências:** Issue #51; REST dedicado; option/array separado (não inflar `/ops/voluntarios`); reutilizar padrão export escala para PDF/CSV; **todas** as strings novas em pt_br conforme tabela acima.

---

## ADR-040 — SMS operacional via Comtele Gateway V4 (#54, 2026-06-22)

**Contexto:** Notificações ops usam push (ADR-035), e-mail + fila (ADR-037) e telefone já existe em `zelo_phone`. Contrato Comtele **Painel Novo** ([portal.comtele.com.br](https://portal.comtele.com.br/)) — API [Gateway V4](https://developers.comtele.com.br/): `https://api.comtele.com.br`, auth `x-api-key`. Smoke API + envio SMS validado pelo responsável (jun/2026).

**Decisões de produto:**

1. **Cascata:** SMS **em paralelo** a push **e** a e-mail (não substitui nem espera falha de outro canal).
2. **Eventos:** todos os do cron `zelo_volunteer_notify_tick` (check-in, check-out, minutos antes, digest antecipado, escala alterada, compromisso).
3. **Destinatários:** voluntários/responsáveis com `wp_user_id` + `zelo_phone` válido; **não** SMS a supervisores em recusa/swap.
4. **Sem telefone:** ignora SMS; mantém push/e-mail.
5. **Conteúdo:** templates ≤140 chars, só PT, link encurtado para PWA.
6. **LGPD (mínimo):** aviso no cadastro/perfil («telefone para avisos operacionais por SMS»); opt-in checkbox = fase 2.
7. **Orçamento inicial:** 1.000 SMS / R$ 90; **sem** teto diário hard no ZELO; alerta admin ~80% dos créditos.
8. **Digest SMS:** agrupar por voluntário+dia (não 1 SMS por linha de escala).

**Decisões técnicas:**

1. **API:** `POST /messages/sms/send`; `GET /balance`, `GET /routes`; rota **16** (Marketing) em testes/produção inicial salvo contrário no painel.
2. **Credenciais:** chave em admin Operação → Config (não no repo); **rotacionar** chave de smoke após validação; produção com chave nova.
3. **Fila SMS separada** (`zelo_notify_sms_queue`), mesmo cron; contadores espelhando e-mail.
4. **Dedup:** `user_meta` `zelo_notify_sms_log` — chave `assignment_id|window|sms`.
5. **Ficheiros:** `inc/zelo-sms-comtele.php`, `inc/zelo-notify-sms-queue.php`; estender `zelo_notify_deliver_*`.
6. **Telefone:** normalizar BR → E.164 (`55` + DDD + número); campo `custom` na API = id dedup.
7. **Relatórios/webhook Comtele:** fase 2; fase 1 confia em resposta HTTP + log admin.

**Risco volume:** ~46 vol. / ~292 linhas / todos eventos pode ultrapassar 1.000 SMS no evento — monitorizar contador; mitigar digest longo em SMS se necessário.

**Consequências:** Issue #54; `TESTING.md` § SMS; bump plugin patch ao implementar.

**Alternativas descartadas:** painel antigo (`auth-key` + `sms.comtele.com.br/api/v2`); SMS só como último recurso (opção B); opt-in obrigatório na fase 1.

---

## ADR-043 — SMS novidades (Posts WP) (#65, 2026-06-25)

**Contexto:** Novidades (#26) usam push broadcast quando `_zelo_as_notification`; SMS Comtele (#54, ADR-040) cobre só ops. Pedido: SMS opcional ao publicar post na PWA.

**Decisões:**

1. **Gatilho:** meta `_zelo_sms_notify` (checkbox explícito no editor); requer `_zelo_in_app` + Comtele activo + post `publish`.
2. **Destinatários:** utilizadores com `zelo_view_ops` + `zelo_phone` válido (E.164 BR).
3. **Conteúdo:** título + link curto PWA, ≤140 chars; tag Comtele `zelo-news`.
4. **Envio:** lotes de 25 via `zelo_comtele_send_sms`; falha → fila `zelo_notify_sms_queue`.
5. **Dedup:** 1 envio por post (`_zelo_sms_sent`); transient por `post_modified_gmt` no save.
6. **LGPD:** texto cadastro/perfil inclui «novidades do evento»; opt-in separado = fase 2.
7. **Paralelo:** não substitui push/sino; falha SMS não bloqueia publicação.

**Consequências:** Plugin **2.25.0**; `inc/zelo-news-sms.php`; `TESTING.md` S65a–c; PWA **180** (hint telefone).

---

## ADR-042 — Voluntários extras Pool B (#60, 2026-06-23)

**Contexto:** Formulários papel Curitiba 2026; extras de outros departamentos não devem misturar-se com contas WP do dept. Informações.

**Decisões:** Pool B sem login; cadastro/pedidos/encaminhamento por `zelo_view_ops`; telefone obrigatório; SMS Comtele só no encaminhamento (responsável do pedido no texto); export `zelo_manage_ops`; storage em options dedicadas.

**Consequências:** Plugin **2.22.0** / PWA **172**; `inc/zelo-extra-volunteers-ops.php`; `TESTING.md` §17.

---

## Template para nova ADR

```markdown
## ADR-XXX — Título (YYYY-MM-DD)

**Contexto:** …
**Decisão:** …
**Consequências:** …
**Alternativas consideradas:** …
```
