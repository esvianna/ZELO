# PROJECT_STATUS.md — Status do projeto ZELO

> **Arquivo principal de continuidade.** Atualize ao fim de cada sessão significativa de desenvolvimento.
>
> Última atualização: **2026-06-02** (vista escala por turno na PWA).

---

## Onde paramos

O projeto está em **produção funcional** com foco operacional para o **departamento de informações** (voluntários), mantendo o app **público para visitantes ocasionais**.

**PWA build 102:** escala «Por turno» (faixas horárias + voluntários juntos); toggle Lista; Minhas designações em cards; «Montar este turno».

**Plugin 2.11.2:** `prior_commitment` preservado ao marcar `schedule_changed` (auditoria do aceite anterior).

**Plugin 2.11.1 / PWA 101:** reconciliação compromissos + aviso «escala mudou» (ADR-019).

**Plugin 2.11.0 / PWA 99–100:** edição escala, modal Montar escala (ADR-018).

**Próximo:** deploy **plugin 2.11.1** + **PWA build 102**; smoke TESTING §4 (5h–5k); Web Push VAPID. Backlog: PDF export agrupado por faixa.

---

## O que já foi implementado

### Backend (`zelo-assistente` v2.11.1)

- [x] `POST /zelo/v1/ops/schedule` — reconciliação por fingerprint; `schedule_changed` só quando a designação mudou
- [x] E-mail cron `schedule_changed` para reconfirmação

### Backend (`zelo-assistente` v2.11.0)

- [x] `POST /zelo/v1/ops/schedule` — merge por dia+turno, validação, histórico
- [x] `zelo_edit_schedule` + escopos via governança (`zelo_user_can_supervise_assignment`)
- [x] Payload `permissions` + catálogos editor; escala completa para `zelo_view_ops`
- [x] `reallocate` com checagem de supervisão na linha
- [x] Tudo de 2.10.x (local no turno, horários por linha, export PDF, etc.)

### Frontend (PWA build 102)

- [x] Vista escala por turno (dia → turno → faixa → voluntários); toggle Lista; Montar este turno no card

### Frontend (PWA build 101)

- [x] Avisos e badge «escala alterada — confirme» quando `pending_reason === schedule_changed`

### Frontend (PWA build 99–100)

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
