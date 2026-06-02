# PROJECT_STATUS.md — Status do projeto ZELO

> **Arquivo principal de continuidade.** Atualize ao fim de cada sessão significativa de desenvolvimento.
>
> Última atualização: **2026-06-02** (escala horário customizado por linha).

---

## Onde paramos

O projeto está em **produção funcional** com foco operacional para o **departamento de informações** (voluntários), mantendo o app **público para visitantes ocasionais**.

**Plugin 2.10.0:** escala com `start`/`end` por linha (admin + validação dentro do turno); ADR-016.

**PWA build 98:** tabela da escala ordenada por horário de início.

**Anterior:** 2.9.x export PDF, onboarding, cards home (build 97).

**Próximo:** deploy **plugin 2.10.0** + **PWA build 98**; smoke escala com sub-faixas; Web Push VAPID.

---

## O que já foi implementado

### Backend (`zelo-assistente` v2.10.0)

- [x] Escala: horário customizado por linha (`schedule.start`/`end`), validação bounds + duplicata com faixa horária
- [x] Tudo de 2.9.x (export PDF, onboarding, idiomas perfil, compromisso, etc.)

### Frontend (PWA build 98)

- [x] Escala detalhada: ordenação por `start` dentro do dia
- [x] Snapshots: `zelo_locais`, `zelo_volunteer_ops`, badges stale
- [x] Avatar: `default-avatar.png` + precache SW + onerror fallback
- [x] Escala: agrupamento por dia, tabela responsiva, export PDF
- [x] i18n: escala, auth, sessão, rede, ops (PT/EN/ES); re-render dinâmico na troca de idioma
- [x] Home «Mais opções» expandida por defeito

### Governança docs

- [x] ADR-015/016; TESTING §3/§12–§13; DEPLOY atualizado

---

## O que está pendente

| Prioridade | Item | Notas |
|------------|------|-------|
| **Alta** | Deploy plugin 2.10.0 + PWA 98 | Escala sub-faixas + smoke TESTING §3 |
| **Média** | Web Push VAPID + subscribe real | Stub 501 hoje |
| **Baixa** | Filtros escala avançados + WhatsApp (B1.1, B1.3) | Após PDF |
| **Baixa** | Inbox avisos servidor (B10) | Fase 2 UX |

---

## Próximos passos lógicos

1. Publicar plugin 2.9.0 e PWA 89 (cache `zelo-cache-v89`).
2. Smoke offline: login → escala → avião → badge + dados.
3. Smoke export PDF como gestor (`zelo_manage_ops`).
4. Config admin evento/roster/escala conforme `docs/DEPLOY-ZELO-PWA.md`.

---

## Última sessão (2026-06-02)

- Plugin 2.10.0: `start`/`end` por linha da escala; admin inputs time; validação bounds e duplicata com horário.
- PWA build 98: ordenação da escala por início; cache alinhado.

**Como testar:** `TESTING.md` §3 (7a–7c), §4 (horários na PWA/check-in).

## Sessão anterior (2026-05-28)

- Offline-first: `api-v5.js` snapshots + `app-v5.js` stale UI.
- Avatar default + precache; cache best-effort same-origin.
- Escala UX por dia + export PDF servidor.
- i18n auditoria completa nas telas auditadas.
- Documentação: ADR-015, CHANGELOG, TESTING §12–§13, SECURITY.

**Como testar:** `TESTING.md` §12 (offline), §13 (i18n), §9–§11 (ops), export em §7.
