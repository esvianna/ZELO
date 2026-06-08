# Issue #10 — Painel de cobertura por posto e idioma (B2)

> **Issue:** [esvianna/ZELO#10](https://github.com/esvianna/ZELO/issues/10)  
> **Status:** **Descartado** (ADR-027, 2026-06-04) — escala + filtros + export + admin presença bastam; issue fechada.  
> **Relacionadas:** [#11](https://github.com/esvianna/ZELO/issues/11) filtros escala (idioma), ROADMAP **B2**, admin «Cobertura escala» existente  
> **Última atualização:** 2026-06-04

---

## 1. Objetivo

Painel operacional que mostre **cobertura de voluntários por posto/local e idioma**, destacando **lacunas** (ninguém designado ou idioma em falta) e **excesso** (mais voluntários do que o necessário, se houver meta configurada).

Público-alvo: **supervisores e gestores** durante o evento — tomada de decisão rápida («falta inglês no Balcão 1 no turno A2»).

---

## 2. Pedido original (issue)

> Painel operacional que mostre cobertura de voluntários por posto/local e idioma (lacunas e excesso).

**Critérios de aceite (GitHub):**

- [ ] Wireframe ou critério visual aprovado.
- [ ] Dados derivados da escala + catálogos (`wp_options`).
- [ ] Acesso restrito a perfis com `zelo_view_ops` / gestão conforme governança.

**Nota da issue:** definir métricas (ex.: mínimo por idioma) antes de **Ready**.

---

## 3. Contexto no código hoje

| Peça | Situação |
|------|----------|
| **Admin «Cobertura escala»** | Submenu `zelo-volunteer-coverage` em `volunteer-ops-admin-ui.php` — agrega só **dia + turno**: designados vs check-in / check-out / pendente. **Sem posto nem idioma.** |
| **Permissão admin cobertura** | `manage_options` (administrador WP) — **não** alinhado ao critério `zelo_view_ops` da issue |
| **PWA** | **Nenhuma** view de cobertura. Escala ops tem filtros por local e idioma (`ops-language-filter`, `ops-location-filter`) — leitura linha a linha, não matriz de lacunas |
| **Escala (`schedule`)** | Cada linha: `day`, `shift`, voluntário, `location` (nome derivado do turno → `location_id` no catálogo), `languages[]` (nomes do perfil/roster) |
| **Catálogos** | `shifts[]` com `location_id`; `locations[]` (id, nome, active); `languages[]` (id, nome, active); idiomas no voluntário via `zelo_language_ids` / roster |
| **Check-ins** | `zelo_get_volunteer_checkins()` — status por `assignment_id` (`pending`, `checked_in`, `checked_out`) |
| **REST `/ops/voluntarios`** | Já expõe escala enriquecida + check-ins + catálogos implícitos nos nomes — **sem** endpoint de cobertura agregada |
| **Metas / mínimos** | **Inexistentes** — não há campo «mínimo por idioma por posto» em catálogo ou settings |
| **Governança** | Supervisores por dia/turno (`keymen`, grupo A/B, app) — escopo de edição/check-in, não painel de cobertura |

**Implicação:** existe um **esqueleto admin** (B2 parcial) orientado a presença por turno, mas **não cumpre** o escopo «posto + idioma» da issue #10. A PWA já tem os **dados brutos** via `/ops/voluntarios`; falta **agregação + UX de painel**.

---

## 4. Gap vs critérios de aceite

| Critério | Hoje | Gap |
|----------|------|-----|
| Wireframe / visual | Nada aprovado | Definir layout (matriz vs cards vs lista) e onde vive (PWA vs admin) |
| Dados escala + catálogos | Dados existem; agregação só dia+turno no admin | Nova função `zelo_compute_coverage_by_location_language()` (ou equivalente) |
| Acesso `zelo_view_ops` / governança | Admin só `manage_options`; PWA ops exige `view_ops` mas sem painel | Decidir quem vê: todos `view_ops`, só `manage_ops` / supervisores, ou escopo por turno |

---

## 5. Decisões de produto (abertas)

| # | Pergunta | Opções | Impacto |
|---|----------|--------|---------|
| **D1** | Onde fica o painel? | **A)** Só admin WP · **B)** Só PWA · **C)** Admin completo + resumo PWA (card «Cobertura» na home ops) | Esforço e uso no chão de fábrica (mobile vs secretaria) |
| **D2** | Quem acede? | **A)** `zelo_manage_ops` · **B)** supervisores (`canSuperviseOps`) · **C)** qualquer `zelo_view_ops` | Segurança e ruído para voluntário comum |
| **D3** | Granularidade | **A)** dia + turno + posto + idioma · **B)** só turno actual / «agora» · **C)** dia inteiro agregado | Complexidade UI e utilidade em tempo real |
| **D4** | O que é «lacuna»? | **A)** idioma do catálogo com **0** voluntários designados naquele posto/turno · **B)** 0 com **check-in** activo · **C)** abaixo de **meta configurável** | D4-C exige novo modelo de metas (ver §6) |
| **D5** | O que é «excesso»? | **A)** mais designados que meta · **B)** mais check-in que designados · **C)** fora do escopo MVP | Só relevante se D4-C |
| **D6** | Metas por posto/idioma | **A)** Sem metas — só contagens e zeros em vermelho · **B)** Matriz editável no admin (ex.: Balcão 1 precisa ≥1 EN, ≥2 PT) | B aumenta escopo admin + validação |
| **D7** | Idiomas múltiplos por voluntário | Contar voluntário em **cada** idioma que fala (union) — recomendado | Já alinhado a `languages[]` na API |
| **D8** | Offline | **A)** Online only (re-fetch `/ops/voluntarios`) · **B)** derivar do cache offline da escala | B reutiliza snapshot existente; números podem ficar stale |

---

## 6. Modelo de dados proposto

### 6.1 Agregação (sem metas — MVP recomendado)

Chave: `(day, shift, location_name, language_name)`

Por célula:

| Métrica | Fonte |
|---------|--------|
| `planned` | nº linhas da escala com esse posto (via turno→local) e voluntário com esse idioma |
| `checked_in` | subset com check-in activo |
| `pending` | designados sem check-in |
| `gap` | `planned === 0` para idioma activo no catálogo **ou** idioma esperado sem cobertura (regra D4-A) |

**Posto:** derivado de `zelo_ops_schedule_row_location()` — um turno → um local no catálogo (modelo actual do evento).

**Idioma:** explode `languages[]` de cada linha; voluntário bilíngue incrementa duas colunas.

### 6.2 Metas opcionais (fase 2)

Novo bloco em `wp_options` (ex.: `coverage_requirements`):

```json
{
  "sext_a2": { "loc_balcao1": { "lang_en": 1, "lang_pt": 2 } }
}
```

Ou meta no catálogo `locations[].required_languages[]`. Só implementar se **D6 = B**.

---

## 7. Proposta técnica (MVP)

### 7.1 Backend (plugin)

1. Extrair lógica de `zelo_compute_coverage_rows()` para módulo reutilizável (ex.: `inc/volunteer-ops-coverage.php`).
2. Adicionar `zelo_compute_coverage_matrix( $filters = [] )` → array de células + totais + flags `gap` / `surplus`.
3. **Opção API:** `GET /zelo/v1/ops/coverage?day=&shift=` com `permission_callback` = login + (`zelo_manage_ops` **ou** supervisão — conforme D2).
4. Evoluir página admin existente **ou** redireccionar para matriz posto×idioma (mantendo tabela dia+turno como aba «Presença»).

### 7.2 Frontend (PWA) — se D1 incluir PWA

1. Nova view `coverage` ou secção colapsável em `renderVolunteerOps()` — visível se `canSuperviseOps()` ou `canManageOps()`.
2. Filtros dia/turno (reutilizar chips existentes).
3. Tabela responsiva: linhas = postos, colunas = idiomas activos; célula = `planned / checked_in`; destaque CSS para `gap`.
4. Sem novo fetch se dados já em `volunteerOps.schedule` + check-ins — agregação **no cliente** no MVP (menor risco); API dedicada se payload ficar pesado.

### 7.3 Wireframe sugerido (MVP PWA)

```
┌─ Cobertura ─────────────────────────┐
│ [Sexta ▼] [Turno A2 ▼]  Atualizar   │
├─────────────────────────────────────┤
│ Posto      │ PT │ EN │ ES │ …       │
│ Balcão 1   │ 2/2│ 0/0│ 1/1│         │  ← 0/0 EN em vermelho
│ Balcão 2   │ 1/1│ 1/0│ 2/2│         │
│ Informações│ 3/2│ 2/2│ —  │         │
└─────────────────────────────────────┘
  planned / checked_in
```

---

## 8. Permissões sugeridas

| Surface | MVP recomendado |
|---------|-----------------|
| Admin WP | Manter para `manage_options`; opcionalmente `zelo_manage_ops` via map_meta_cap |
| PWA | `canSuperviseOps() \|\| canManageOps()` — alinhado a realocar/editar escala |
| REST (se existir) | Mesmo gate que export PDF (`zelo_manage_ops`) ou supervisores com escopo no turno filtrado |

Documentar em `docs/OPS-PERMISSIONS.md` ao implementar.

---

## 9. Plano de implementação (estimativa)

| Fase | Entrega | Sessões |
|------|---------|---------|
| **0** | Fechar D1–D4 + wireframe mínimo | 0,5 (produto) |
| **1** | PHP: matriz posto×idioma + admin evoluído | 1 |
| **2** | PWA: view/resumo + i18n + CSS gaps | 1–1,5 |
| **3** | `GET /ops/coverage` (opcional) + testes `TESTING.md` | 0,5 |
| **4** | Metas configuráveis (só se D6=B) | +1–2 |

**Total MVP (sem metas, PWA + admin):** ~2–3 sessões.  
**Total com metas:** +1–2 sessões.

---

## 10. Riscos

| Risco | Mitigação |
|-------|-----------|
| Turno sem `location_id` no catálogo | Mostrar «Sem posto»; alerta no admin de catálogos |
| Voluntário sem idiomas cadastrados | Linha conta para posto mas não para colunas de idioma; badge «sem idioma» na escala (já visível) |
| Múltiplos postos no mesmo turno | Modelo actual = 1 local por turno; se escala real divergir, revisar catálogo antes do evento |
| Performance (escala grande) | Agregação server-side ou memoização; filtros obrigatórios dia+turno |
| Confusão com admin «Cobertura escala» actual | Renomear abas: «Presença por turno» vs «Posto × idioma» |

---

## 11. Recomendação (para aprovação)

| Tema | Proposta |
|------|----------|
| **Prioridade** | Média — útil para supervisores; não bloqueia operação (escala + filtros já existem) |
| **MVP** | **PWA** para supervisores/gestores + **evoluir admin** existente; **sem metas** (D4-A, D6-A); contagens planned/check-in por posto×idioma |
| **Não fazer no MVP** | Metas configuráveis, excesso automático, offline dedicado, endpoint público |
| **Reutilizar** | `zelo_compute_coverage_rows`, filtros ops PWA, catálogos, check-ins |
| **Próximo passo** | Confirmar **D1** (PWA vs admin) e **D2** (quem vê); aprovar wireframe §7.3 → mover issue para **Ready** |

---

## 12. Como testar (rascunho — após implementação)

1. Admin: cadastrar turnos com locais e voluntários com idiomas distintos.
2. Designar escala com lacuna intencional (ex.: Balcão 1 A2 sem falante de EN).
3. PWA como supervisor: abrir Cobertura → célula EN = 0/0 em destaque.
4. Check-in de voluntário EN → refrescar → 1/1.
5. Voluntário comum (`zelo_voluntario`): **não** deve ver painel (se D2=B).
6. Regressão: escala, filtros #11, export PDF inalterados.

---

## 13. Comparação com análise #8 (Web Push)

| | **#8 Web Push** | **#10 Cobertura** |
|---|-----------------|-------------------|
| Estado código | Stub 501 + SW preparado | Admin parcial (só dia+turno); dados na PWA |
| Decisão produto | **Descartado** — in-app basta (ADR-026) | **A implementar** — lacunas posto/idioma não são visíveis hoje |
| Bloqueador | Infra VAPID, permissões browser | Métricas e wireframe (D1–D4) |
| Esforço | Alto (infra + entrega) | Médio (agregação + UI) |
| Dependências | #9 motor notificações | #11 filtros (já done); catálogos ops |
