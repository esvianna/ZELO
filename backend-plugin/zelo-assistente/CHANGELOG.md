# Changelog — Zelo Assistente

Todas as alterações relevantes ao plugin backend do Zelo são documentadas aqui.

## [2.0.0] - 2026-03-12

### Segurança
- Adicionado `esc_html()` nas colunas customizadas do admin (categoria, endereço, telefone)
- Substituído `_e()` por `esc_html_e()` em todas as labels do meta box de detalhes
- Adicionado escape na notice de sucesso da página de Configurações
- Adicionado escape e internacionalização na notice de importação OSM

### Infraestrutura
- Criado `CHANGELOG.md` para rastreabilidade de versões
- Versão atualizada de `1.0.0` para `2.0.0`

## [1.0.0] - Lançamento Inicial

### Funcionalidades
- Custom Post Type `zelo_local` com campos: categoria, endereço, lat/lng, telefone, horário, 24h
- Meta boxes para edição dos campos do local
- REST API (`/zelo/v1/locais` e `/zelo/v1/evento`) com filtro por distância
- API de autenticação (`/zelo/v1/auth/login`)
- Página de configurações do evento (nome, endereço, coordenadas, contatos, Wi-Fi, credenciamento, transporte, avisos)
- Importador OpenStreetMap (Overpass API) com lógica de upsert
- Importador CSV simples com mapeamento automático de colunas
- Importador CSV com mapeamento manual de colunas (ex.: CNES)
- Importador Google Places (Nearby Search + Place Details) com grid de busca
- Enriquecimento individual de locais via Google Places
- Importação automática de fotos do Google Places como imagem destacada
- Botão "Limpar todos os locais" na listagem do admin
