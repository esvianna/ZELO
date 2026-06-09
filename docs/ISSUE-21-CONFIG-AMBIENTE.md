# Issue #21 — Configuração de ambiente (URL API sem hardcode)

> **Issue:** [esvianna/ZELO#21](https://github.com/esvianna/ZELO/issues/21)  
> **Status:** **Done** (escopo mínimo, ADR-034) — PWA **129** (2026-06-04)  
> **ROADMAP:** C3 (infra) — distinto do C3 «Onboarding roster» do pacote voluntários  
> **Última atualização:** 2026-06-04

---

## 10. Entrega escopo mínimo (ADR-034, PWA 129)

- `app-v5.js` — login: `${API.baseUrl}/auth/login` (removido fallback `tenhazelo.com.br` duplicado).
- `api-v5.js` — comentário same-origin; fallback legado mantido.
- `TESTING.md`, `docs/DEPLOY-ZELO-PWA.md` — produção não exige editar URLs.

Override dev/staging (Opção A) **não implementado** — reabrir só se necessário.

---

## 1. Objetivo

Reduzir ou eliminar URLs fixas (`tenhazelo.com.br`) no frontend, mantendo **same-origin em produção** e permitindo **override documentado** para dev/staging — sem editar `api-v5.js` a cada ambiente.

---

## 2. Estado actual do código

### 2.1 `api-v5.js` (principal)

```javascript
baseUrl: window.location?.origin
    ? `${window.location.origin}/wp-json/zelo/v1`
    : 'https://tenhazelo.com.br/wp-json/zelo/v1',
siteUrl: window.location?.origin
    ? window.location.origin
    : 'https://tenhazelo.com.br',
```

| Cenário | Comportamento hoje |
|---------|-------------------|
| PWA em `https://tenhazelo.com.br/zelo/` | Usa **origin do browser** — hardcode **não entra** |
| `file://` ou origem inválida | Cai no fallback `tenhazelo.com.br` |
| Staging noutro host (ex. `staging.tenhazelo.com.br`) | Usa origin do staging — **OK** se PWA e WP no mesmo host |

### 2.2 Lacuna real — `app-v5.js` (login)

O fluxo de login tem **segundo hardcode** independente:

```443:448:frontend-pwa/assets/js/app-v5.js
                if (API.baseUrl && API.baseUrl.includes('/zelo/v1')) {
                    url = `${API.baseUrl}/auth/login`;
                } else {
                    const apiRoot = 'https://tenhazelo.com.br/wp-json';
                    url = `${apiRoot}/zelo/v1/auth/login`;
                }
```

Em condições normais usa `API.baseUrl`, mas o `else` duplica produção e contradiz a centralização em `api-v5.js`.

### 2.3 Documentação desatualizada

- `TESTING.md` e `docs/DEPLOY-ZELO-PWA.md` ainda dizem «editar `baseUrl`/`siteUrl` em `api-v5.js`» — em produção same-origin **não é necessário**.
- `AGENTS.md` / `SECURITY.md`: não alterar URL de produção no repo sem confirmação explícita.

### 2.4 O que já funciona sem #21

Produção actual (PWA + WP no mesmo domínio, ADR implícito same-origin): **não há bug operacional** — o hardcode é fallback legado e código morto na maior parte dos acessos.

---

## 3. Problema que #21 resolve (se implementada)

| Dor | Detalhe |
|-----|---------|
| Dev local | Testar PWA noutro path/host apontando para WP remoto |
| Manutenção | URL espalhada em 2 ficheiros |
| Staging | Evitar commit acidental de URL de staging em `api-v5.js` |
| Onboarding dev | Novo contribuidor não sabe como apontar API sem editar JS versionado |

**Não resolve:** cross-domain PWA ↔ WordPress (continua fora do MVP — JWT/CORS, `DECISIONS.md`).

---

## 4. Opções de implementação

### Opção A — **Same-origin puro + limpeza** *(proposta MVP)*

- Função única `resolveZeloApiOrigin()` em `api-v5.js`.
- **Produção:** sempre `window.location.origin` (comportamento idêntico ao actual).
- **Override dev:** `localStorage.setItem('zelo_api_origin', 'https://…')` ou ficheiro opcional `zelo-config.local.js` (gitignored) com `window.ZELO_API_ORIGIN`.
- Remover ramo `else` com `tenhazelo` em `app-v5.js` login → `${API.baseUrl}/auth/login` sempre.
- Fallback `tenhazelo.com.br` **opcional** só se `origin` for `null`/`file:` (documentar como último recurso; **não muda prod**).

**Esforço:** ~0,5 sessão · **Sem bump de comportamento em prod** se origin for HTTPS same-origin.

### Opção B — `zelo-config.js` versionado por ambiente

- `zelo-config.example.js` no repo; deploy copia para `zelo-config.js`.
- Carregado em `index.html` **antes** de `api-v5.js`.
- Override explícito em staging; produção com ficheiro vazio ou omitido.

**Prós:** claro para ops FTP. **Contras:** mais um ficheiro no deploy (`DEPLOYMENT_RULES.md`).

### Opção C — Meta tag / injecção no HTML

- `<meta name="zelo-api-origin" content="…">` no deploy staging.
- Útil se HTML for gerado por CI; hoje deploy é estático manual.

### Opção D — **Descartar** (como #20)

- Corrigir **só** o login duplicado em `app-v5.js` (bugfix mínimo).
- Actualizar docs: «produção = same-origin, não editar URLs».
- Manter fallback `tenhazelo` em `api-v5.js` sem evolução.

**Prós:** zero risco deploy. **Contras:** dev local continua sem override formal.

---

## 5. Decisões pendentes (antes de **Ready**)

| # | Pergunta | Proposta |
|---|----------|----------|
| D1 | Escopo | **A** (resolver + override dev) ou **D** (só limpeza login + docs)? |
| D2 | Override dev | `localStorage` vs `zelo-config.local.js` vs ambos? |
| D3 | Fallback `tenhazelo.com.br` | Manter em edge cases ou remover totalmente? |
| D4 | Cross-domain | Confirmar **fora de escopo** (#21 não inclui JWT)? |

---

## 6. Critérios de aceite (ajustados)

- [ ] Produção same-origin: login, escala, cookies WP — **sem regressão** (smoke `TESTING.md` §2).
- [ ] Nenhuma alteração da URL de produção **comportamental** sem confirmação (regra AGENTS).
- [ ] Um único ponto de resolução de `baseUrl` / `siteUrl` (sem hardcode duplicado em `app-v5.js`).
- [ ] Documentado override para dev (`TESTING.md` + `DEPLOY-ZELO-PWA.md`).
- [ ] Se bump PWA: alinhar `zelo-build.js`, `index.html`, `sw.js` (`DEPLOYMENT_RULES.md`).

---

## 7. Ficheiros afectados (implementação A)

| Ficheiro | Alteração |
|----------|-----------|
| `frontend-pwa/assets/js/api-v5.js` | `resolveZeloApiOrigin()`, export coerente |
| `frontend-pwa/assets/js/app-v5.js` | Login usa só `API.baseUrl` |
| `frontend-pwa/index.html` | (Opcional) script `zelo-config.local.js` |
| `.gitignore` | (Opcional) `zelo-config.local.js` |
| `TESTING.md`, `docs/DEPLOY-ZELO-PWA.md` | Secção ambiente / override |
| `DEPLOYMENT_RULES.md` | Se novo asset no cache SW |

**Backend:** nenhum.

---

## 8. Estimativa

| Opção | Sessões |
|-------|---------|
| A — MVP resolver + override | 0,5 |
| B — + ficheiro config deploy | 0,5–1 |
| D — descarte + fix login | 0,25 |

---

## 9. Recomendação

**Opção A** se quiseres fechar C3 com valor real para dev/staging. **Opção D** se produção same-origin é o único cenário previsto — neste caso a issue pode ser **reduzida a bugfix + docs** ou **descartada** como #20.

Próximo passo: escolher D1 (A ou D) e confirmar D2–D3 → marcar issue **Ready** → implementar.
