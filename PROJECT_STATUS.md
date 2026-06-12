# PROJECT_STATUS.md — Status do projeto ZELO

> **Arquivo principal de continuidade.** Atualize ao fim de cada sessão significativa de desenvolvimento.
>
> Última atualização: **2026-06-12** (PWA 133 — designações home/escala, #30).

---

## Onde paramos

O projeto está em **produção funcional** com foco operacional para o **departamento de informações** (voluntários), mantendo o app **público para visitantes ocasionais**.

| Referência no repo | Versão |
|--------------------|--------|
| Plugin WordPress (`zelo-assistente.php`) | **2.13.6** |
| PWA (`zelo-build.js` / `sw.js`) | **build 133** |

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
| [#26](https://github.com/esvianna/ZELO/issues/26) | Novidades/blog WP — card Operação, offline detalhe (2.13.x, PWA 116–125) |
| [#28](https://github.com/esvianna/ZELO/issues/28) | Mapa do evento — admin + PWA diagrama, offline (2.12.x, PWA 110–125) |
| [#15](https://github.com/esvianna/ZELO/issues/15) | Carrossel novidades home — posts WP + scroll-snap (2.13.3, PWA 126) |
| [#17](https://github.com/esvianna/ZELO/issues/17) | Emergência — hierarquia + contatos 190/192/193 (2.13.4, PWA 128) |
| [#22](https://github.com/esvianna/ZELO/issues/22) | Rate limit login REST — 30/15 min IP + 10/15 min user (2.13.5) |
| [#21](https://github.com/esvianna/ZELO/issues/21) | Config API — login centralizado; docs same-origin (PWA 129) |

### Descartado (decisão de produto)

| Issue | Motivo |
|-------|--------|
| [#8](https://github.com/esvianna/ZELO/issues/8) Web Push VAPID | Voluntários usam a PWA; avisos in-app (#26) bastam — ADR-026. Stub 501 mantido. |
| [#10](https://github.com/esvianna/ZELO/issues/10) Cobertura posto/idioma | Escala + filtros (#11), export PDF e admin presença bastam — ADR-027. |
| [#9](https://github.com/esvianna/ZELO/issues/9) Motor notificações | Hub sino + e-mail cron + localStorage bastam — ADR-028. |
| [#16](https://github.com/esvianna/ZELO/issues/16) Inbox servidor | Descartado com #9 — ADR-028. |
| [#14](https://github.com/esvianna/ZELO/issues/14) Programação visitante | JW Library + impresso no evento — ADR-029. |
| [#18](https://github.com/esvianna/ZELO/issues/18) Branding splash/home | Banner evento + admin logo/foto bastam — ADR-031. |
| [#20](https://github.com/esvianna/ZELO/issues/20) Testes automatizados | `TESTING.md` manual + smoke pré-evento bastam — ADR-033. |

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

- [#30](https://github.com/esvianna/ZELO/issues/30) — designações home/escala: **In review** (PWA 133).
- [#29](https://github.com/esvianna/ZELO/issues/29) — visualização substituições: **In review** (PWA 132).

---

## Próximos passos lógicos

1. Validar smoke `TESTING.md` §4 **5l–5l5** (#30) e deploy PWA **133**.
2. Deploy PWA **132** (#29 — substituições legíveis) se ainda pendente.
3. Deploy plugin **2.13.6** se ainda pendente (avatar).

---

## Última sessão (2026-06-12)

- **#30 / PWA 133:** home «Minhas designações» só com ações pendentes; empty state «Tudo em dia»; escala com filtro por estado e seção colapsável «Recusadas e encerradas»; badges presença corrigidos; i18n pt/en/es; `TESTING.md` §4 **5l–5l5**.

## Sessão anterior (2026-06-10)

- **#29 / PWA 132:** pedidos de substituição legíveis — painel escala, sino avisos e histórico ops (nome, dia/turno/local, motivo, data); i18n pt/en/es; `TESTING.md` §4 **6a–6c**.
- **Fix avatar perfil:** PWA **130** + plugin **2.13.6** — preview local, cache-bust, msg junto ao avatar; backend fallback URL attachment.
- **#20 Testes automatizados descartado (ADR-033):** `TESTING.md` manual bastam; issue fechada; **Done** no Project.
- **#18 Branding descartado (ADR-031):** banner + admin bastam; issue fechada; **Done** no Project.
- **#22 Rate limit:** plugin **2.13.5** deploy OK; issue fechada; **Done** no Project.
- **#17 Emergência:** plugin **2.13.4** + PWA **128** — smoke OK; **Done** no Project; issue fechada.
- **#15 Carrossel novidades (ADR-030):** plugin **2.13.3** + PWA **126** — smoke OK; **Done** no Project; issue fechada.
- **#14 Programação descartada (ADR-029):** JW Library + impresso; **Done** no Project.
- **#9 / #16 descartados (ADR-028):** hub sino + cron e-mail + localStorage; issues fechadas; **Done** no Project.
- **#10 cobertura descartado (ADR-027):** filtros escala + export + admin presença bastam; issue fechada; **Done** no Project.
- **#8 Web Push descartado (ADR-026):** notificações via PWA in-app (#26); issue fechada; **Done** no Project; stub `/ops/push/subscribe` mantido.
- **Mapa evento UX:** PWA **125** — viewport do diagrama edge-to-edge; **124** — header/bottom nav no Diagrama.
- **ADR-025 / novidades detalhe offline:** PWA **123** — snapshot `zelo_news_item_v1_*`, prefetch ao listar, badge stale, mensagem amigável; teste O6.
- **ADR-024 / mapa evento offline:** PWA **122** — snapshot `zelo_indoor_map`, prefetch imagem same-origin no SW, badge stale, carregamento no `init()`; teste `TESTING.md` O5.
- **UX home designações:** PWA **121** — aviso «escala alterada» acima dos botões; mobile: Aceitar/Não posso empilhados largura total.
- **#26 UX home:** PWA **120** — rótulo do card na secção Operação: «Novidades».
- **#26 fix título:** plugin **2.13.2** + PWA **118** — `zelo_news_plain_text()` decodifica entidades e normaliza travessões → `-`; frontend `formatPlainText`/`decodeHtmlEntities`; cache API `v2` + snapshot cliente `zelo_news_v2_*`.
- **#26 fix anterior:** plugin **2.13.1** + PWA **117** — vídeos responsivos; cache `/news` ao gravar post.
- **#26:** plugin **2.13.0** + PWA **116** — blog/notificações implementado (**In review**).
- **#28** permanece **In review** (PWA 115 / plugin 2.12.3).

**Como testar:** `TESTING.md` §7 (rate limit login #22); §4 (**2ac** carrossel; **2aa** novidades; **2x–2z** mapa indoor); §12 **O5** offline mapa evento.

## Sessão anterior (2026-06-04)
- PWA **115** + plugin **2.12.3**: Balcão 1 (azul) / Balcão 2 (teal), número no pino, legenda.
- PWA **110–112**: mobile (tela cheia, pinch, mapa completo); fix diagrama em branco iPhone.
- PWA **113–114**: combobox «Para onde?»; rótulo Pavim.
- Plugin **2.12.1–2.12.2**: fix direções admin + aba Mapa evento / tela branca Salvar abas.
- **#24 / #25:** Done no Project (PWA 108 + plugin 2.11.9).

**Como testar:** `TESTING.md` §4 (**2x**, **2y**, **2z**); validação humana → **Done** no Project.

## Sessão anterior (2026-06-04)

- Backlog oficial no GitHub Project 3; fluxo Backlog → plano → **Ready** → código (`docs/GITHUB-WORKFLOW.md`, ADR-020).
- Issues migradas de `SITE-NOVO-VTIS` para `esvianna/ZELO` (#1 escala, #2 e-mails).

## Sessão anterior (2026-06-02)

- Plugin 2.11.0: API escala escopada; PWA build 99: UX leitura + editor Montar escala.

**Como testar:** `TESTING.md` §4 (2, 2b–2c, 5, 5b–5c).
