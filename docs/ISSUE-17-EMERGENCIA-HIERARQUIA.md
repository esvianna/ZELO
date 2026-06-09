# Issue #17 — Hierarquia visual emergência (B1)

> **Issue:** [esvianna/ZELO#17](https://github.com/esvianna/ZELO/issues/17)  
> **Status:** **Done** — plugin 2.13.4 + PWA 128 (2026-06-04)  
> **Referências:** PRD §5.1, ROADMAP B1

---

## Entregas

### PWA 128 (ajustes contatos)
- Home: **Hospitais | Emergência | Farmácias** — 3 colunas simétricas; card central com destaque rosa
- View emergência: serviços de `emergency_services[]` com descrição multilíngue + botão **Ligar agora** (`tel:`)
- Telefone interno: só se admin marcar «Mostrar telefone interno do evento» **e** número preenchido (default off)
- Posto médico (`medical_loc`) quando configurado
- i18n PT-BR: «contatos» (não contactos)

### Plugin 2.13.4
- Admin: 3 slots (Polícia 190, SAMU 192, Bombeiros 193) — número, nome e «quando ligar» em PT/EN/ES, checkbox «Exibir na PWA»
- API: `GET /evento` → `emergency_services[]`, `info_uteis.emergency_phone_active`
- Migração de `phones[]` legado

---

## Como testar

Ver `TESTING.md` §4 **2ad** e **2ae**.

1. Admin → Configurações → Emergência pública: editar textos PT/EN/ES; alternar «Exibir na PWA»
2. Telefone interno: desmarcado → não aparece na PWA; marcar + número → bloco «Telefone do evento»
3. PWA: home 3 cards simétricos; Emergência → 3 linhas com **Ligar agora** (discagem directa)
4. Trocar idioma (PT/EN/ES) → rótulos e guias do backend no idioma activo
5. Regressão: S.O.S., mapa, bottom nav

---

## Fora de escopo

- Branding evento (#18)
