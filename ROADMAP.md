# ROADMAP.md — ZELO

Roadmap orientado ao contexto real do projeto (visitante + voluntários + WordPress). Atualize prioridades conforme a data do próximo evento.

---

## Fase atual: Pacote A (concluído) — validação em produção

**Objetivo:** Voluntários do dept. de informações com fluxo mínimo (home operacional, nav Operação, escala mine, check-in). Visitantes ocasionais sem login preservados.

| # | Entrega | Status |
|---|---------|--------|
| 1 | Alinhar versionamento PWA (build 65) | Concluído |
| 2 | Remover leitura pública de `/ops/voluntarios` | Concluído (2.5.1) |
| 3 | Painel home + nav Operação + badges | Concluído |
| 4 | Checklist `TESTING.md` em staging/produção | Pendente |
| 5 | Backup WP (locais + options ops) | Operacional (fora do repo) |

---

## Pacote — Confirmação voluntários (2026-05-28)

| # | Entrega | Status |
|---|---------|--------|
| C1 | Compromisso antecipado + prazo admin | Implementado (2.7.0) |
| C2 | Janelas check-in/out + validação API | Implementado |
| C3 | Onboarding roster + fila vínculos | Implementado |
| C4 | Web Push completo (VAPID + subscribe) | Pendente (stub 501) |
| C5 | Motor notificações unificado + inbox servidor | Parcial (hook + e-mail) |

---

## Fase B — Operação voluntários (próximo pacote)

| # | Entrega | Status |
|---|---------|--------|
| B0 | Check-in restrito à própria designação | **Concluído** (2.7.0 — titular ou supervisor) |
| B1 | Exportação escala/check-ins (`/ops/export`) | Feito 2.9.0 (PDF) |
| B2 | Painel de cobertura por posto/idioma | Pendente |
| B3 | **Escala PWA — filtros** (idioma, responsável, voluntário) | Implementado (PWA 106, ZELO#11) |
| B4 | **Escala PWA — UX listagem por dia** (referência PDF Congresso) | Feito build 89 |
| B5 | **Escala PWA — WhatsApp** (responsáveis + voluntários, tel. cadastro) | Pendente |
| B6 | **Home — «Mais opções» expandido por defeito** | Feito build 89 |

---

## Fase A — Operação voluntários (pós-MVP imediato)

| # | Entrega | Status |
|---|---------|--------|
| A1 | Exportação escala/check-ins (`/ops/export`) | Stub 501 |
| A2 | Auditoria de permissões em todos os endpoints ops/swap | Parcial |
| A3 | Documentar fluxo admin: escala → roles → teste PWA | Em `docs/DEPLOY-ZELO-PWA.md` |
| A4 | Histórico de realocações — revisar retenção/limites | Implementado (slice 15 na UI) |

---

## Fase B — UX visitante (PRD `product_requirements_document.md`)

Itens do PRD ainda parcialmente cobertos ou evoluíveis:

| # | Entrega | Status |
|---|---------|--------|
| B1 | Hierarquia visual reforçada (emergência destacada) | Parcial (CSS cards) |
| B2 | Branding evento na splash/home | Parcial (banner evento) |
| B3 | Card visual do mapa | Implementado (`home-map-card`) |
| B4 | Bottom navigation | Implementado |
| B5 | Botão / área “Programação” | Não implementado |
| B6 | Protótipos `docs/stitch_zelo/` → telas reais | Referência de design |
| B7 | Hub avisos unificado (sino) | Implementado MVP (build 78) |
| B8 | Widget tempo na home | Implementado (build 78) |
| B9 | Carrossel destaques na home | Pendente (Fase 2) |
| B10 | Inbox avisos com persistência servidor | Pendente (Fase 2) |

---

## Fase C — Infraestrutura e qualidade

| # | Entrega | Status |
|---|---------|--------|
| C1 | `.gitignore` para PHP/IDE/OS | Pendente |
| C2 | Testes automatizados (PHPUnit REST + smoke E2E opcional) | Não iniciado |
| C3 | Config de ambiente (URL API sem hardcode) | Pendente |
| C4 | Rate limiting REST (login/register) | Parcial (só register) |
| C5 | Cross-domain PWA + JWT | Futuro (ver DECISIONS) |

---

## Fase D — Pós-evento / manutenção

- Consolidar changelog e tag de release (plugin + PWA build).
- Arquivar dados do evento (export, snapshot options).
- Retrospectiva: métricas de uso (se disponíveis no servidor).
- Decidir se cadastro público de voluntários permanece ativo.

---

## Critério de “pronto” por fase

- **Estabilização:** TESTING.md checklist verde em HTTPS same-origin.
- **Ops:** login → escala → check-in → realocação (perfis distintos) sem 401/403 indevidos.
- **UX:** validação com 2–3 usuários reais em celular (Android + iOS).
