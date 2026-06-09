# Issue #18 — Branding splash/home — DESCARTADA

> **Issue:** [esvianna/ZELO#18](https://github.com/esvianna/ZELO/issues/18)  
> **Status:** **Descartada** (ADR-031) — 2026-06-04  
> **ROADMAP:** B2 branding evento

---

## Motivo do descarte

- Banner evento (`#home-event-banner` + `foto`/`name_evento` no admin) **já identifica** o evento na home.
- Splash dedicada e cores custom exigiriam **pacote gráfico** por evento sem ganho operacional proporcional.
- Equipa confirmou: **não priorizar** polish de branding neste ciclo.

---

## O que permanece (sem #18)

| Peça | Situação |
|------|----------|
| Banner evento na home | `GET /evento` → `#home-event-banner` |
| Logo no mapa | `logo` em admin |
| Nome do evento | `name_evento` na API e views Info |
| Welcome genérico | `#home-welcome` («Bem-vindo!») — **não** personalizado por evento |

---

## Se reabrir no futuro

PRD §5.2: splash com nome do evento; faixa «Você está na [Evento]»; cores opcionais em `zelo_event_data`. Estimativa: 0,5–1 sessão com assets fornecidos.
