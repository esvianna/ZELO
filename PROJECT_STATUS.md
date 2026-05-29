# PROJECT_STATUS.md — Status do projeto ZELO

> **Arquivo principal de continuidade.** Atualize ao fim de cada sessão significativa de desenvolvimento.
>
> Última atualização: **2026-05-28** (idiomas no perfil do voluntário).

---

## Onde paramos

O projeto está em **produção funcional** com foco operacional para o **departamento de informações** (voluntários), mantendo o app **público para visitantes ocasionais**.

**Pacote confirmação voluntários (2.7.0 / PWA 81–82):** compromisso antecipado, onboarding, check-in validado.

**UX escala Congresso (2.7.1 / PWA 83–84):** rótulos dia+data, governança 3 dias, turnos 07:00–18:30, guia em `docs/DEPLOY-ZELO-PWA.md`.

**Idiomas no perfil (2.8.0 / PWA 85):** roster + user_meta; escala sem coluna Idiomas; cadastro/perfil PWA; ADR-014.

**Próximo backlog:** deploy 2.8.0 + build 85; smoke §9–§11; Web Push VAPID.

---

## O que já foi implementado

### Backend (`zelo-assistente` v2.8.0)

- [x] `zelo_volunteer_commitments` + REST `/ops/assignments/{id}/commit`
- [x] `zelo_link_requests` + onboarding admin + matching cadastro por e-mail
- [x] Config: `commitment_deadline`, `presence`, supervisores com `wp_user_id`
- [x] Check-in/out validado (compromisso, dia, janela, titular/supervisor)
- [x] E-mails estendidos (compromisso pendente, 1 dia antes, check-in/out abertos)
- [x] Stub push; hook `zelo_notification_dispatch`
- [x] Rótulos dia+data; governança 3 dias; turnos Congresso; migração idempotente
- [x] Idiomas no perfil (roster + WP); herança na API; REST languages/profile

### Frontend (PWA build 85)

- [x] Aceitar/recusar turno; check-in/out com janelas; supervisor em nome
- [x] Hub avisos: `commitment-*`, `checkin-*`, `checkout-*`, vínculo pendente
- [x] Prompt notificações; SW handlers push (preparação)
- [x] `getOpsDayLabel` com data; filtros escala com data
- [x] Cadastro/perfil: idiomas opcionais

### Governança docs

- [x] ADR-013–014; TESTING §9–§11; guia Congresso em `docs/DEPLOY-ZELO-PWA.md`

---

## O que está pendente

| Prioridade | Item | Notas |
|------------|------|-------|
| **Alta** | Deploy plugin 2.8.0 + PWA 85 | `TESTING.md` §9–§11 |
| **Média** | Web Push VAPID + subscribe real | Stub 501 hoje |
| **Média** | `/ops/export` CSV | Stub 501 |
| **Baixa** | Inbox avisos servidor (B10) | Fase 2 UX |

### Melhorias UX (backlog — próximo pacote)

**1. Página da Escala completa**

| # | Item | Notas |
|---|------|-------|
| 1.1 | Novos filtros de pesquisa | Idioma (seleção), Responsável, Voluntário |
| 1.2 | Melhorar exibição da listagem | Alinhar ao PDF: agrupamento/visualização **por dia**; equipe acostumada com planilha por Sexta/Sábado/Domingo |
| 1.3 | Hiperlinks WhatsApp | Contato com responsáveis e voluntários via `tel`/`wa.me` usando telefone do cadastro/roster |

**2. Home — «Mais opções (cidade e mapas)»**

| # | Item | Notas |
|---|------|-------|
| 2.1 | Secção expandida por defeito | Exibir todos os botões (Cultura, mapas, etc.) sem precisar expandir manualmente |

---

## Próximos passos lógicos

1. Configurar no admin: datas evento (26–28/06), prazo compromisso (ex. 15/06), supervisores WP.
2. Preencher roster com `expected_email`; convidar cadastro; aprovar vínculos.
3. Smoke T1–T10 + §10 em staging.
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
| Feito | Idiomas no perfil voluntário (roster, API, PWA cadastro/perfil) |
| Build PWA | **85** |
| Plugin | **2.8.0** |
| Próximo passo | Deploy + smoke TESTING §11 |

---

## Como retomar em 30 segundos

1. Leia **Onde paramos** acima.
2. `TESTING.md` §9–§11 antes do evento.
3. Admin → Operação Voluntários → Config + Onboarding.
4. `AGENTS.md` + `DECISIONS.md` (ADR-013).
