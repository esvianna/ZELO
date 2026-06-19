# ROADMAP.md — ZELO

Roadmap orientado ao contexto real do projeto (visitante + voluntários + WordPress). **Backlog operacional e priorização:** [GitHub Project 3](https://github.com/users/esvianna/projects/3) (ADR-020). Atualize este arquivo quando fases mudarem; issues linkadas são a fonte de tarefas.

**Versão de referência no repo:** plugin **2.17.0**, PWA **build 149**.

---

## Fase actual: Operação em evento (Curitiba/2026)

**Objetivo:** Conteúdo e config do evento; backlog novo via Project 3.

| # | Entrega | Status | Issue |
|---|---------|--------|-------|
| 1 | Web Push VAPID + subscribe PWA | **Concluído** | [#36](https://github.com/esvianna/ZELO/issues/36) |
| 2 | Reset push pós-VAPID (#42) | **Concluído** | [#42](https://github.com/esvianna/ZELO/issues/42) |
| 3 | Aprovação cadastro voluntários | **Concluído** | [#41](https://github.com/esvianna/ZELO/issues/41) |
| 4 | Excluir linha escala (pending/accepted) | **Concluído** | [#43](https://github.com/esvianna/ZELO/issues/43) |
| 5 | Admin dedupe escala | **Concluído** | [#37](https://github.com/esvianna/ZELO/issues/37) |
| 6 | Admin save por aba | **Concluído** (2.15.2) | [#39](https://github.com/esvianna/ZELO/issues/39) · [#38](https://github.com/esvianna/ZELO/issues/38) |
| 7 | Conteúdo imprensa + config evento | **Pendente** (sem issue) | — |

---

## Fase anterior: Operação voluntários — estabilização pós-pacote escala

**Objetivo:** Dept. de informações com escala, check-in, export e filtros em produção; visitantes ocasionais sem login preservados.

| # | Entrega | Status | Issue |
|---|---------|--------|-------|
| 1 | Alinhar versionamento PWA | Concluído | — |
| 2 | Remover leitura pública de `/ops/voluntarios` | Concluído (2.5.1) | — |
| 3 | Painel home + nav Operação + badges | Concluído | — |
| 4 | Checklist `TESTING.md` em staging/produção | Concluído | [#6](https://github.com/esvianna/ZELO/issues/6) |
| 5 | Backup WP (locais + options ops) | Operacional (fora do repo) | — |
| 6 | Edição escala PWA + reconciliação compromissos | Concluído (2.11.x) | [#1](https://github.com/esvianna/ZELO/issues/1) |
| 7 | Export PDF agrupado por faixa | Concluído (2.11.5–2.11.7) | [#7](https://github.com/esvianna/ZELO/issues/7) |
| 8 | Filtros escala (idioma, responsável, nome) | Concluído (PWA 106) | [#11](https://github.com/esvianna/ZELO/issues/11) |

---

## Pacote — Confirmação voluntários (2026-05-28)

| # | Entrega | Status | Issue |
|---|---------|--------|-------|
| C1 | Compromisso antecipado + prazo admin | Implementado (2.7.0) | — |
| C2 | Janelas check-in/out + validação API | Implementado | — |
| C3 | Onboarding roster + fila vínculos | Implementado | — |
| C4 | Web Push completo (VAPID + subscribe) | **Concluído** (#36, ADR-035) | [#36](https://github.com/esvianna/ZELO/issues/36) · [#8](https://github.com/esvianna/ZELO/issues/8) |
| C5 | Motor notificações unificado + inbox servidor | **Descartado** (ADR-028; hub + e-mail + localStorage) | [#9](https://github.com/esvianna/ZELO/issues/9) |

---

## Fase B — Operação voluntários (extensões)

| # | Entrega | Status | Issue |
|---|---------|--------|-------|
| B0 | Check-in restrito à própria designação | **Concluído** (2.7.0) | — |
| B1 | Exportação escala (`/ops/export`) PDF/CSV | **Concluído** (2.9.0+; faixa 2.11.5–7) | [#7](https://github.com/esvianna/ZELO/issues/7) |
| B2 | Painel de cobertura por posto/idioma | **Descartado** (ADR-027; escala + filtros #11) | [#10](https://github.com/esvianna/ZELO/issues/10) |
| B3 | Escala PWA — filtros (idioma, responsável, voluntário) | **Concluído** (PWA 106) | [#11](https://github.com/esvianna/ZELO/issues/11) |
| B4 | Escala PWA — UX listagem por dia / por turno | **Concluído** (build 89→102) | [#1](https://github.com/esvianna/ZELO/issues/1) |
| B5 | Escala PWA — WhatsApp | **Concluído** (2.11.4 / PWA 105) | [#12](https://github.com/esvianna/ZELO/issues/12) |
| B6 | Home — «Mais opções» expandido por defeito | Concluído (build 89) | — |
| B7 | Persistir última view após refresh (F5) | **Concluído** (PWA 107) | [#27](https://github.com/esvianna/ZELO/issues/27) |

---

## Fase A — Operação voluntários (legado pós-MVP)

Itens históricos; ver **Fase B** para status atualizado.

| # | Entrega | Status | Issue |
|---|---------|--------|-------|
| A1 | Exportação escala/check-ins (`/ops/export`) | **Concluído** (2.9.0+; ver B1 / #7) | [#7](https://github.com/esvianna/ZELO/issues/7) |
| A2 | Auditoria de permissões em todos os endpoints ops/swap | **Concluído** (2.11.8, #13) | [#13](https://github.com/esvianna/ZELO/issues/13) |
| A3 | Documentar fluxo admin: escala → roles → teste PWA | Em `docs/DEPLOY-ZELO-PWA.md` | — |
| A4 | Histórico de realocações — retenção/limites | Implementado (slice 15 na UI) | — |

---

## Fase B (UX) — Visitante (PRD `product_requirements_document.md`)

| # | Entrega | Status | Issue |
|---|---------|--------|-------|
| B0 | Mapa indoor estádio + direções (POIs públicos, i18n) | **In review** | [#28](https://github.com/esvianna/ZELO/issues/28) |
| B1 | Hierarquia visual reforçada (emergência destacada) | Concluído (2.13.4 / PWA 128) | [#17](https://github.com/esvianna/ZELO/issues/17) |
| B2 | Branding evento na splash/home | **Descartado** (ADR-031; banner actual basta) | [#18](https://github.com/esvianna/ZELO/issues/18) |
| B3 | Card visual do mapa | Implementado (`home-map-card`) | — |
| B4 | Bottom navigation | Implementado | — |
| B5 | Botão / área “Programação” | **Descartado** (ADR-029; JW Library + impresso) | [#14](https://github.com/esvianna/ZELO/issues/14) |
| B6 | Protótipos `docs/stitch_zelo/` → telas reais | Referência de design | — |
| B7 | Hub avisos unificado (sino) | Implementado MVP (build 78) | — |
| B8 | Widget tempo na home | Implementado (build 78) | — |
| B9 | Carrossel destaques na home | **Concluído** (2.13.3 / PWA 126) | [#15](https://github.com/esvianna/ZELO/issues/15) |
| B10 | Inbox avisos com persistência servidor | **Descartado** (ADR-028; com #9) | [#16](https://github.com/esvianna/ZELO/issues/16) |

---

## Fase C — Infraestrutura e qualidade

| # | Entrega | Status | Issue |
|---|---------|--------|-------|
| C1 | `.gitignore` para PHP/IDE/OS | **Concluído** | [#19](https://github.com/esvianna/ZELO/issues/19) |
| C2 | Testes automatizados (PHPUnit REST + smoke E2E) | **Descartado** (ADR-033; `TESTING.md` manual) | [#20](https://github.com/esvianna/ZELO/issues/20) |
| C3 | Config de ambiente (URL API sem hardcode) | **Escopo mínimo** (PWA 129, ADR-034) | [#21](https://github.com/esvianna/ZELO/issues/21) |
| C4 | Rate limiting REST (login/register) | **Concluído** (2.13.5) | [#22](https://github.com/esvianna/ZELO/issues/22) |
| C5 | Cross-domain PWA + JWT | Futuro (ver DECISIONS) | — |

---

## Outras demandas (backlog)

| Issue | Título |
|-------|--------|
| [#24](https://github.com/esvianna/ZELO/issues/24) | Ícone olho para ver senha |
| [#25](https://github.com/esvianna/ZELO/issues/25) | Perfil — alterar dados pessoais |
| [#26](https://github.com/esvianna/ZELO/issues/26) | Posts como notificações (`docs/ISSUE-26-BLOG-NOTIFICACOES.md`) |
| [#28](https://github.com/esvianna/ZELO/issues/28) | Mapa estádio + direções internas (planejamento: `docs/ISSUE-28-STADIUM-MAP.md`) |

---

## Fase D — Pós-evento / manutenção

- Consolidar changelog e tag de release (plugin + PWA build).
- Arquivar dados do evento (export, snapshot options).
- Retrospectiva: métricas de uso (se disponíveis no servidor).
- Decidir se cadastro público de voluntários permanece ativo.

---

## Critério de “pronto” por fase

- **Estabilização:** `TESTING.md` checklist verde em HTTPS same-origin ([#6](https://github.com/esvianna/ZELO/issues/6) fechada).
- **Ops:** login → escala → check-in → realocação (perfis distintos) sem 401/403 indevidos.
- **UX:** validação com 2–3 usuários reais em celular (Android + iOS).
