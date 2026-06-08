# Issue #28 — Mapa do estádio e direções internas

> **Issue:** [esvianna/ZELO#28](https://github.com/esvianna/ZELO/issues/28)  
> **Referência visual:** `docs/Departamentos_localizacao.pdf`  
> **Diagrama para produção (preferido):** `docs/Departamentos_localizacao_page-0001.jpg` — recorte sem margens, **4184×3374 px** (~1,8 MB)  
> **Export automático (legado):** `docs/assets/issue-28/estadio-diagrama.png` (2978×2105 — usar só se o JPG não estiver disponível)  
> **Status:** Opção 1 aprovada — CRUD com direções no formulário do destino; **Ready** para implementação  
> **Última atualização:** 2026-06-04

---

## 1. Objetivo

Permitir que **voluntários nos balcões** (previsto: **2 balcões**) orientem visitantes com **direções textuais** a partir do **balcão onde estão** até o **local desejado**, usando o diagrama do estádio, nos três idiomas da PWA.

**Privacidade:** departamentos **8 a 35** permanecem **fora** do app público; voluntários não devem indicar essas áreas.

---

## 2. Sequência de entrega (acordada)

| Ordem | Entrega | Quem | Saída |
|-------|---------|------|--------|
| **1** | PDF → imagem + marcar locais no diagrama | Equipe evento + dev (ferramenta admin) | PNG/WebP no WP; pinos `%` na imagem |
| **2** | Lista de departamentos/POIs públicos, ligados aos pinos | Admin (CRUD) | Catálogo `points[]` com nome, categoria, dept. |
| **3** | CRUD no WordPress para locais do mapa indoor | Dev (plugin) | Aba «Mapa do evento» — sem depender só de JSON |
| **4** | Mapear **balcões** (A1, B1…) no diagrama + matriz de direções | Admin + dev | `booths[]` + `routes[from][to]` |
| **5** | PWA: origem (balcão) → destino → texto de direção | Dev | View `mapa-evento` evoluída |

> **Regra:** não pular a fase 1 (imagem + pinos) antes do CRUD completo — o diagrama anotado valida o entendimento espacial com a equipe do evento.

---

## 3. Contexto no código hoje

| Peça | Situação |
|------|----------|
| `GET /zelo/v1/indoor-map` | Público; lê `indoor_map` em `zelo_volunteer_ops_data` |
| `catalogs.locations` + turnos A1–B2 | Nomes de posto/balcão na **escala** (`location_id` por turno) — **sem** coordenadas no diagrama |
| View PWA `mapa-evento` | Stub: imagem + pontos com `alert()` |
| Mapa Leaflet | Locais **externos** — permanece separado |

**Integração desejada:** balcões do mapa indoor **alinhados** aos códigos de turno A1/B1/A2/B2 (e opcionalmente a `catalogs.locations[].id`).

---

## 4. Fase 1 — Diagrama (imagem + pinos)

### 4.1 Conversão PDF → imagem

- Fonte: `docs/Departamentos_localizacao.pdf`
- Rascunho gerado: `docs/assets/issue-28/estadio-diagrama.png`
- Produção: reexportar em **WebP** (~2400 px largura) e enviar à **Biblioteca de mídia** do WordPress

### 4.2 Sinalizar locais na imagem

Dois modos (escolher na implementação):

| Modo | Prós | Contras |
|------|------|---------|
| **A — Pinos sobrepostos (PWA/admin)** | Coordenadas editáveis; lista ↔ mapa sincronizados | Precisa UI de clique no admin |
| **B — Marcadores desenhados no PNG** | Rápido para validar com equipe | Difícil manter; sem i18n por pin |

**Recomendação:** modo **A** — imagem limpa + pinos HTML/CSS com `x`,`y` em percentual (0–1), como o stub atual.

### 4.3 Tipos de locais (unificado no CRUD)

**Um único cadastro** com campo `kind` — balcões e destinos na mesma tabela, diferenciados na UI e no mapa:

| `kind` | Papel | Exemplos | Pino no mapa | Na PWA (público) |
|--------|--------|----------|--------------|------------------|
| `booth` | **Origem** das direções | Balcão 1, Balcão 2 *(nomes definidos pela equipe)* | ● quadrado, azul | Só select «Estou no balcão» |
| `department` | Destino (legenda PDF) | Dept. 1–6 nos camarotes | ● círculo, verde | Lista + mapa + busca |
| `facility` | Destino (rótulos no plano) | Ambulatório, perdidos e achados, guarda-volumes | ● círculo, laranja | Lista + mapa + busca |
| `amenity` | Destino **extra** (fora da legenda) | Banheiros, entradas, água, elevadores | ● círculo, cinza/teal | Lista + mapa + busca |
| `restricted` | Backstage (dept. 8–35) | Broadcasting, transporte… | **Não expor** | **Oculto** |

**Por que unificar balcão + POI no mesmo CRUD**

- Um **editor visual** único: clique no mapa → escolhe tipo → grava `x`,`y`.
- Balcões **não** entram como destino na busca do visitante — só como origem.
- Rotas referenciam IDs do mesmo catálogo (`from` = `kind: booth`, `to` = qualquer destino público).

**Marcação no admin (proposta UX)**

```
[ Imagem do diagrama — clicável ]
  • Arrastar pin para reposicionar
  • Legenda lateral: ■ Balcão  ● Dept.  ● Serviço  ● Extra
  • Lista à esquerda: filtrar por kind | «Sem posição no mapa» (alerta)
```

MVP aceitável: clique define posição + campos `x`/`y` % editáveis manualmente.

---

## 5. Fase 2 — Locais além da legenda

A tabela **Camarote ↔ Departamento** do PDF **não cobre tudo** o que visitantes e voluntários perguntam. Dept. **7** nem aparece (salta 6 → 8). Vários serviços existem só como **rótulos no desenho** (ambulatórios, catracas…) — não na tabela.

### 5.1 Três fontes de POIs

| Fonte | O que cadastrar | `kind` |
|-------|-----------------|--------|
| **Legenda PDF** (dept. 1–6 públicos) | Indicadores, hospedagem, informações, apoio ao público, PID, autoridade | `department` |
| **Rótulos no plano** | Ambulatórios 2/3/4, perdidos e achados, informações P1/P2, catracas, escadas, guarda-volumes, montagem | `facility` |
| **Extras operacionais** *(equipe define)* | Banheiros (M/F/acessível), entradas principais, credenciamento, água, lanchonete, ponto de táxi/shuttle | `amenity` |

Dept. **8–35**: cadastrar só se útil para gestão interna, sempre `restricted` — **nunca** na API pública.

### 5.2 Sugestão de extras (validar com equipe)

| POI extra | Prioridade | Notas |
|-----------|------------|-------|
| Entrada / catracas (N, S, L…) | Alta | Já rotulado «CATRACAS» — pode ser 1 pin por cluster |
| Banheiros | Alta | Vários pinos; pavimento no nome («Banheiro P2 — setor leste») |
| Escadas / elevadores | Média | Referência para subir/descer pavimento |
| Ambulatórios | Alta | Já no plano — 3–4 pinos |
| Perdidos e achados | Alta | Já no plano |
| Guarda-volumes | Média | Já no plano |
| Palco / área visitantes | Baixa | Só se visitantes perguntarem |

### 5.3 Campos por local (lista + mapa)

- `kind`, `visibility` (derivada: restricted se dept ≥ 8)  
- Nome **pt_br / en / es**  
- `category`: atendimento | saúde | acesso | serviços | higiene | alimentação  
- `floor`: subsolo | P1 | P2 | P3  
- `dept_number` opcional (só `department`)  
- `shift_code` ou `booth_label` opcional (só `booth`) — vínculo opcional a turno/local da escala se fizer sentido  
- `x`, `y` (0–1) — **obrigatório** para aparecer no diagrama  
- `keywords` para busca  
- `location_id` opcional — vínculo a `catalogs.locations[]` (balcões)

---

## 6. Fase 3 — CRUD admin (WordPress)

Aba **«Mapa do evento»** em Zelo Assistente → Operação de voluntários.

### 6.1 Estrutura da tela

| Área | Conteúdo |
|------|----------|
| **Cabeçalho** | Upload imagem (JPG/WebP), preview, dimensões |
| **Editor mapa** | Imagem + pinos arrastáveis; cores por `kind` |
| **Tabela locais** | CRUD unificado; colunas: tipo, nome, pavimento, posição, ativo |
| **Rotas** | Ver § 6.5 — direções **no formulário de cada destino** (sem CSV) |

### 6.2 Fluxo de cadastro (balcão ou POI)

1. **Novo local** → escolher tipo (Balcão | Departamento | Serviço no plano | Extra).  
2. Preencher nomes e pavimento.  
3. **Marcar no mapa** — clique na imagem ou arrastar pin existente.  
4. Se **balcão**: rótulo (ex. «Informações P1 norte»); vínculo opcional a `catalogs.locations[]`.  
5. Se **destino**: preencher direções desde **Balcão 1** e **Balcão 2** (pt / en / es).  
6. Se **departamento**: opcional `dept_number` (1–6); ≥ 8 bloqueia como restrito.  
7. Salvar → aparece na lista e no diagrama (se público).

### 6.5 Cadastro de direções — **Opção 1** (decidido)

Com **2 balcões**, todo o conteúdo é cadastrado **só no CRUD** — **sem CSV**.

**Implementação v1:** rotas no **formulário de cada destino** (`department`, `facility`, `amenity`).

Ao editar um destino, o admin mostra:

```
┌─ Direções desde o Balcão 1 ─────────────────────┐
│ Português: [________________________]          │
│ English:   [________________________]          │
│ Español:   [________________________]          │
└────────────────────────────────────────────────┘
┌─ Direções desde o Balcão 2 ─────────────────────┐
│ (mesmos 3 campos)                               │
└────────────────────────────────────────────────┘
```

- Balcões: cadastro único (`kind: booth` + pin no mapa); máximo **2** activos na v1.
- Cada destino persiste `directions_from_booths[]` (2 entradas × 3 idiomas); normalizado para `routes[]` no save.
- Lista de destinos: coluna **«Rotas OK?»** (0/2 · 1/2 · 2/2).

**Fora de escopo v1:** import CSV; grelha tipo planilha (opção 2); direção única genérica (opção 3).

### 6.3 Funcionalidades mínimas

1. Upload / URL da imagem  
2. **CRUD unificado** `places[]` com `kind`  
3. **Editor visual** de pinos  
4. **Direções por destino** — 2 blocos (balcão 1 e 2) × 3 idiomas, no mesmo ecrã do local  
5. Sanitização: dept. ≥ 8 ou `restricted` → excluir da API pública  

### 6.4 PWA — como reflete a diferenciação

| Elemento | Comportamento |
|----------|----------------|
| Select origem | Só locais `kind: booth` |
| Busca / lista destinos | `department` + `facility` + `amenity` (públicos) |
| Diagrama | Cores/formas distintas; balcões destacados se origem selecionada |
| Rotas | Só pares cadastrados; fallback: «Direção ainda não cadastrada — consulte supervisor» |

### Permissões

- Editar mapa: `manage_options` ou capability ops de gestão  
- Leitura: `GET /indoor-map` (payload filtrado)

---

## 7. Fase 4 — Direções a partir do balcão

### Conceito

O visitante está **com o voluntário no balcão X**. A direção não é genérica («como chegar ao ambulatório»), e sim **contextualizada**:

> **De:** Balcão B1 (P1, junto às catracas norte)  
> **Para:** Ambulatório 2  
> **Instrução:** «Siga reto pela arquibancada, suba a escada à direita…»

### Modelo de dados (extensão de `indoor_map`)

```json
{
  "image_url": "https://…/estadio-diagrama.webp",
  "width": 4184,
  "height": 3374,
  "places": [
    {
      "id": "booth-a1",
      "kind": "booth",
      "shift_code": "A1",
      "location_id": "loc_xxxxxxxx",
      "floor": "P1",
      "x": 0.31,
      "y": 0.72,
      "labels": { "pt_br": "Balcão A1", "en": "Counter A1", "es": "Mostrador A1" },
      "active": true
    },
    {
      "id": "dept-3",
      "kind": "department",
      "dept_number": 3,
      "visibility": "public",
      "category": "atendimento",
      "floor": "P1",
      "x": 0.42,
      "y": 0.55,
      "labels": { "pt_br": "Informações e Serviço Voluntário", "en": "…", "es": "…" },
      "keywords": ["informações", "information"]
    },
    {
      "id": "wc-p2-east",
      "kind": "amenity",
      "category": "higiene",
      "floor": "P2",
      "x": 0.58,
      "y": 0.48,
      "labels": { "pt_br": "Banheiros — P2 setor leste", "en": "Restrooms — E2 upper level", "es": "…" },
      "keywords": ["banheiro", "wc", "restroom", "baño"]
    }
  ],
  "routes": [
    {
      "from_place_id": "booth-a1",
      "to_place_id": "wc-p2-east",
      "directions": {
        "pt_br": "Do balcão A1, suba ao P2 pela escada mais próxima…",
        "en": "From counter A1, go up to level 2…",
        "es": "…"
      }
    }
  ],
  "volunteer_notice": {
    "pt_br": "Não indique a localização de departamentos restritos (8–35).",
    "en": "Do not give directions to restricted departments (8–35).",
    "es": "No indique departamentos restringidos (8–35)."
  }
}
```

> **Nota:** `booths[]` + `points[]` do rascunho anterior fundem-se em **`places[]`** com `kind`.

### API pública

- `GET /indoor-map` → `image`, `places` (só kinds públicos; booths incluídos para select origem), `routes`, `volunteer_notice`  
- **Nunca** expor `kind: restricted` nem metadados de dept. 8–35  

### UX PWA (voluntário)

```
Mapa do evento
  1. «Estou no balcão» → select Balcão 1 | Balcão 2
     (pré-selecionar se escala do dia indica turno + local)
  2. «Para onde?» → busca / lista / toque no diagrama
  3. Painel: mapa com origem (●) e destino (●) + texto de direção
     + «Copiar» + aviso volunteer_notice
```

Abas complementares: **Orientar** (fluxo acima) | **Diagrama** | **Lista**

---

## 8. Relação balcões ↔ escala existente

| Sistema | Campo | Uso no mapa |
|---------|-------|-------------|
| Turnos | `catalogs.shifts[].code` | A1, B1, A2, B2 |
| Locais escala | `catalogs.locations[].name` | Rótulo humano («Entrada principal») |
| Turno → local | `shifts[].location_id` | Sugerir balcão default na PWA |
| Mapa indoor | `booths[].shift_code` + `location_id` | Posição no diagrama + rotas |

**Não duplicar** nomes: o CRUD de balcões **referencia** o catálogo de locais quando existir; senão, rótulo livre no mapa.

---

## 9. Critérios de aceite (atualizados)

- [ ] Diagrama exportado do PDF e publicado (WebP/PNG responsivo).
- [ ] Locais públicos cadastrados via **CRUD admin** com pinos no diagrama.
- [ ] Lista buscável na PWA, sincronizada com pinos.
- [ ] **2 balcões** posicionados no diagrama.
- [ ] Direções **por par balcão → destino** nos 3 idiomas.
- [ ] Dept. 8–35 ausentes da API e da UI.
- [ ] Aviso ao voluntário sobre áreas restritas.
- [ ] Offline após primeiro carregamento.
- [ ] Testes em `TESTING.md`.

---

## 10. Esforço estimado (dev)

| Bloco | Sessões |
|-------|---------|
| CRUD admin + sanitização API | 1–2 |
| Editor pinos (clique ou % manual) | 1 |
| Matriz rotas no admin | 1 |
| PWA origem→destino + diagrama | 1–2 |
| **Total** | **4–6** sessões focadas |

Fase 1 (imagem + validação pinos com equipe) pode começar **antes** do CRUD, usando JSON temporário ou planilha.

---

## 11. Próximos passos imediatos

1. **Equipe evento:** revisar `docs/assets/issue-28/estadio-diagrama.png` e marcar (rascunho) onde ficam A1, B1, A2, B2 e POIs públicos.  
2. **Confirmar** dept. 7 e lista de infraestrutura pública.  
3. **Issue #28 → Ready** no Project quando a lista de pinos estiver validada.  
4. **Implementação:** CRUD → API → PWA → **In review**.

---

## Referências

- ADR-022 — mapa indoor e direções por balcão (`DECISIONS.md`)
- `frontend-pwa/assets/js/app-v5.js` — `renderIndoorEventMap()`
- `backend-plugin/zelo-assistente/inc/api-routes.php` — `zelo_get_indoor_map_public()`
- `backend-plugin/zelo-assistente/inc/volunteer-ops-catalogs.php` — turnos e `locations`
- ADR-017 — local vinculado ao turno
