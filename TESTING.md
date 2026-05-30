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
| 2 | Aba **Turnos** | Códigos A1–B2 (ou migrados); início/fim; ativo |
| 3 | Aba **Voluntários** | Cadastrar nome + telefone sem conta WP |
| 4 | Aba **Escala** | Dia (select), turno, voluntário (WP ou cadastrado), local, idiomas (multi); sem coluna `id` visível |
| 5 | Selecionar turno | Início/fim preenchidos do catálogo (editáveis) |
| 6 | Selecionar voluntário | Nome preenchido (editável) |
| 7 | Duplicar mesma pessoa em sexta+A1 | Erro ao salvar; escala não gravada |
| 8 | Salvar abas | Notice de sucesso; PWA lista escala normalmente |
| 9 | JSON avançado | `catalogs` e `roster_volunteer_id` presentes após save |

---

## 4. Smoke test — Operação voluntários (PWA)

**Pré-requisito:** escala preenchida em **Zelo → Operação Voluntários**; `wp_user_id` ou nome na linha para roster.

| # | Passo | Perfil | Esperado |
|---|-------|--------|----------|
| 1 | GET `/wp-json/zelo/v1/ops/voluntarios` sem auth | — | **401/403** após remover bypass público |
| 2 | Login voluntário → abrir Escala | view_ops | Minhas designações visíveis |
| 3 | Check-in em assignment | checkin_ops | Estado atualizado; persiste após refresh |
| 4 | Check-out | checkin_ops | Registro de saída |
| 5 | Realocação | reallocate_ops | Assignment muda; histórico registra |
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
| `/ops/export?format=csv` com gestor + nonce | HTTP 200, CSV com cabeçalho `dia;turno;...` |
| Deploy plugin sem `inc/lib/font/*.php` | JSON `zelo_export_pdf_failed` (helveticab.php, etc.) |
| Falha FPDF (plugin &lt; 2.9.3) | JSON `zelo_export_pdf_failed`, não página crítica HTML |
| Rate limit cadastro (8+/hora/IP) | HTTP 429 |
| Login sem e-mail verificado | HTTP 403 |

---

## 8. Backend admin (amostra)

| # | Área | Esperado |
|---|------|----------|
| 1 | Salvar configurações evento | Dados refletidos na API `/evento` |
| 2 | Importador Places (pequeno lote) | Progresso AJAX; sem fatal error |
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
