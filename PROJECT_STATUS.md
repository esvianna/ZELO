# PROJECT_STATUS.md — Status do projeto ZELO

> **Arquivo principal de continuidade.** Atualize ao fim de cada sessão significativa de desenvolvimento.
>
> Última atualização: **2026-05-29** (export PDF fontes, i18n re-render).

---

## Onde paramos

O projeto está em **produção funcional** com foco operacional para o **departamento de informações** (voluntários), mantendo o app **público para visitantes ocasionais**.

**Pacote offline + i18n + escala (2.9.0 / PWA 89):** snapshots localStorage, escala por dia, export PDF servidor, auditoria i18n PT/EN/ES, ADR-015.

**Correção export PDF (2.9.3):** fontes FPDF em `inc/lib/font/` + `FPDF_FONTPATH` (deploy deve incluir a pasta `font/`).

**PWA build 94:** ajuste visual checkboxes de idioma (cadastro/perfil).

**Próximo:** deploy **plugin 2.9.3** + **PWA build 94**; validar export PDF e i18n home em produção; Web Push VAPID.

---

## O que já foi implementado

### Backend (`zelo-assistente` v2.9.3)

- [x] Tudo de 2.8.0 (idiomas perfil, compromisso, onboarding, etc.)
- [x] `GET /ops/export` PDF/CSV — `inc/volunteer-ops-export.php`, FPDF vendored
- [x] Permissão export: `zelo_manage_ops`

### Frontend (PWA build 94)

- [x] Snapshots: `zelo_locais`, `zelo_volunteer_ops`, badges stale
- [x] Avatar: `default-avatar.png` + precache SW + onerror fallback
- [x] Escala: agrupamento por dia, tabela responsiva, export PDF
- [x] i18n: escala, auth, sessão, rede, ops (PT/EN/ES); re-render dinâmico na troca de idioma
- [x] Home «Mais opções» expandida por defeito

### Governança docs

- [x] ADR-015; TESTING §12–§13; DEPLOY atualizado (export)

---

## O que está pendente

| Prioridade | Item | Notas |
|------------|------|-------|
| **Alta** | Deploy plugin 2.9.3 + PWA 94 | Export PDF + i18n + cadastro checkboxes |
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

## Última sessão (2026-05-28)

- Offline-first: `api-v5.js` snapshots + `app-v5.js` stale UI.
- Avatar default + precache; cache best-effort same-origin.
- Escala UX por dia + export PDF servidor.
- i18n auditoria completa nas telas auditadas.
- Documentação: ADR-015, CHANGELOG, TESTING §12–§13, SECURITY.

**Como testar:** `TESTING.md` §12 (offline), §13 (i18n), §9–§11 (ops), export em §7.
