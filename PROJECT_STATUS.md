# PROJECT_STATUS.md — Status do projeto ZELO

> **Arquivo principal de continuidade.** Atualize ao fim de cada sessão significativa de desenvolvimento.
>
> Última atualização: **2026-06-18** (fix save por aba **2.15.2** / #39).

---

## Onde paramos

O projeto está em **produção funcional** com foco operacional para o **departamento de informações** (voluntários), mantendo o app **público para visitantes ocasionais**.

| Referência no repo | Versão |
|--------------------|--------|
| Plugin WordPress (`zelo-assistente.php`) | **2.15.2** |
| PWA (`zelo-build.js` / `sw.js`) | **build 142** |

**Produção:** deploy **2.15.2** (abas JS + save por aba persiste dados).

**Backlog oficial:** [GitHub Project — Projeto ZELO](https://github.com/users/esvianna/projects/3) — issues em [`esvianna/ZELO`](https://github.com/esvianna/ZELO) (ADR-020, `docs/GITHUB-WORKFLOW.md`). Este arquivo **complementa** o quadro; status canônico das tarefas está no Project.

### Entregas recentes (Done no Project / issues fechadas)

| Issue | Entrega |
|-------|---------|
| [#35](https://github.com/esvianna/ZELO/issues/35) | Link/botão instruções Imprensa (PWA 140) — **Done** GitHub 18/06 |
| [#34](https://github.com/esvianna/ZELO/issues/34) | Info evento secções opcionais + imprensa (PWA 138 + plugin 2.13.8) — **Done** GitHub 18/06 |
| [#33](https://github.com/esvianna/ZELO/issues/33) | Layout superior escala (PWA 137) |
| [#32](https://github.com/esvianna/ZELO/issues/32) | Add/edit linha escala modal (PWA 136) — **Done** GitHub 12/06 |
| [#31](https://github.com/esvianna/ZELO/issues/31) | Excluir linha recusada (PWA 134 + plugin 2.13.7) |
| [#30](https://github.com/esvianna/ZELO/issues/30) | Designações home/escala — filtro e arquivo (PWA 133–135) |
| [#29](https://github.com/esvianna/ZELO/issues/29) | Substituições legíveis (PWA 132) |
| [#28](https://github.com/esvianna/ZELO/issues/28) | Mapa do evento — admin + PWA diagrama, offline (2.12.x, PWA 110–125) |
| [#26](https://github.com/esvianna/ZELO/issues/26) | Novidades/blog WP — card Operação, offline detalhe (2.13.x, PWA 116–125) |
| [#27](https://github.com/esvianna/ZELO/issues/27) | Persistir última view após F5 (PWA 107) |
| [#1](https://github.com/esvianna/ZELO/issues/1)–[#22](https://github.com/esvianna/ZELO/issues/22) | Pacote escala, export, filtros, e-mails, rate limit, etc. (ver histórico) |

### Descartado (decisão de produto)

| Issue | Motivo |
|-------|--------|
| [#8](https://github.com/esvianna/ZELO/issues/8) Web Push VAPID | Supersedido por [#36](https://github.com/esvianna/ZELO/issues/36) (ADR-035). |
| [#10](https://github.com/esvianna/ZELO/issues/10) Cobertura posto/idioma | ADR-027 |
| [#9](https://github.com/esvianna/ZELO/issues/9) / [#16](https://github.com/esvianna/ZELO/issues/16) | ADR-028 |
| [#14](https://github.com/esvianna/ZELO/issues/14) Programação visitante | ADR-029 |
| [#18](https://github.com/esvianna/ZELO/issues/18) Branding splash/home | ADR-031 |
| [#20](https://github.com/esvianna/ZELO/issues/20) Testes automatizados | ADR-033 |

---

## O que já foi implementado (resumo por versão actual)

### Backend (`zelo-assistente` v2.15.2)

- [x] Fix save por aba: `call_user_func` → invocação directa (#39, 2.15.2)
- [x] Fix JS troca de abas admin (#39, 2.15.1)
- [x] Admin save **por aba** (#39, 2.15.0) — Config/push isolado; notice inline

- [x] Web Push VAPID — tabela subscriptions, admin Config, REST subscribe/status/test (#36, ADR-035)
- [x] Admin «Limpar duplicatas» na escala (#37)
- [x] Admin save parcial + VAPID separado + desvincular roster (#38)
- [x] Admin fix form aninhado + flash notice PRG (#38, 2.14.6)
- [x] Hotfix save: hidden `zelo_ops_tabs_save` + disable botão após submit + flash `user_meta` (#38, 2.14.7)
- [x] Fix nonce duplicado no form ops — «Este link expirou» (#38, 2.14.8)
- [x] Export PDF/CSV, escala merge, reconciliação compromissos, permissões ops (2.11.x+)
- [x] Novidades WP, mapa indoor, info evento toggles, rate limit login (2.12.x–2.13.x)

### Frontend (PWA build 142)

- [x] Web Push subscribe + Perfil + consentimento (#36)
- [x] Imprensa/autoridades atalhos (#35); info evento condicional (#34)
- [x] Modal add/edit linha escala (#32); layout toolbar escala (#33)
- [x] Home designações só acções pendentes; escala filtro/arquivo (#30)
- [x] Substituições legíveis (#29); escala por turno, filtros, WhatsApp, offline

---

## O que está pendente

Todas as issues **abertas** no GitHub (18/06):

| Issue | Entrega | Project | Smoke |
|-------|---------|---------|-------|
| [#39](https://github.com/esvianna/ZELO/issues/39) | Admin save por aba + fix abas | **In review** | §4 **5n12–5n13** |
| [#38](https://github.com/esvianna/ZELO/issues/38) | Admin save ops (fixes 2.14.x) | **In review** | §4 **5n7–5n11** |
| [#37](https://github.com/esvianna/ZELO/issues/37) | Limpar duplicatas escala (admin) | **In review** | §4 **5n6** |
| [#36](https://github.com/esvianna/ZELO/issues/36) | Web Push VAPID (PWA 142 + plugin 2.14.x) | **In review** | §15 |

**Ops / conteúdo (sem issue aberta):** post Novidades slug `imprensa-autoridades`; config Curitiba/2026 (desactivar transporte/Wi‑Fi/credenciamento; activar imprensa).

---

## Próximos passos lógicos

1. **Deploy plugin 2.15.2** — fix abas + save persiste (#39, §4 **5n12–5n13**).
2. Smoke `TESTING.md` §15 (#36 Web Push) e §4 **5n6** (#37).
3. Validar escala admin ↔ PWA após save OK (`GET /ops/voluntarios` vs admin F5).
4. Criar post `imprensa-autoridades` quando conteúdo estiver pronto.

---

## Última sessão (2026-06-18)

- **#39 / plugin 2.15.2:** `call_user_func()` em PHP 8 não passa `&$data` — save por aba não persistia; invocação directa `$handler( $data )`.

## Sessão anterior (2026-06-18)

- **#39 / plugin 2.15.1:** fix `SyntaxError` JS (`tab-*` sem aspas) — abas só mudavam hash; `zeloOpsActivateTabFromHash`; §4 **5n13**.

## Sessão anterior (2026-06-18 — noite)

- **#39 / plugin 2.15.0:** save por aba; §4 **5n12**.

## Sessão anterior (2026-06-18 — noite, cont.)

- **#38 / plugin 2.14.8:** «Este link expirou» — colisão de `_wpnonce` (tabs + dedupe no mesmo form desde 2.14.6); nomes únicos; §4 **5n11**.

## Sessão anterior (2026-06-18 — noite)

- **#38 / plugin 2.14.7:** hotfix hidden `zelo_ops_tabs_save`; §4 **5n10**.

## Sessão anterior (2026-06-18 — tarde)

- **Sync docs ↔ GitHub:** #32/#34/#35 **Done**; pendentes = #36–#38.
- **#38 / plugin 2.14.6:** form aninhado corrigido; PRG + transient flash; §4 **5n9**.

## Sessão anterior (2026-06-18 — manhã)

- **#38 / plugin 2.14.4–2.14.5:** save parcial; VAPID separado; `novalidate`; `TESTING.md` §4 **5n7–5n8**.
- **#37 / plugin 2.14.1–2.14.3:** dedupe escala; §4 **5n6**.
- **#36 / plugin 2.14.0–2.14.2 + PWA 142:** Web Push VAPID; ADR-035.

## Sessão anterior (2026-06-12)

- **#35 / PWA 140**, **#34 / PWA 138 + plugin 2.13.8**, **#33 / PWA 137 Done**, **#32 / PWA 136**, **#31 / PWA 134**, **#30 / PWA 133**.

**Como testar:** `TESTING.md` §4 (escala/admin), §15 (push), §12 (offline).
