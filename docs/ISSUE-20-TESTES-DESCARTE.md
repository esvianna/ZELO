# Issue #20 — Testes automatizados — DESCARTADA

> **Issue:** [esvianna/ZELO#20](https://github.com/esvianna/ZELO/issues/20)  
> **Status:** **Descartada** (ADR-033) — 2026-06-04  
> **ROADMAP:** C2 testes automatizados

---

## Motivo do descarte

- O ZELO é **evento-driven** (deploys esporádicos); o checklist manual **`TESTING.md`** já cobre smoke e regressão antes de cada evento ([#6](https://github.com/esvianna/ZELO/issues/6) validou produção).
- Suite PHPUnit + ambiente WordPress de teste (Docker/CI) exige **investimento alto** (roles, `wp_options` ops, cookies REST) para um monorepo **sem npm/build** e equipa reduzida.
- Não há regressões frequentes que justifiquem CI contínuo neste ciclo de produto.

---

## O que permanece (sem #20)

| Peça | Situação |
|------|----------|
| Validação pré-deploy | `TESTING.md` — fonte de verdade manual |
| Smoke produção | Issue [#6](https://github.com/esvianna/ZELO/issues/6) concluída |
| `.gitignore` | Entradas `phpunit*` mantidas caso reabrir no futuro |
| § Automação futura | `TESTING.md` — sugestão PHPUnit/Playwright permanece como referência |

---

## Se reabrir no futuro

Critérios da issue original: ambiente WP documentado; cobertura mínima auth + ops check-in + schedule read; GitHub Actions. Estimativa: **2–4 sessões** (ambiente + primeiros testes + CI). Priorizar após estabilizar #21 (config API) se houver deploys mais frequentes ou contribuidores externos.
