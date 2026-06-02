# PROJECT_STATUS.md — Status do projeto ZELO

> **Arquivo principal de continuidade.** Atualize ao fim de cada sessão significativa de desenvolvimento.
>
> Última atualização: **2026-06-02** (escala PWA: leitura equipa + edição por responsáveis).

---

## Onde paramos

O projeto está em **produção funcional** com foco operacional para o **departamento de informações** (voluntários), mantendo o app **público para visitantes ocasionais**.

**Plugin 2.11.0:** `POST /ops/schedule` (edição escopada); escala completa em leitura para voluntários logados; cap `zelo_edit_schedule`; ADR-018.

**PWA build 99:** «Minhas designações» em destaque; escala da equipa com filtros; editor «Montar escala» para responsáveis.

**Anterior:** 2.10.x local no turno + sub-faixas horárias; build 98 ordenação por `start`.

**Próximo:** deploy **plugin 2.11.0** + **PWA build 99**; smoke TESTING §4 (2b–5c); Web Push VAPID.

---

## O que já foi implementado

### Backend (`zelo-assistente` v2.11.0)

- [x] `POST /zelo/v1/ops/schedule` — merge por dia+turno, validação, histórico, limpeza compromissos/check-ins
- [x] `zelo_edit_schedule` + escopos via governança (`zelo_user_can_supervise_assignment`)
- [x] Payload `permissions` + catálogos editor; escala completa para `zelo_view_ops`
- [x] `reallocate` com checagem de supervisão na linha
- [x] Tudo de 2.10.x (local no turno, horários por linha, export PDF, etc.)

### Frontend (PWA build 99)

- [x] Voluntário logado: escala completa só leitura + destaque nas próprias linhas
- [x] Filtros: dia, turno, local, nome, «Comigo neste turno»
- [x] Editor «Montar escala» (responsáveis com `schedule_edit.enabled`)
- [x] Tudo de build 98 (ordenação, snapshots, export PDF, i18n)

### Governança docs

- [x] ADR-018; TESTING §4 ampliado; AGENTS.md rota `/ops/schedule`

---

## O que está pendente

| Prioridade | Item | Notas |
|------------|------|-------|
| **Alta** | Deploy plugin 2.11.0 + PWA 99 | Governança preenchida antes do evento |
| **Média** | Web Push VAPID + subscribe real | Stub 501 hoje |
| **Baixa** | WhatsApp / filtros avançados escala | Backlog UX |

---

## Próximos passos lógicos

1. Publicar plugin 2.11.0 e PWA 99 (`zelo-cache-v99`).
2. Admin: governança (homens-chave + supervisores) + turnos com local.
3. Smoke: voluntário vê equipa; homem-chave monta A1 na PWA.
4. Smoke export PDF como gestor.

---

## Última sessão (2026-06-02)

- Plugin 2.11.0: API escala escopada; permissões e leitura completa; `inc/volunteer-ops-schedule.php`.
- PWA build 99: UX leitura + editor Montar escala; i18n PT/EN/ES.

**Como testar:** `TESTING.md` §4 (2, 2b–2c, 5, 5b–5c).
