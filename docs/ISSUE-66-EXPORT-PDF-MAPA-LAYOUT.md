# Issue #66 — Export PDF mapa: cabeçalho com logo + legenda por pavimento

> **Issue:** [esvianna/ZELO#66](https://github.com/esvianna/ZELO/issues/66)
> **Status:** **In review** — plugin **2.26.0**; smoke **2z7j**  
> **Relacionadas:** [#62](https://github.com/esvianna/ZELO/issues/62)–[#64](https://github.com/esvianna/ZELO/issues/64) export PDF mapa  
> **Wireframe visual:** [`docs/wireframes/issue-66-pdf-mapa-wireframe.png`](wireframes/issue-66-pdf-mapa-wireframe.png)  
> **Última atualização:** 2026-06-25

---

## 1. Objetivo

Com **aumento do número de locais** no mapa do evento, o PDF exportado (plugin **2.24.8**) deixa de escalar:

- Legenda plana «N — Nome» (32 mm) quebra linhas e ocupa muita altura.
- Cabeçalho genérico (`{site} — Mapa do evento — data`) sem identidade visual.

**Pedido:**

1. **Reduzir ligeiramente** a área do diagrama para **dar mais espaço à legenda**.
2. **Agrupar legenda por pavimento** (coluna «Pav.» do admin).
3. **Cabeçalho** com **logo Zelo** + título **«Mapa»** + subtítulo do evento (ex.: *Congresso Internacional Curitiba 2026*).

**Fora do escopo:** alterar PWA/admin visual do mapa; overlay de legenda (#64.6–7 revertido); pinos/números vetoriais (#64.5) — **manter**; **numeração de páginas** («Página X de Y») — **não gerar** no PDF (artefacto do wireframe visual apenas).

---

## 2. Estado actual (2.24.8)

| Elemento | Valor / comportamento |
|----------|------------------------|
| Mapa | ~253 mm × altura máx. útil |
| Legenda | 32 mm à direita; lista plana ordem cadastro |
| Pavimento | Só cor do pino; **não** na legenda PDF |
| Cabeçalho | 1 linha texto ~11 pt |
| Overflow legenda | Página 2, coluna 120 mm |

---

## 3. Layout proposto (wireframe)

> **Nota:** o wireframe visual ilustra só layout e proporções. O PDF **não** inclui rodapé «Página X de Y» (o export FPDF actual não define `Footer` nem numeração).

### 3.1 Página 1 — A4 paisagem (297 × 210 mm)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────┐
│ margem 8mm                                                                                   │
│  ┌────┐  Mapa                                              gerado 25/06/2026 (rodapé opt.) │
│  │LOGO│  Congresso Internacional Curitiba 2026                                               │
│  └────┘  (~12–14 mm altura cabeçalho)                                                        │
├──────────────────────────────────────────────────────────────┬───────────────────────────────┤
│                                                              │ Legenda:                      │
│                                                              │                               │
│                    DIAGRAMA DO ESTÁDIO                       │ Sub-solo                      │
│                    (PNG + pinos coloridos)                   │  ● 13 — Ambulatório 4         │
│                                                              │                               │
│                    ~230 mm largura                           │ Piso 1                        │
│                    números vetor nos pinos                   │  ■  1 — Balcão 1              │
│                                                              │  ■  2 — Balcão 2              │
│                                                              │  ●  9 — Achados e Perdidos    │
│                                                              │  ● 11 — Ambulatório 2         │
│                                                              │                               │
│  Rua Getúlio Vargas (visível)                                │ Piso 2                        │
│                                                              │  ● 10 — Indicadores           │
│                                                              │  ● 15 — Limpeza               │
│                                                              │  ...                          │
│                                                              │                               │
│                                                              │ ~50 mm largura                │
│                                                              │ grupos + overflow → pág. 2    │
└──────────────────────────────────────────────────────────────┴───────────────────────────────┘
         ↑ map_x=8 mm                    ↑ leg_x ≈ 239 mm (281 - 50 + 8)
```

### 3.2 Métricas alvo (aprovação)

| Parâmetro | Actual | Proposto |
|-----------|--------|----------|
| `map_w` | 253 mm | **~230 mm** → **~281 mm** (2.26.1 overlay) |
| `leg_w` | 32 mm | **~50 mm** (overlay sobre mapa) |
| Cabeçalho | 5 mm (1 linha) | logo **16 mm** + título **1 linha** «Mapa {evento}» |
| `map_h` | derivado | reduz ~4 mm vs hoje (cabeçalho maior) |

### 3.3 Página 2 (se legenda não couber)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────┐
│ Legenda (continuação):                                                                       │
│                                                                                              │
│ Piso 3                                                                                       │
│   ●  4 — Depart. Hospedagem                                                                  │
│   ●  5 — Depart. Infor. e Serv. Voluntário                                                   │
│   ...                                                                                        │
│                                                                                              │
│ (coluna larga ~120 mm — padrão actual)                                                       │
└──────────────────────────────────────────────────────────────────────────────────────────────┘
```

### 3.4 Diagrama Mermaid (proporções)

```mermaid
block-beta
  columns 5
  block:header:5
    logo["Logo Zelo\n10mm"]
    title["Mapa\nCongresso Internacional Curitiba 2026"]
  space
  block:body:5
    columns 4
    map["Diagrama\n~230mm"]:3
    legend["Legenda por pavimento\n~50mm"]:1
```

---

## 4. Legenda agrupada — regras

| Regra | Decisão proposta |
|-------|------------------|
| Agrupamento | Campo `floor` de cada local (`place['floor']`) |
| Balcões | Entram no pavimento da coluna «Pav.» (não grupo separado) |
| Pavimento vazio | Grupo **«Outros»** no fim |
| Ordem dos grupos | `strnatcasecmp` (Sub-solo → Piso 1 → Piso 2 …) |
| Ordem dentro do grupo | **Numeração global** 1…N (igual pinos no mapa) |
| Formato entrada | swatch + «N — Nome» (sem repetir pavimento na linha) |
| Cabeçalho grupo | Bold ~7 pt; entrada ~6,5 pt |

---

## 5. Cabeçalho — regras

| Elemento | Fonte |
|----------|--------|
| Logo | `assets/img/zelo-logo-pdf.png` (derivado ícone PWA 256 px) |
| Linha 1 | **«Mapa»** (fixo PT) |
| Linha 2 | `zelo_event_data['nome']` ou `titulo` (ex. Congresso Internacional Curitiba 2026) |
| Data/hora | Rodapé pequeno (8 pt) ou canto superior direito — **não** linha principal |
| Numeração | **Não** incluir «Página X de Y» (FPDF actual não usa `Footer`; manter assim) |

Fallback sem logo: texto «Zelo» em bold.

---

## 6. Implementação prevista

| Ficheiro | Alteração |
|----------|-----------|
| `inc/indoor-map-export.php` | Layout metrics; `zelo_indoor_map_pdf_render_header()`; `zelo_indoor_map_pdf_group_by_floor()`; legenda agrupada |
| `assets/img/zelo-logo-pdf.png` | Logo PDF (novo) |
| `TESTING.md` | Caso **2z7j** |
| `DECISIONS.md` | ADR-044 (curto) |

**Preservar:** pinos GD, números vetor FPDF, coluna lateral (sem overlay), escala que mantém ruas visíveis (#64.8).

---

## 7. Critérios de aceite

1. PDF com logo + «Mapa» + nome do evento no cabeçalho.
2. Legenda agrupada por pavimento; entradas «N — Nome» legíveis (menos quebras que 2.24.8).
3. Mapa ~230 mm; ruas e pinos da margem **não cortados** (smoke diagrama real).
4. Overflow legenda → página 2.
5. Admin/PWA inalterados.
6. **Sem** rodapé «Página X de Y» em nenhuma página.

---

## 8. Como testar (rascunho)

| ID | Caso | Passos |
|----|------|--------|
| 2z7j | PDF legenda por pavimento + cabeçalho | Exportar mapa; verificar grupos Sub-solo/Piso N; logo + título; sem corte ruas |

---

## 9. Riscos

| Risco | Mitigação |
|-------|-----------|
| Mapa corta ruas | Manter ~230 mm (não 253+); smoke #64 |
| Pavimentos inconsistentes no cadastro | Convenção «Piso 1» / «Sub-solo» no admin |
| Legenda longa | Pág. 2; fonte 6 pt se necessário |

---

## 10. Versão estimada

Plugin **2.26.0** (só backend, sem PWA).
