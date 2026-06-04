# Assets — Issue #28 (mapa do estádio)

| Arquivo | Descrição | Uso |
|---------|-----------|-----|
| **`../Departamentos_localizacao_page-0001.jpg`** | Recorte em alta resolução (4184×3374), sem margens do PDF | **Fonte preferida** para PWA + admin |
| `estadio-diagrama.png` | Export automático do PDF (2978×2105) | Fallback / referência |

## Por que o JPG recortado é melhor

- Remove margens e rodapé técnico do PDF — mais área útil no telemóvel.
- Legenda dos camarotes (tabela dept. 1–35) permanece visível no canto.
- Resolução suficiente para **pinch/zoom** na fase 2 da PWA.

## Antes do deploy

1. Converter para **WebP** (~400–600 KB) a partir do JPG — manter JPG como master no repo.
2. Enviar WebP à Biblioteca de mídia WordPress.
3. Pinos via CRUD admin (coordenadas `%` sobre a imagem limpa).

## Próximo passo operacional

Marcar no diagrama (ou planilha) posições de:

- Balcões **A1, B1, A2, B2** (provavelmente junto aos pontos «Informações e Serviço Voluntário» P1/P2/P3)
- Dept. públicos **1–6** (+ confirmar se existe dept. **7** — tabela salta de 6 para 8)
- Infraestrutura: ambulatórios, perdidos e achados, catracas, escadas
