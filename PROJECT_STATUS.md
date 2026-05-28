# PROJECT_STATUS.md — Status do projeto ZELO

> **Arquivo principal de continuidade.** Atualize ao fim de cada sessão significativa de desenvolvimento.
>
> Última atualização: **2026-05-27** (Pacote A — mínimo viável voluntário).

---

## Onde paramos

O projeto está em **produção funcional** com foco operacional para o **departamento de informações** (voluntários), mantendo o app **público para visitantes ocasionais** (sem divulgação ativa).

**Pacote A implementado:** segurança ops, cache PWA v65, painel na home pós-login, nav Operação, escala `mine=1` para voluntário comum, badges de check-in.

**Próximo backlog sugerido (Pacote B):** restringir check-in à própria designação, export CSV, painel de cobertura.

---

## O que já foi implementado

### Backend (`zelo-assistente` v2.5.1)

- [x] Tudo do v2.5.0 (locais, categorias, auth, ops, swaps, cron).
- [x] Removido bypass público de `/ops/voluntarios`.

### Frontend (PWA build 65)

- [x] Painel operacional na home (`#home-volunteer-dashboard`).
- [x] Bottom nav **OPERAÇÃO** para utilizadores com `view_ops`.
- [x] `loadVolunteerOps()` com `mine=1` para voluntário comum.
- [x] Badges de status (pendente / no posto / saiu).
- [x] Secção visitante extra colapsável quando logado como voluntário.
- [x] Cache alinhado (build 65, `zelo-cache-v65`).

### Governança

- [x] AGENTS, STATUS, ROADMAP, CHANGELOG, DECISIONS, SECURITY, TESTING, regras Cursor.

---

## O que está pendente

| Prioridade | Item | Notas |
|------------|------|-------|
| **Alta** | Validar em produção (HTTPS same-origin) | `TESTING.md` checklist |
| **Média** | Pacote B: check-in só na própria designação | |
| **Média** | `/ops/export` CSV | Stub 501 |
| **Média** | Atualizar `README.md` | Ainda desatualizado |
| **Baixa** | `.gitignore` | |

---

## Próximos passos lógicos

1. Deploy plugin 2.5.1 + PWA build 65 no servidor.
2. Smoke test com perfis: anônimo, voluntário, supervisor.
3. Preencher escala com `wp_user_id` e roles Zelo.
4. Planejar Pacote B conforme data do evento.

---

## Riscos

| Risco | Severidade | Estado |
|-------|------------|--------|
| Bypass público ops | Crítica | **Resolvido** (2.5.1) |
| Cache desalinhado | Alta | **Resolvido** (build 65) |
| Check-in em designação alheia | Média | Aberto (Pacote B) |
| URL produção em `api-v5.js` | Média | Aberto |

---

## Última sessão

| Campo | Valor |
|-------|--------|
| Data | 2026-05-28 |
| Feito | PWA build 74: fix `_opsAuthFailed` após login; mensagem plugin 2.5.3+ |
| Build PWA | 74 |
| Plugin | 2.5.3 (branch local) |
| Próximo passo | Publicar `index.html` + `sw.js` + `zelo-build.js`; hard refresh / limpar cache SW |

---

## Como retomar em 30 segundos

1. Leia **Onde paramos** acima.
2. `git log -5` para commits recentes.
3. `TESTING.md` antes de cada evento.
4. `AGENTS.md` antes de codar.
