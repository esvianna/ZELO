# TESTING.md — ZELO

Orientação de testes **manual** (prioritário hoje) e caminho para automação futura. Não há suite automatizada no repositório.

---

## Quando testar

- Após qualquer mudança em `api-routes.php`, auth, ops ou `app-v5.js` / `api-v5.js`.
- Após incrementar versão de cache (`DEPLOYMENT_RULES.md`).
- Antes de cada evento: checklist completo abaixo.

---

## Ambiente

| Requisito | Detalhe |
|-----------|---------|
| URL | Mesmo domínio PWA + WordPress (HTTPS) — `api-v5.js` usa `window.location.origin`; **não** editar URLs em produção same-origin |
| Navegador | Chrome Android + Safari iOS (amostra) |
| Perfis WP | visitante (sem login), voluntário, realocador, gestor ops, admin |

---

## 1. Smoke test — Visitante (sem login)

| # | Passo | Esperado |
|---|-------|----------|
| 1 | Abrir PWA em aba anônima | Home carrega, status Online/Offline |
| 2 | Home → Farmácias / Hospitais | Lista com itens ou estado vazio claro |
| 3 | Abrir detalhe de um local | Endereço, horário, status aberto/fechado coerente |
| 4 | Mapa geral | Pins e legenda; zoom/pan OK |
| 5 | Emergência | Telefones do evento visíveis |
| 6 | Evento (info) | Dados de `/evento` |
| 6p | Secções opcionais Info (PWA **138+**, plugin **2.13.8+**, #34) | manage_options | Desactivar Como chegar / Wi‑Fi / Credenciamento — cards **ausentes** na PWA |
| 6p2 | Contacto imprensa | manage_options + telefone | Card acima de Segurança; **Ligar** e **WhatsApp** funcionam |
| 6p3 | Compat. evento existente | — | Secções com dados antigos permanecem visíveis até desactivar no admin |
| 6p4 | Instruções imprensa (#35, PWA **140+**) | Post WP slug `imprensa-autoridades` + «Exibir no app Zelo»; voluntário logado | Card Info: link **Ver instruções completas**; Home ops: botão **Imprensa / Autoridades** / *Como agir* — ambos abrem o mesmo `blog-post` |
| 6p5 | Sem post ainda (#35) | Card imprensa activo, post não criado | Link e botão **ocultos** até publicar o post |
| 7 | Widget tempo na home | Resumo + toque abre previsão completa |
| 8 | Sino (avisos) | Lista evento + (voluntário) turno/check-in/swap |
| 9 | Trocar idioma PT/EN/ES | Strings principais traduzidas |
| 10 | Modo avião após visita online | Home, evento, tempo e avisos via cache |

---

## 2. Smoke test — Autenticação

| # | Passo | Esperado |
|---|-------|----------|
| 1 | Login credenciais inválidas | Mensagem de erro, sem vazar se usuário existe |
| 2 | Login válido (e-mail verificado) | Redireciona home; nonce salvo |
| 3 | Cadastro novo voluntário | E-mail de verificação; role `subscriber`; login bloqueado até verificar |
| 4 | Link verificação | View `email-verified`; login OK como visitante; banner «aguardando aprovação» |
| 4b | Admin aprova na PWA (#41) | Perfil → Gerir cadastros → Aprovar → logout/login → acesso ops |
| 5 | Logout / limpar sessão | Perfil pede login novamente |

---

## 3. Admin — Operação de Voluntários (plugin 2.6.0+)

| # | Passo | Esperado |
|---|-------|----------|
| 1 | Abrir **Locais Zelo → Operação Voluntários** após atualizar plugin | Abas: Escala, Turnos, Locais, Idiomas, Voluntários, Governança, Config, JSON |
| 2 | Aba **Turnos** (2.10.1+) | Códigos A1–B2; **local** (select); início/fim; ativo |
| 3 | Aba **Voluntários** | Cadastrar nome + telefone sem conta WP |
| 4 | Aba **Escala** (2.10.0+) | Dia, turno, voluntário, início/fim (`type="time"`); sem coluna Local (vem do turno) |
| 4b | Escala sem local no turno A1 | Erro ao salvar: configure local na aba Turnos |
| 5 | Selecionar turno | Início/fim preenchidos do catálogo; ajustar faixa dentro do turno |
| 6 | Selecionar voluntário | Nome preenchido (editável) |
| 7a | Duplicar mesma pessoa em sexta+A1 com **mesmos** início/fim | Erro ao salvar |
| 7b | Mesma pessoa em sexta+A1 com horários **diferentes** (ex. 07:00–08:15 e 11:00–12:30) | Salva OK |
| 7c | Horário fora do turno (ex. 06:00–08:00 em A1 07:00–12:30) | Erro de validação |
| 8 | Salvar abas | Notice de sucesso; PWA lista escala com horários da linha |
| 9 | JSON avançado | `catalogs` e `roster_volunteer_id` presentes após save |

---

## 4. Smoke test — Operação voluntários (PWA)

**Pré-requisito:** escala preenchida em **Zelo → Operação Voluntários**; `wp_user_id` ou nome na linha para roster.

| # | Passo | Perfil | Esperado |
|---|-------|--------|----------|
| 1 | GET `/wp-json/zelo/v1/ops/voluntarios` sem auth | — | **401/403** após remover bypass público |
| 2 | Login voluntário → abrir Escala (2.11.0+) | view_ops | Bloco **Minhas designações** + **Escala da equipa** (nomes de colegas); badge «Você» na sua linha; filtros dia/turno/local/**responsável**/nome/idioma (build 106+) |
| 2p | Perfil → alterar nome/telefone/idiomas; upload foto (130+: preview imediato + msg junto ao avatar); senha (108+) | logado | Salvar OK; foto actualiza header e perfil; e-mail novo exige verificação |
| 2f | Escala aberta → **F5** (build 107+) | view_ops | Permanece em **Escala**; hash `#escala`; nav Operação ativo |
| 2w | Nome voluntário/responsável com telefone cadastrado (105+) | view_ops | Nome é link `wa.me`; sem telefone → texto sem link; offline: cache ops; abrir WhatsApp exige rede |
| 2x | **Mapa evento** — admin: upload JPG + 2 balcões + 1 destino com direções (2.12+) | manage_options | Detalhes → direções Balcão 1/2; **Salvar abas** permanece na aba Mapa evento; `GET /indoor-map` tem `routes` com texto |
| 2y | **Mapa evento** PWA: balcão + destino | **view_ops** (#41) | Diagrama: Balcão 1 azul / Balcão 2 teal + legenda (115+); combobox destinos; copiar instruções; **403** sem `view_ops` |
| 2z | **Mapa evento** PWA mobile (110+) | iPhone / ≤768px | Abre em **Orientar**; aba Diagrama: **header + bottom nav visíveis** (124+); pinch; botões **Mapa completo** / **Ir ao destino** |
| 2aa | **Novidades / blog** (#26, 2.13.0 / PWA 116+) | **view_ops** (#41) | Admin: post com «Publicar na PWA» → `GET /news` OK; card/menu → lista + detalhe PT; subscriber **403** |
| 2ac | **Carrossel novidades** (#15, 2.13.3 / PWA 126+) | **view_ops** (#41) | Admin: post carrossel → home com swipe; subscriber/anónimo sem carrossel |
| 2ad | **Emergência — hierarquia visual** (#17, PWA 127+) | Visitante | Home: card Emergência full-width rosa vs Hospitais/Farmácias; view emergência com hero + tel. destaque admin; lista/pesquisa destaca categoria `emergencia`; S.O.S. bottom nav OK; regressão mapa/nav |
| 2ae | **Emergência — contatos** (#17, 2.13.4 / PWA 128+) | Visitante | Admin: 3 slots 190/192/193 com guia PT/EN/ES; PWA: **Ligar agora** (`tel:`); home 3 colunas simétricas; tel. interno só se checkbox admin; idioma altera textos do backend |
| 2g | Visitante em **Mapa** → F5 (107+) | — | Permanece em Mapa; hash `#mapa` |
| 2h | Abrir URL com `#escala` sem login | — | Redireciona **Login** (sem loop infinito) |
| 2i | `?zelo_verified=1` após cadastro | — | Tela e-mail verificado (prioridade sobre última view) |
| 2b | Voluntário: filtro «Comigo neste turno» | view_ops | Dia+turno da sua designação aplicados |
| 2c | Voluntário: `POST /ops/schedule` (devtools) | view_ops | **403** |
| 3 | Check-in em assignment | checkin_ops | Estado atualizado; persiste após refresh |
| 4 | Check-out | checkin_ops | Registro de saída |
| 5 | Realocação em linha que supervisiona | homem-chave / supervisor na governança | Assignment muda; **403** se não for responsável do turno |
| 5b | Homem-chave na governança → **Montar escala** | `zelo_edit_schedule` + keymen | Editor abre; adicionar/remover linhas; salvar; PWA atualiza |
| 5c | Horário fora do turno no editor PWA | responsável turno | Erro da API ao salvar |
| 5d | Montar escala: guardar **sem** alterar linhas | homem-chave | Voluntários com `accepted` **permanecem** accepted; sem aviso «escala mudou» |
| 5e | Montar escala: alterar só horário de **uma** linha | homem-chave + 2 voluntários no turno | Só o afetado fica `pending` + `pending_reason: schedule_changed`; outro mantém `accepted`; PWA sino → «A sua escala mudou — confirme» |
| 5e2 | Voluntário já tinha **accepted** → alterar horário da linha | homem-chave | Em `wp_options` / payload: `prior_commitment.committed_at` e `committed_by` do aceite anterior permanecem; `status` = `pending` |
| 5f | Troca de voluntário na mesma linha (`id` preservado) | homem-chave | Antigo: compromisso removido (sem reconfirmar); novo: `pending` + aviso schedule_changed |
| 5g | E-mail escala alterada | voluntário com e-mail verificado | Após 5e: no próximo `zelo_volunteer_notify_tick` (≤1h), e-mail «Sua escala mudou»; dedup — não reenvia após aceitar |
| 5h | Vista **Por turno** (padrão) | view_ops | Mesma faixa horária mostra vários voluntários num bloco; cores por faixa; turno A1/B1 em cards separados |
| 5i | Toggle **Lista** | view_ops | Volta à tabela linha a linha; preferência persiste após refresh (`zelo_ops_schedule_view`) |
| 5j | **Montar este turno** no card | homem-chave | Abre editor com dia+turno corretos |
| 5k | **Minhas designações** | voluntário | Cards por turno/faixa (não tabela); aceitar/recusar OK |
| 5l | **Home — designações** (PWA **133+**, #30) | voluntário com escala | Só aparecem itens com ação (pendente de confirmar ou check-in/out no dia); recusadas/aceitas futuras **não** no bloco home |
| 5l2 | Após **recusar** designação | voluntário | Some da home; visível na escala em «Recusadas e encerradas» (colapsado) ou filtro «Recusadas» |
| 5l3 | Após **aceitar** (dia futuro) | voluntário | Home mostra «Tudo em dia»; escala lista em ativas; presença «Aguardando dia do evento» (não «Pendente») |
| 5l3b | Aceita + dia **já passou** sem check-in (PWA **135+**) | voluntário | Badge presença «Pendente» — **não** «Aguardando dia do evento» |
| 5l4 | Escala — filtro estado participação | voluntário | Select no bloco «Minhas designações»: Todos / Pendentes / Aceitas / Recusadas |
| 5l5 | Vista equipa (supervisor) | homem-chave | Recusas de colegas **continuam** visíveis na escala da equipa |
| 5m | **Remover linha recusada** (PWA **134+** / plugin **2.13.7+**, #31) | homem-chave / gestor | Ícone lixeira em linha `declined` no turno do escopo; modal com resumo → Confirmar remove linha |
| 5m2 | **Remover linha pending** (PWA **148+**, #43) | homem-chave / gestor | Lixeira em `pending` ou «Escala alterada — confirme»; modal com aviso; confirmar remove linha |
| 5m2b | **Remover linha accepted** (#43) | responsável | Lixeira visível; modal avisa que já confirmou (ou check-in); remove designação e compromisso |
| 5m2c | Cancelar no modal | responsável | Linha permanece; sem alteração na API |
| 5m3 | Swap pendente na linha | responsável | Modal avisa; após remover, pedido fica `rejected` |
| 5m4 | Voluntário comum | view_ops | **Sem** botão remover em linhas de colegas |
| 5m5 | Homem-chave outro turno | view_ops | Sem botão; `POST /ops/schedule` omitindo linha → **403** |
| 5m6 | Vista **Lista** | gestor | Botão «Remover» na coluna de acções |
| 5n | **+ Adicionar** no card do turno (PWA **136+**, #32) | homem-chave / gestor | Modal com voluntário + horário; nova linha na escala; compromisso `pending` |
| 5n2 | **Editar** linha (ícone / botão Lista) | responsável | Alterar horário ou voluntário; aviso se `accepted`; API valida bounds |
| 5n3 | Horário inválido no modal | responsável | Erro exibido no modal (não só alert) |
| 5n4 | «Montar este turno» | responsável | Editor completo inalterado |
| 5n5 | Linha `declined` | responsável | Sem editar — remover (#31) + adicionar (#32) |
| 5n6 | Admin → Escala → **Limpar duplicatas** (#37, plugin 2.14.1+) | `manage_options` | Aviso amarelo se há duplicatas; confirmação; remove excedentes; sem duplicatas → notice verde |
| 5n7 | Admin → Voluntários: excluir linha → **Salvar abas** (#38, plugin 2.14.4+) | `manage_options` | Página recarrega (redirect); **notice verde/amarelo** no topo; voluntário some; se escala inválida, notice amarelo mas roster salvo |
| 5n8 | Config → **Gerar VAPID** não grava outras abas (#38) | `manage_options` | Só gera chaves; **Salvar abas** grava config sem conflito |
| 5n9 | Regressão form admin (#38, plugin **2.14.6+**) | `manage_options` | «Limpar duplicatas» e «Salvar abas» no **mesmo** `<form>` (sem form aninhado); ambos mostram notice após redirect |
| 5n10 | Hotfix save + notice (#38, plugin **2.14.7+**) | `manage_options` | «Salvar abas» → redirect + notice verde/amarelo; alteração persiste após F5; dedupe não dispara save das abas |
| 5n11 | Nonce admin (#38, plugin **2.14.8+**) | `manage_options` | «Salvar abas» **não** mostra «Este link expirou»; dedupe e save funcionam no mesmo form |
| 5n12 | Salvar por aba (#39, plugin **2.15.0+**, fix persist **2.15.2+**) | `manage_options` | Cada aba tem botão **Salvar**; notice na mesma página (sem tela branca); Config grava push/VAPID sem validar escala; Turnos salva sem erro de duplicata na escala; **F5** confirma alteração gravada |
| 5n13 | Troca de abas admin (#39, plugin **2.15.1+**) | `manage_options` | Clicar abas muda conteúdo (não só `#hash`); consola sem `SyntaxError`; F5 com `#tab-config` abre Config |
| 5o | Layout toolbar escala (PWA **137+**, #33) | gestor / mobile 375px | Filtro status «Todos os status»; selects com estilo uniforme; filtros legíveis (grid 2 col.) |
| 5o2 | Botões Montar escala + Export PDF | gestor | Mesma altura, mesma linha |
| 5o3 | Regressão filtros escala | homem-chave | Dia/turno/local/responsável/nome/idioma e export PDF inalterados |
| 6 | Pedido de substituição | conforme regra | Criado; gestor aprova/rejeita |
| 6a | Painel «Pedidos de substituição» (PWA **132+**) | gestor / homem-chave | Nome do solicitante, dia·turno·local, motivo e data — **sem** IDs `sw_*` / `asg_*` |
| 6b | Sino — aviso swap pendente | gestor | Resumo legível («Nome — Sábado · A1 — Balcão 3») |
| 6c | Aprovar / recusar swap | gestor | Fluxo inalterado; após aprovação, histórico ops com contexto humano |
| 7 | Cron lembretes | admin | `wp cron event list` contém `zelo_volunteer_notify_tick` (se aplicável) |

---

## 5. Regressão — Filtros e listagem

| # | Cenário | Esperado |
|---|---------|----------|
| 1 | Filtro “Aberto agora” | Locais 24h e com horário válido |
| 2 | Filtros bairro/cidade | Sem CEP/números isolados como opções |
| 3 | Busca na home | Resultados navegam para detalhe |
| 4 | Categorias cultura/compras/lazer | Lista filtrada correta |

---

## 6. Regressão — Cache / deploy

| # | Passo | Esperado |
|---|-------|----------|
| 1 | Após deploy, hard refresh | Rodapé mostra build novo (`zelo-build.js`) |
| 2 | DevTools → Application → Cache | Novo `CACHE_NAME` ativo; caches antigos removidos |
| 3 | Alterar só `app-v5.js` sem bump `?v=` | **Deve falhar** — confirma que processo de versão foi seguido |

---

## 7. Casos de erro

| Cenário | Esperado |
|---------|----------|
| API offline na primeira visita | Fallback cache ou mensagem clara |
| `/ops/export` sem `zelo_manage_ops` | HTTP 403 |
| `/ops/export?format=pdf` com gestor + nonce | HTTP 200, `Content-Type: application/pdf` |
| Export PDF 2.11.5+ — turno com 2 faixas | Blocos separados por horário; voluntários da mesma faixa juntos |
| Export PDF 2.11.5+ — cabeçalho turno | Linha **Responsável:** homem-chave do dia/turno (governança) |
| Export PDF 2.11.5+ — `?day=sexta&shift=A1` | Só dia/turno filtrados |
| `/ops/export?format=csv` com gestor + nonce | HTTP 200, CSV com cabeçalho `dia;turno;...` (layout linha a linha, inalterado) |
| Deploy plugin sem `inc/lib/font/*.php` | JSON `zelo_export_pdf_failed` (helveticab.php, etc.) |
| Falha FPDF (plugin &lt; 2.9.3) | JSON `zelo_export_pdf_failed`, não página crítica HTML |
| Rate limit cadastro (8+/hora/IP) | HTTP 429 |
| Rate limit login (2.13.5+) — 11.ª tentativa mesmo user / 15 min | HTTP 429, código `zelo_rate_limit` |
| Login sem e-mail verificado | HTTP 403 |

---

## 8. Backend admin (amostra)

| # | Área | Esperado |
|---|------|----------|
| 1 | Salvar configurações evento | Dados refletidos na API `/evento` (incl. `trans_section_active`, `wifi_section_active`, `cred_section_active`, `press_contact`) |
| 3 | Operação Voluntários → **Mapa evento** | Upload diagrama; posicionar pinos; direções Balcão 1/2; coluna Rotas OK |
| 3 | Categorias CRUD | Reflete em `/categorias` e meta box |
| 4 | Limpar todos os locais | Só na zona de perigo em configurações |

---

## 9. Confirmação voluntários (plugin 2.7.0, PWA build 81+)

| # | Cenário | Esperado |
|---|---------|----------|
| T1 | Voluntário aceita designação antes de `commitment_deadline` | Status `accepted`; aviso `commitment-*` some |
| T2 | Voluntário recusa | Status `declined`; supervisor recebe e-mail |
| T3 | Após deadline, voluntário não aceita; supervisor confirma em nome | HTTP 200 com `on_behalf` |
| T4 | Check-in sem `accepted` | HTTP 400 `zelo_commitment_required` |
| T5 | Check-in fora do dia do evento | HTTP 400 `zelo_presence_wrong_day` |
| T6 | Check-in fora da janela configurada | HTTP 400 `zelo_presence_window_closed` |
| T7 | Supervisor check-in/checkout por outro | OK; `on_behalf` gravado |
| T8 | Cadastro com e-mail = `expected_email` do roster → admin aprova vínculo | Linhas da escala ganham `wp_user_id` |
| T9 | Admin aba Onboarding | Lista roster, fila de vínculos, stats compromissos |
| T9b | Onboarding → cadastro pendente → **Confirmar cadastro** | `zelo_email_verified=1`; login PWA OK sem link no e-mail |
| T10 | Voluntário comum tenta check-in em designação alheia | HTTP 403 |
| T11 | Admin aba Onboarding com 70+ designações | «Ver todas as designações» lista o total; roster mostra contagem correta por voluntário |

Admin: Config → prazo, janelas presença, supervisores (select WP). Governança com IDs.

---

## 11. Idiomas no perfil (plugin 2.8.0, PWA build 85+)

| # | Passo | Esperado |
|---|-------|----------|
| 1 | Admin Voluntários: idiomas no roster; salvar | Escala **sem** coluna Idiomas; designações na PWA mostram idiomas do voluntário |
| 2 | `GET /wp-json/zelo/v1/ops/languages` sem auth | Lista `{ id, name }` de idiomas ativos |
| 3 | Cadastro PWA com 2 idiomas | Após verificar e-mail e login, perfil mostra idiomas |
| 3b | Cadastro: marcar 2+ idiomas (checkboxes) | Seleção clara sem Ctrl/toque prolongado; `language_ids` enviados no POST |
| 4 | Perfil PWA: alterar idiomas e Salvar | `PATCH /auth/profile` OK; escala atualiza filtro/exibição |
| 5 | Migração: escala antiga só com idiomas na linha | Após atualizar plugin, idiomas no roster; linhas da escala sem `languages` gravado |

---

---

## 10. Escala dia + data (plugin 2.7.1, PWA build 83+)

| # | Passo | Esperado |
|---|-------|----------|
| 1 | Config: preencher datas sexta/sábado/domingo | Admin escala/governança mostram «Sexta-feira (dd/mm/aaaa)» |
| 2 | Governança: homens-chave diferentes sábado vs sexta | Salvos independentemente; alertas de recusa usam supervisor do dia |
| 3 | PWA escala/home/avisos | Rótulo «Sexta · 26/06» (ou equivalente) quando data configurada |
| 4 | Turnos após migração | A1/B1 07:00–12:30, A2/B2 12:30–18:30 se catálogo ainda era default antigo |

---

## Automação futura (sugestão)

| Camada | Ferramenta sugerida |
|--------|---------------------|
| REST plugin | PHPUnit + `WP_UnitTestCase` para rotas críticas |
| PWA | Playwright smoke (home + login + escala) |
| CI | GitHub Action em push (quando existir `.gitignore` e ambiente WP de teste) |

Até lá, **checklist manual acima é a fonte de verdade**.

---

## 6. Previsão do tempo (`/clima`, build 77+; widget home build 78+)

| # | Passo | Esperado |
|---|-------|----------|
| 1 | Online: widget tempo na home → toque | Temperatura atual, horas e 7 dias na view completa |
| 2 | DevTools → Offline → widget e view tempo | Último cache visível; badge offline |
| 3 | Admin: desmarcar «Ativar previsão» → salvar | View mostra mensagem de desativado |
| 4 | Admin: lat/lng vazios | Erro amigável (coordenadas não configuradas) |
| 5 | Aguardar 30+ min online na view | Dados atualizados (transient expirado) |

---

## 7. Nav, header e avisos (build 78+)

| # | Passo | Esperado |
|---|-------|----------|
| 1 | Bottom nav | 5 itens; S.O.S. central; sem TEMPO |
| 2 | Sino | Badge com avisos não lidos; view com filtros |
| 3 | Menu header | Instalar (se disponível), atualizar cache |
| 4 | Aviso warning/critical na home | Faixa compacta + link para avisos |
| 5 | Aviso info | Só no hub (não card grande na home) |
| 6 | Voluntário logado | Secção «Para você» com check-in pendente em qualquer dia da escala |
| 7n1 | **Ícones PWA** (#40, build **145+**) | Chrome desktop | **Aba:** favicon coração; **Apps instalados / instalar:** wordmark «zelo»; DevTools → Manifest sem warnings; reinstalar após deploy se necessário |
| 7n2 | **Atalho desktop Windows** (#40) | Chrome desktop | Se `.lnk` mostrar «Z» cinza: Propriedades → Alterar ícone → `%LOCALAPPDATA%\Google\Chrome\User Data\Default\Web Applications\Manifest Resources\` (`.ico`/`.png` da app) |

---

## 12. Offline e cache (PWA build 89+)

| # | Passo | Esperado |
|---|-------|----------|
| O1 | Login + abrir escala online; depois modo avião | Última escala visível com badge «em cache» |
| O2 | Avatar offline após sessão online | Default local ou último avatar same-origin em cache |
| O3 | `default-avatar.png` no precache SW | Imagem carrega offline no header/perfil |
| O4 | Home tempo após visita online | Widget com dados `zelo_clima` + indicador stale se offline |
| O5 | Abrir **Mapa do evento** online; depois modo avião (122+, ADR-024) | Diagrama + orientações visíveis; badge «Mapa do evento em cache»; imagem JPG carrega do SW |
| O6 | **Novidades:** abrir lista online (prefetch); abrir 1 artigo; modo avião → mesmo artigo (123+, ADR-025) | Corpo HTML completo; badge «Novidade em cache»; artigo nunca aberto → mensagem amigável offline |

---

## 13. i18n completo (PWA build 89+)

| # | Passo | Esperado |
|---|-------|----------|
| I1 | Perfil → idioma EN → escala operacional | Filtros, tabela, governança e export em inglês |
| I2 | Idioma ES → login + cadastro + erros sessão | Sem português residual nas strings auditadas |
| I3 | Header «Offline» / «Online» | Traduz ao mudar idioma |
| I4 | Home com ES selecionado (sem sair da view) | Dashboard, widget clima e banner atualizam na hora |
| I5 | Home «Minhas designações» (2+ turnos) | Título `Dia · data` + turno à direita; meta `Local · horário`; aviso «escala alterada» acima dos botões; **mobile (≤480px):** Aceitar/Não posso empilhados largura total; **tablet/desktop:** lado a lado (121+) |

---

## 14. Auditoria permissões ops (plugin 2.11.8+, ZELO#13)

Referência: [docs/OPS-PERMISSIONS.md](docs/OPS-PERMISSIONS.md).

| # | Passo | Perfil | Esperado |
|---|-------|--------|----------|
| 1 | `POST /ops/schedule` sem cap edit | voluntário | **403** |
| 2 | Homem-chave A1 → `POST /ops/reallocate` linha turno B1 | homem-chave | **403** |
| 3 | Homem-chave A1 → `PATCH /ops/swap-requests/{id}` pedido turno B1 | homem-chave | **403** |
| 4 | Homem-chave A1 → `GET /ops/swap-requests` | homem-chave | Só pedidos do(s) turno(s) que supervisiona |
| 5 | Sem cookie → `GET /ops/voluntarios` | — | **401** (filtro público off) |
| 6 | Voluntário → `GET /ops/export?format=pdf` | view_ops | **403** |
| 7 | Gestor → smoke §4 (check-in, escala, export) | manage_ops | Sem regressão |

---

## 15. Web Push VAPID (plugin 2.14.0+, PWA 141+, #36)

Pré-requisitos: HTTPS; Chrome/Android ou Safari 16.4+ (iOS com limitações). Admin: Operação Voluntários → Config → gerar VAPID + activar push.

| # | Passo | Esperado |
|---|-------|----------|
| 15.1 | Admin: gerar par VAPID, activar push, salvar | Chave pública visível; sem erro |
| 15.2 | Login voluntário → prompt consentimento (1×) → aceitar | `POST /ops/push/subscribe` **200** |
| 15.3 | Perfil → estado «Activas»; botão desactivar | `GET /ops/push/status` `subscribed: true` |
| 15.4 | Publicar post Novidades com «notificação» | Push recebido (app fechada) |
| 15.5 | Alterar horário na escala (voluntário `accepted`) | Push «escala mudou» + hub in-app |
| 15.6 | Janela check-in aberta (cron ou simular) | Push check-in (se subscription activa) |
| 15.7 | Perfil → desactivar notificações | `DELETE /ops/push/subscribe` **200**; sem push novo |
| 15.7b | Regressão unsubscribe | Endpoint gravado é removido (hash coerente; plugin 2.14.2+) |
| 15.8 | Regressão hub sino + e-mail cron | Inalterados |
| 15.9 | Admin: «Limpar subscriptions push» + confirm (#42, plugin 2.17.0+) | Tabela vazia; `subscribed: false`; notice admin; push só após re-activar Perfil |
| 15.10 | Admin: «Gerar novo par VAPID» + confirm (#42) | Novo par + subscriptions removidas; voluntário re-activa; `vapidPublicKeyFingerprint` muda |
| 15.11 | Logout → login outra conta mesmo telefone (#42, PWA 147+) | Push do user A não chega após B activar; cada conta re-activa no Perfil |
| 15.12 | Fingerprint: `subscribed:true` sem `zelo_push_vapid_fp` local (#42) | Perfil «Reactivação necessária»; Activar → push Novidades OK |

---

## 16. Aprovação de voluntários (#41, plugin 2.16.0+, PWA 146+)

| # | Passo | Esperado |
|---|-------|----------|
| 16.1 | Registo PWA → confirmar e-mail | Role `subscriber`; login OK; **403** `/ops/voluntarios`, `/news`, `/indoor-map` |
| 16.2 | Home subscriber pendente | Banner «aguardando aprovação»; secção Operação oculta |
| 16.3 | Admin (`manage_options`) → Perfil → Gerir cadastros | Lista pendentes; badge com contagem |
| 16.4 | Aprovar candidato | Role `zelo_voluntario`; após re-login ops OK |
| 16.5 | Reprovar candidato pendente | Mantém `subscriber`; ops 403; banner reprovado |
| 16.6 | Após aprovado | **Sem** botão reprovar na fila |
| 16.7 | Admin WP cria user `zelo_voluntario` | Bypass fila; ops imediato |
| 16.8 | Legado existente pós-deploy | Ops intactos (migração `approved`) |
| 16.9 | Entrada na fila | E-mail admin + push (se subscrito) |
| 16.10 | F5 pós-reprovar | Cache ops/news/indoor limpo |

---

## Registro de execução

Copie e preencha após cada rodada:

```
Data: YYYY-MM-DD
Ambiente: staging | produção
Build PWA: ___
Plugin: ___
Executor: ___
Resultado: OK | FALHAS (descrever)
```
