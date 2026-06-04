# PROJECT_STATUS.md — Status do projeto ZELO

> **Arquivo principal de continuidade.** Atualize ao fim de cada sessão significativa de desenvolvimento.
>
> Última atualização: **2026-06-04** (fluxo In review; #24/#25 aguardando validação).

---

## Onde paramos

O projeto está em **produção funcional** com foco operacional para o **departamento de informações** (voluntários), mantendo o app **público para visitantes ocasionais**.

| Referência no repo | Versão |
|--------------------|--------|
| Plugin WordPress (`zelo-assistente.php`) | **2.11.9** |
| PWA (`zelo-build.js` / `sw.js`) | **build 108** |

**Backlog oficial:** [GitHub Project — Projeto ZELO](https://github.com/users/esvianna/projects/3) — issues em [`esvianna/ZELO`](https://github.com/esvianna/ZELO) (ADR-020, `docs/GITHUB-WORKFLOW.md`). Este arquivo **complementa** o quadro; status canônico das tarefas está no Project.

### Entregas recentes (Done no Project)

| Issue | Entrega |
|-------|---------|
| [#1](https://github.com/esvianna/ZELO/issues/1) | Escala por turno — ícones de ação, UX responsáveis (PWA 103–104) |
| [#2](https://github.com/esvianna/ZELO/issues/2) | E-mails / recuperação de senha |
| [#5](https://github.com/esvianna/ZELO/issues/5) | Deploy plugin 2.11.2 + PWA 102 |
| [#6](https://github.com/esvianna/ZELO/issues/6) | Smoke `TESTING.md` §4 em produção |
| [#7](https://github.com/esvianna/ZELO/issues/7) | Export PDF agrupado dia → turno → faixa (2.11.5–2.11.7) |
| [#11](https://github.com/esvianna/ZELO/issues/11) | Filtros escala: idioma + responsável do turno (PWA 106) |
| [#19](https://github.com/esvianna/ZELO/issues/19) | `.gitignore` monorepo (C1) |
| [#23](https://github.com/esvianna/ZELO/issues/23) | Docs PROJECT_STATUS + ROADMAP alinhados |
| [#27](https://github.com/esvianna/ZELO/issues/27) | Persistir última view após F5 (PWA 107) |
| [#13](https://github.com/esvianna/ZELO/issues/13) | Auditoria permissões ops/swaps (2.11.8) |
| [#12](https://github.com/esvianna/ZELO/issues/12) | WhatsApp na escala — links wa.me (2.11.4 / PWA 105) |

### Destaques técnicos por versão

**Plugin 2.11.8:** supervisão por governança (fix homem-chave global); swaps filtrados; `docs/OPS-PERMISSIONS.md`.

**PWA 107:** `sessionStorage` + hash — restaura escala/mapa/perfil após F5.

**Plugin 2.11.7:** fix export PDF (`FPDF` margens PHP 8.2); rate limit só após export OK.

**Plugin 2.11.5–2.11.6:** PDF por faixa horária; governança compacta; página por dia.

**Plugin 2.11.4 + PWA 105:** links WhatsApp (voluntário + responsável, se telefone cadastrado).

**Plugin 2.11.3:** status de compromisso dos colegas visível na escala da equipa.

**Plugin 2.11.2:** `prior_commitment` em `schedule_changed` (auditoria).

**Plugin 2.11.1 / PWA 101:** reconciliação de compromissos + aviso «escala mudou» (ADR-019).

**Plugin 2.11.0 / PWA 99–100:** edição escala, modal Montar escala (ADR-018).

**PWA 102–106:** vista por turno, filtros combináveis, ícones compactos na linha.

---

## O que já foi implementado

### Backend (`zelo-assistente` v2.11.7)

- [x] Export PDF/CSV `GET /zelo/v1/ops/export` — agrupamento por faixa; responsável no turno; 3 dias com quebra de página
- [x] Payload `/ops/voluntarios`: `shift_contacts`, `volunteer_phone`, compromissos de colegas na escala
- [x] `POST /zelo/v1/ops/schedule` — merge por dia+turno; reconciliação de compromissos (ADR-019)
- [x] `zelo_edit_schedule` + escopos via governança; `reallocate` com supervisão na linha
- [x] E-mail cron `schedule_changed`; histórico ops (slice 15 na UI)
- [x] Tudo de 2.10.x (local no turno, horários por linha, catálogos, etc.)

### Frontend (PWA build 106)

- [x] Escala: vista **Por turno** e **Lista**; «Montar este turno» / editor Montar escala
- [x] Filtros: dia, turno, local, nome, idioma, responsável do turno; «Comigo neste turno»
- [x] Ações em ícones na linha (confirmar, check-in/out, realocar, swap); links WhatsApp
- [x] Minhas designações; aviso «escala alterada»; export PDF (gestor)
- [x] Home operacional, nav Operação, hub avisos, widget tempo, mapa indoor

### Governança docs

- [x] ADR-018, ADR-019, ADR-020; `TESTING.md` §4; `docs/GITHUB-WORKFLOW.md`

---

## O que está pendente

Priorização via [Project 3](https://github.com/users/esvianna/projects/3). Principais itens em **Backlog**:

| Prioridade | Item | Issue |
|------------|------|-------|
| **Alta** | Web Push VAPID + subscribe real (stub 501 hoje) | [#8](https://github.com/esvianna/ZELO/issues/8) |
| **Média** | Painel cobertura posto/idioma | [#10](https://github.com/esvianna/ZELO/issues/10) |
| **Média** | Motor notificações + inbox servidor | [#9](https://github.com/esvianna/ZELO/issues/9) |
| **Infra** | Rate limit REST, testes, env API | [#22](https://github.com/esvianna/ZELO/issues/22) … [#20](https://github.com/esvianna/ZELO/issues/20) |
| **UX visitante** | Programação, carrossel, branding, emergência | [#14](https://github.com/esvianna/ZELO/issues/14) … [#17](https://github.com/esvianna/ZELO/issues/17) |

---

## Próximos passos lógicos

1. Priorizar no Project: **#8** Web Push ou **#27** persistir view (UX rápida, só PWA).
2. Manter plugin **2.11.7** + PWA **106** alinhados em produção (`DEPLOYMENT_RULES.md`).
3. Smoke regressão escala + export PDF após cada deploy ops (`TESTING.md` §4).
4. Fechar itens de infra/UX conforme janela antes do próximo evento.

---

## Última sessão (2026-06-04)

- **Fluxo Project:** agentes param em **In review** após implementar; **Done** só após OK do responsável (`docs/GITHUB-WORKFLOW.md`, regras Cursor).
- **#24 / #25:** PWA 108 + plugin 2.11.9 entregues — **In review** (validar olho senha + perfil editável).
- **ZELO#13:** Plugin 2.11.8 — matriz `docs/OPS-PERMISSIONS.md`; supervisão por governança; swaps filtrados (ADR-021).

**Como testar:** abrir este arquivo e `ROADMAP.md`; confirmar versões batem com `zelo-assistente.php` e `zelo-build.js`; cruzar pendências com cards **Backlog** no Project 3.

## Sessão anterior (2026-06-04)

- Backlog oficial no GitHub Project 3; fluxo Backlog → plano → **Ready** → código (`docs/GITHUB-WORKFLOW.md`, ADR-020).
- Issues migradas de `SITE-NOVO-VTIS` para `esvianna/ZELO` (#1 escala, #2 e-mails).

## Sessão anterior (2026-06-02)

- Plugin 2.11.0: API escala escopada; PWA build 99: UX leitura + editor Montar escala.

**Como testar:** `TESTING.md` §4 (2, 2b–2c, 5, 5b–5c).
