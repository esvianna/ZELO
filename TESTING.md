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
| 7 | Trocar idioma PT/EN/ES | Strings principais traduzidas |
| 8 | Modo avião após visita | Conteúdo em cache ainda acessível (limitações aceitáveis) |

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

## 3. Smoke test — Operação voluntários

**Pré-requisito:** escala preenchida em **Zelo → Operação Voluntários**; `wp_user_id` vinculado na escala.

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

## 4. Regressão — Filtros e listagem

| # | Cenário | Esperado |
|---|---------|----------|
| 1 | Filtro “Aberto agora” | Locais 24h e com horário válido |
| 2 | Filtros bairro/cidade | Sem CEP/números isolados como opções |
| 3 | Busca na home | Resultados navegam para detalhe |
| 4 | Categorias cultura/compras/lazer | Lista filtrada correta |

---

## 5. Regressão — Cache / deploy

| # | Passo | Esperado |
|---|-------|----------|
| 1 | Após deploy, hard refresh | Rodapé mostra build novo (`zelo-build.js`) |
| 2 | DevTools → Application → Cache | Novo `CACHE_NAME` ativo; caches antigos removidos |
| 3 | Alterar só `app-v5.js` sem bump `?v=` | **Deve falhar** — confirma que processo de versão foi seguido |

---

## 6. Casos de erro

| Cenário | Esperado |
|---------|----------|
| API offline na primeira visita | Fallback cache ou mensagem clara |
| `/ops/export` | HTTP 501 com mensagem |
| Rate limit cadastro (8+/hora/IP) | HTTP 429 |
| Login sem e-mail verificado | HTTP 403 |

---

## 7. Backend admin (amostra)

| # | Área | Esperado |
|---|------|----------|
| 1 | Salvar configurações evento | Dados refletidos na API `/evento` |
| 2 | Importador Places (pequeno lote) | Progresso AJAX; sem fatal error |
| 3 | Categorias CRUD | Reflete em `/categorias` e meta box |
| 4 | Limpar todos os locais | Só na zona de perigo em configurações |

---

## Automação futura (sugestão)

| Camada | Ferramenta sugerida |
|--------|---------------------|
| REST plugin | PHPUnit + `WP_UnitTestCase` para rotas críticas |
| PWA | Playwright smoke (home + login + escala) |
| CI | GitHub Action em push (quando existir `.gitignore` e ambiente WP de teste) |

Até lá, **checklist manual acima é a fonte de verdade**.

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
