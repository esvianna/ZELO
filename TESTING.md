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
| URL | Mesmo domínio PWA + WordPress (HTTPS) |
| Navegador | Chrome Android + Safari iOS (amostra) |
| Perfis WP | visitante (sem login), voluntário, realocador, gestor ops, admin |

Configurar `baseUrl` / `siteUrl` em `frontend-pwa/assets/js/api-v5.js` para o ambiente testado.

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
| 3 | Cadastro novo voluntário | E-mail de verificação; login bloqueado até verificar |
| 4 | Link verificação | View `email-verified`; depois login OK |
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
| 2p | Perfil → alterar nome/telefone/idiomas; upload foto; senha (108+) | logado | Salvar OK; e-mail novo exige verificação; olho mostra/oculta senha (login/cadastro/perfil) |
| 2f | Escala aberta → **F5** (build 107+) | view_ops | Permanece em **Escala**; hash `#escala`; nav Operação ativo |
| 2w | Nome voluntário/responsável com telefone cadastrado (105+) | view_ops | Nome é link `wa.me`; sem telefone → texto sem link; offline: cache ops; abrir WhatsApp exige rede |
| 2x | **Mapa evento** — admin: upload JPG + 2 balcões + 1 destino com direções (2.12+) | manage_options | Detalhes → direções Balcão 1/2; **Salvar abas** permanece na aba Mapa evento; `GET /indoor-map` tem `routes` com texto |
| 2y | **Mapa evento** PWA: balcão + destino | view_ops ou visitante | Diagrama: Balcão 1 azul / Balcão 2 teal + legenda (115+); combobox destinos; copiar instruções |
| 2z | **Mapa evento** PWA mobile (110+) | iPhone / ≤768px | Abre em **Orientar**; aba Diagrama: tela cheia (111+); pinch até mapa completo; botões **Mapa completo** / **Ir ao destino** |
| 2aa | **Novidades / blog** (#26, 2.13.0 / PWA 116+) | Logado | Admin: post com «Publicar na PWA» + notificação → `GET /news` OK; sino + badge; card home + menu hambúrguer → lista + detalhe PT; **voluntário:** card «Novidades» na secção Operação (119+); título sem `&#8211;` literal (2.13.1+); vídeo no detalhe sem corte lateral (117+, iPhone); anónimo sem novidades |
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
| 6 | Pedido de substituição | conforme regra | Criado; gestor aprova/rejeita |
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
| Login sem e-mail verificado | HTTP 403 |

---

## 8. Backend admin (amostra)

| # | Área | Esperado |
|---|------|----------|
| 1 | Salvar configurações evento | Dados refletidos na API `/evento` |
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

---

## 12. Offline e cache (PWA build 89+)

| # | Passo | Esperado |
|---|-------|----------|
| O1 | Login + abrir escala online; depois modo avião | Última escala visível com badge «em cache» |
| O2 | Avatar offline após sessão online | Default local ou último avatar same-origin em cache |
| O3 | `default-avatar.png` no precache SW | Imagem carrega offline no header/perfil |
| O4 | Home tempo após visita online | Widget com dados `zelo_clima` + indicador stale se offline |

---

## 13. i18n completo (PWA build 89+)

| # | Passo | Esperado |
|---|-------|----------|
| I1 | Perfil → idioma EN → escala operacional | Filtros, tabela, governança e export em inglês |
| I2 | Idioma ES → login + cadastro + erros sessão | Sem português residual nas strings auditadas |
| I3 | Header «Offline» / «Online» | Traduz ao mudar idioma |
| I4 | Home com ES selecionado (sem sair da view) | Dashboard, widget clima e banner atualizam na hora |
| I5 | Home «Minhas designações» (2+ turnos) | Título `Dia · data` + turno à direita; meta `Local · horário`; botões lado a lado |

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
