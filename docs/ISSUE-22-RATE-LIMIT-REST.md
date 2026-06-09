# Issue #22 — Rate limiting REST (#22)

> **Issue:** [esvianna/ZELO#22](https://github.com/esvianna/ZELO/issues/22)  
> **Status:** **Done** — plugin **2.13.5** deploy produção (2026-06-04)  
> **ADR:** ADR-032

---

## Decisões fechadas

| # | Decisão |
|---|---------|
| Login IP | 30 / 15 min |
| Login user | 10 / 15 min |
| Contar sucesso | Sim |
| verify-email | Fora do MVP |
| Lost password | WordPress (`wp-login.php`) |
| Register | 8/h/IP (inalterado) |

---

## Implementação (2.13.5)

- `inc/rate-limit.php` — `zelo_rate_limit_consume()`, `zelo_login_rate_limit_check()`, `zelo_registration_rate_limit_ok()`
- `zelo_api_login()` — throttle antes de `wp_signon`
- Filtro: `apply_filters( 'zelo_rate_limit_enabled', true )`

---

## Como testar

`TESTING.md` §7 — rate limit login + cadastro; regressão export PDF 60 s.

---

## Fora de escopo

- Rate em GET públicos (`/locais`, `/evento`)
- Endpoint REST lost-password
