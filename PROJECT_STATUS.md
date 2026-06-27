# PROJECT_STATUS.md — Status do projeto ZELO

> **Arquivo principal de continuidade.** Atualize ao fim de cada sessão significativa de desenvolvimento.
>
> Última atualização: **2026-06-27** — #66 legenda overlay reserva 20 mm plugin **2.26.4** **In review**.

---

## Onde paramos

O projeto está em **produção funcional** com foco operacional para o **departamento de informações** (voluntários), mantendo o app **público para visitantes ocasionais**.

| Referência no repo | Versão |
|--------------------|--------|
| Plugin WordPress (`zelo-assistente.php`) | **2.26.4** |
| PWA (`zelo-build.js` / `sw.js`) | **build 180** |

**Produção (repo):** PWA **151** validada (#45–#50, #46); plugin **2.18.0** + **2.19.0** / PWA **152** aguardam deploy/smoke (#44, #51).

### Entregas recentes (Done no Project / issues fechadas)

| Issue | Entrega |
|-------|---------|
| [#50](https://github.com/esvianna/ZELO/issues/50) | ADR-038 P3 — `refreshSession` paralelo (PWA 151) — **Done** smoke §12b |
| [#49](https://github.com/esvianna/ZELO/issues/49) | ADR-038 P2 — banner rede degradada + i18n (PWA 151) — **Done** smoke §12b |
| [#48](https://github.com/esvianna/ZELO/issues/48) | ADR-038 P1 — `init` allSettled + hidratação (PWA 151) — **Done** smoke §12b |
| [#47](https://github.com/esvianna/ZELO/issues/47) | ADR-038 P0 — SWR + timeout `api-v5.js` (PWA 151) — **Done** smoke §12b |
| [#46](https://github.com/esvianna/ZELO/issues/46) | Auditoria offline / rede lenta + ADR-038 — **Done** |
| [#45](https://github.com/esvianna/ZELO/issues/45) | Info Posto Médico — título acima do texto (PWA 150) — **Done** smoke §6p6 |
| [#43](https://github.com/esvianna/ZELO/issues/43) | Excluir linha escala pending/accepted na lista (PWA 148) — **Done** smoke §5m2 |
| [#42](https://github.com/esvianna/ZELO/issues/42) | Reset push + consent v3 + fingerprint (plugin 2.17.0 / PWA 147) — **Done** smoke §15.9–15.12 |
| [#41](https://github.com/esvianna/ZELO/issues/41) | Aprovação cadastro voluntários (plugin 2.16.0 / PWA 146–149) — **Done** smoke §16 |
| [#36](https://github.com/esvianna/ZELO/issues/36) | Web Push VAPID + subscribe PWA (plugin 2.14.x / PWA 142+) — **Done** smoke §15 |
| Issue | Entrega |
|-------|---------|
| [#40](https://github.com/esvianna/ZELO/issues/40) | Ícones PWA — favicon coração + manifest wordmark; atalho desktop (PWA 145) — **Done** Project 18/06 |
| [#39](https://github.com/esvianna/ZELO/issues/39) | Admin save por aba + fix abas (plugin 2.15.0–2.15.2) — **Done** Project 18/06 |
| [#38](https://github.com/esvianna/ZELO/issues/38) | Admin save ops — fixes 2.14.4–2.14.8 — **Done** Project 18/06 |
| [#37](https://github.com/esvianna/ZELO/issues/37) | Limpar duplicatas escala admin (plugin 2.14.1–2.14.3) — **Done** Project 18/06 |
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

**Backlog oficial:** [GitHub Project — Projeto ZELO](https://github.com/users/esvianna/projects/3) — issues em [`esvianna/ZELO`](https://github.com/esvianna/ZELO) (ADR-020, `docs/GITHUB-WORKFLOW.md`). Este arquivo **complementa** o quadro; status canônico das tarefas está no Project.

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

### Backend (plugin 2.20.0)

- [x] SMS Comtele (#54, ADR-040): cliente V4, fila, cron paralelo, admin Config

### Backend (plugin 2.21.5)

- [x] Lembretes manuais + aviso recusas por turno (#59): REST remind-pending / notify-declines, cooldown, histórico ops

### Backend (plugin 2.21.0)

- [x] Swap notificações (#56, ADR-041): recusa c/ justificativa, aprovar c/ roster WP, e-mail/SMS imediato

### Frontend (PWA build 171)

- [x] Botões «Lembrar pendentes» e «Avisar responsável (recusas)» no card do turno (#59)

### Frontend (PWA build 165)

- [x] Swap modais recusar/aprovar + avisos sino (#56)

### Frontend (PWA build 164)

- [x] Mapa evento — flicker Diagrama (#55): `is-preparing`, layout único, patch seleção

### Frontend (PWA build 163)

- [x] Mapa evento — aba Diagrama sem flicker (#55): `_syncIndoorTabDom`, zoom in-place

### Frontend (PWA build 162)

- [x] Aviso LGPD telefone/SMS no cadastro e perfil (#54)

### Frontend (PWA build 161)

- [x] Banner «conexão lenta» preso: só locais/evento/ops; retry online/visibility; limpa flag em auth

### Frontend (PWA build 160)

- [x] Escala: filtros «Buscar por nome» / «Filtrar idioma» mantêm foco ao digitar (atualiza só `#ops-schedule-by-day`)

### Frontend (PWA build 152)

- [ ] Registro apoio delegados (#51, ADR-039): formulário + lista/export gestor — **In review**

### Frontend (PWA build 151)

- [x] Rede degradada SWR + timeout (#47–#50, ADR-038): `fetchWithStaleFallback`, banner, init não bloqueante
- [x] Info Posto Médico (#45, 150)
- [x] Push consent v3 + fingerprint + logout unsub + re-subscribe VAPID (#42)
- [x] Cadastros pendentes admin + hotfix navegação (#41, 149)
- [x] Lixeira escala pending/accepted na lista (#43, 148)

### Backend (`zelo-assistente` v2.19.0)

- [ ] Registro apoio delegados (#51) — REST + export CSV/PDF — **In review**

### Backend (`zelo-assistente` v2.18.0)

- [ ] E-mails digest + fila (#44) — **In review**, smoke pendente

### Backend (`zelo-assistente` v2.17.0)

- [x] Reset push (#42): truncate ao regenerar VAPID; botão limpar subscriptions; fingerprint REST; histórico ops
- [x] Aprovação voluntários (#41): fila pós-e-mail; REST approve/reject; migração legados

### Frontend (PWA build 149)

- [x] Push consent v3 + fingerprint + logout unsub + re-subscribe VAPID (#42)
- [x] Cadastros pendentes admin + hotfix navegação (#41, 149)
- [x] Lixeira escala pending/accepted na lista (#43, 148)

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

### Frontend (PWA build 145)

- [x] Ícones PWA: manifest/atalho = wordmark `logo-zelo.png`; favicon aba = coração (#40, 145)
- [x] Web Push subscribe + Perfil + consentimento (#36)
- [x] Imprensa/autoridades atalhos (#35); info evento condicional (#34)
- [x] Modal add/edit linha escala (#32); layout toolbar escala (#33)
- [x] Home designações só acções pendentes; escala filtro/arquivo (#30)
- [x] Substituições legíveis (#29); escala por turno, filtros, WhatsApp, offline

---

## O que está pendente

| Issue | Estado | Notas |
|-------|--------|-------|
| [#66](https://github.com/esvianna/ZELO/issues/66) | **In review** | PDF legenda overlay reserva 20 mm — plugin **2.26.4**; smoke **2z7j** |
| [#65](https://github.com/esvianna/ZELO/issues/65) | **In review** | SMS novidades posts — plugin **2.25.0** / PWA **180**; smoke **S65a–c** |
| [#64](https://github.com/esvianna/ZELO/issues/64) | **In review** | PDF layout 2.24.5 + legenda 32 mm — plugin **2.24.8**; smoke **2z7i** |
| [#63](https://github.com/esvianna/ZELO/issues/63) | **In review** | PDF pinos numerados — plugin **2.24.1**; smoke **2z7b** |
| [#62](https://github.com/esvianna/ZELO/issues/62) | **In review** | Export PDF mapa evento — plugin **2.24.0**; smoke **2z7** |
| [#61](https://github.com/esvianna/ZELO/issues/61) | **In review** | Mapa evento pinos legíveis — plugin 2.23.3 / PWA 179; smoke 2z2–2z6b |
| [#60](https://github.com/esvianna/ZELO/issues/60) | **In review** | Voluntários extras Pool B fase 2 — plugin 2.23.0 / PWA 174; smoke 60a–60h |

### Validadas no Project (Done) — sync 2026-06-23

| Issue | Entrega |
|-------|---------|
| [#44](https://github.com/esvianna/ZELO/issues/44) | Digest e-mail + fila — plugin 2.18.0 |
| [#51](https://github.com/esvianna/ZELO/issues/51) | Registro apoio delegados — plugin 2.19.0 / PWA 152–154 |
| [#52](https://github.com/esvianna/ZELO/issues/52) | Auditoria pt_br — PWA 158 |
| [#53](https://github.com/esvianna/ZELO/issues/53) | Novidades/push live refresh — PWA 155–159 |
| [#54](https://github.com/esvianna/ZELO/issues/54) | SMS Comtele — plugin 2.20.1 / PWA 162 |
| [#55](https://github.com/esvianna/ZELO/issues/55) | Mapa evento diagrama — PWA 164 |
| [#56](https://github.com/esvianna/ZELO/issues/56) | Swap modais + notificar — plugin 2.21.x / PWA 165–168 |
| [#57](https://github.com/esvianna/ZELO/issues/57) | Selectors voluntário optgroup — plugin 2.21.4 / PWA 169 |
| [#58](https://github.com/esvianna/ZELO/issues/58) | Menu ops + fix mapa — PWA 170 |
| [#59](https://github.com/esvianna/ZELO/issues/59) | Lembrete manual + aviso recusas — plugin 2.21.5 / PWA 171 |

**Conteúdo operacional Curitiba/2026** (imprensa, toggles evento, lembrete antecipado, post `imprensa-autoridades`): funcionalidade entregue (#34, #35, Config ops); configuração no WP — confirmada pelo responsável.

---

## Próximos passos lógicos

1. Smoke **#66** PDF (`TESTING.md` **2z7j**) após deploy plugin **2.26.4**.
2. Smoke **#65** SMS novidades (`TESTING.md` **S65a–c**) após deploy plugin **2.25.0**.
3. Smoke **#63** PDF numerado (`TESTING.md` **2z7b**) após deploy plugin **2.24.1**.
4. Smoke **#61** pinos/legenda PWA + admin (`TESTING.md` **2z2–2z6b**) após deploy plugin **2.23.3** + PWA **179**.
5. Smoke **#60** fase 2 (lote, SMS responsável, encerrar/atendido, PDF Share) após deploy plugin **2.23.0** + PWA **174**.
6. Nova demanda → issue no Project 3 (Backlog) antes de codificar.

---

## Última sessão (2026-06-25)

- **#66:** Título fixo + legenda overlay sem cobrir «LEGENDA (FACILIDADES)» — plugin **2.26.3** — **In review**; smoke **2z7j**.
- **#65:** SMS novidades — checkbox «Enviar SMS» + Comtele em lotes — plugin **2.25.0** / PWA **180** — **In review**; smoke **S65a–c**.

## Sessão anterior (2026-06-25)

- **#64:** revert layout 2.24.5 (mapa ~253 mm, números vetor) + legenda coluna ~32 mm em X=257 — plugin **2.24.8** — **In review**; smoke **2z7i**.

## Sessão anterior (2026-06-24)

- **#63:** PDF pinos numerados — plugin **2.24.1** — **In review**; smoke **2z7b**.

## Sessão anterior (2026-06-22)

- **#59:** lembrete manual a pendentes + aviso responsável sobre recusas — `zelo-ops-shift-notify.php`; REST `remind-pending` / `notify-declines`; botões no card do turno (PWA 171); plugin **2.21.5** — **In review**; smoke `TESTING.md` 59a–59e.

## Sessão anterior (2026-06-20)

- **#51 (ADR-039):** registro apoio delegados — backend `zelo-delegate-support-reports.php`; PWA 152–154 (formulário + lista/export + UX tabela); strings pt-BR; **In review**.
- **PWA 154:** lista «Registros — apoio a delegados» — cards empilhados no mobile (rótulo por campo); desktop com bordas, padding e scroll horizontal.
- **#53 (PWA 155–159):** novidades/push — SWR + refresh live; **159** corrige banner «Atualizando» preso + `forceFresh` na view Novidades.
- **#52 (PWA 158):** auditoria pt_br — 40+ strings pt-PT→pt-BR (push, rede, ops, news); `index.html` + `api-v5.js` — **In review**.
- **Validação smoke:** #45–#50, #46 — **Done** no Project (§6p6, §12b, §12 O1–O6).
- **#44 (plugin 2.18.0):** digest e-mail — **In review**, smoke pendente.
- Versões repo: plugin **2.19.0**, PWA **152**.

## Sessão anterior (2026-06-19)

- **Validação smoke:** #36 (Web Push), #41 (aprovação voluntários), #42 (reset push), #43 (excluir linha escala) — **Done** no Project; issues fechadas.
- Versões validadas: plugin **2.17.0**, PWA **149** (incl. hotfix #41 em 149 e #43 em 148).
- **#40 / PWA 145:** **Done** — smoke §7 **7n1** OK; favicon coração na aba; manifest wordmark; atalho desktop Windows via «Alterar ícone» quando `.lnk` mostra «Z» (limitação Chrome/shell).
- **Validação anterior:** **Done** [#37](https://github.com/esvianna/ZELO/issues/37), [#38](https://github.com/esvianna/ZELO/issues/38), [#39](https://github.com/esvianna/ZELO/issues/39).

## Sessão anterior (2026-06-18)

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
