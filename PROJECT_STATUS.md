# PROJECT_STATUS.md — Status do projeto ZELO

> **Arquivo principal de continuidade.** Atualize ao fim de cada sessão significativa de desenvolvimento.
>
> Última atualização: **2026-05-28** (fluxo confirmação voluntários).

---

## Onde paramos

O projeto está em **produção funcional** com foco operacional para o **departamento de informações** (voluntários), mantendo o app **público para visitantes ocasionais**.

**Pacote confirmação voluntários (2.7.0 / PWA 81):** compromisso antecipado por designação, prazo e janelas de presença no admin, vínculo cadastro↔roster com aprovação, alerta supervisor na recusa, validação check-in/out, UI PWA e aba Onboarding.

**Próximo backlog:** deploy 2.7.0 + build 81; smoke `TESTING.md` §9; Web Push VAPID (Fase 3); export CSV.

---

## O que já foi implementado

### Backend (`zelo-assistente` v2.7.0)

- [x] `zelo_volunteer_commitments` + REST `/ops/assignments/{id}/commit`
- [x] `zelo_link_requests` + onboarding admin + matching cadastro por e-mail
- [x] Config: `commitment_deadline`, `presence`, supervisores com `wp_user_id`
- [x] Check-in/out validado (compromisso, dia, janela, titular/supervisor)
- [x] E-mails estendidos (compromisso pendente, 1 dia antes, check-in/out abertos)
- [x] Stub push; hook `zelo_notification_dispatch`

### Frontend (PWA build 81)

- [x] Aceitar/recusar turno; check-in/out com janelas; supervisor em nome
- [x] Hub avisos: `commitment-*`, `checkin-*`, `checkout-*`, vínculo pendente
- [x] Prompt notificações; SW handlers push (preparação)

### Governança docs

- [x] ADR-013; TESTING §9; ROADMAP pacote C

---

## O que está pendente

| Prioridade | Item | Notas |
|------------|------|-------|
| **Alta** | Deploy plugin 2.7.0 + PWA 81 | `TESTING.md` §9 |
| **Média** | Web Push VAPID + subscribe real | Stub 501 hoje |
| **Média** | `/ops/export` CSV | Stub 501 |
| **Baixa** | Inbox avisos servidor (B10) | Fase 2 UX |

---

## Próximos passos lógicos

1. Configurar no admin: datas evento (26–28/06), prazo compromisso (ex. 15/06), supervisores WP.
2. Preencher roster com `expected_email`; convidar cadastro; aprovar vínculos.
3. Smoke T1–T10 em staging.
4. Gerar VAPID e ativar push quando infra estiver pronta.

---

## Riscos

| Risco | Severidade | Estado |
|-------|------------|--------|
| Bypass público ops | Crítica | Resolvido (2.5.1) |
| Check-in em designação alheia | Média | **Resolvido** (2.7.0) |
| Push iOS só PWA instalada | Média | Documentar em deploy |

---

## Última sessão

| Campo | Valor |
|-------|--------|
| Data | 2026-05-28 |
| Feito | Fluxo confirmação voluntários Fases 0–2 + prep push/motor |
| Build PWA | **81** |
| Plugin | **2.7.0** |
| Próximo passo | Deploy + smoke §9 |

---

## Como retomar em 30 segundos

1. Leia **Onde paramos** acima.
2. `TESTING.md` §9 antes do evento.
3. Admin → Operação Voluntários → Config + Onboarding.
4. `AGENTS.md` + `DECISIONS.md` (ADR-013).
