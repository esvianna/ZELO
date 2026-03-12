# Changelog — Zelo Assistente

Todas as alterações relevantes ao plugin backend do Zelo são documentadas aqui.

## [2.2.0] - 2026-03-12

### Funcionalidades
- **Importador AJAX com Barra de Progresso**: O importador do Google Places agora processa os locais um a um, evitando timeouts no servidor.
- **Feedback Visual**: Barra de progresso em tempo real durante o processo de importação.
- **Relatório Detalhado**: Resumo ao final da importação mostrando total processado, novos locais, locais atualizados e fotos importadas.
- **Estabilidade**: Limite de importação ajustado para 100 itens por rodada via processamento assíncrono.

## [2.1.1] - 2026-03-12

### Correções
- Corrigido "Erro Crítico" durante importação do Google Places reduzindo o limite de processamento de 600 para 60 itens por execução.
- Adicionado `set_time_limit(300)` para evitar timeouts do PHP em servidores com limites baixos.
- Removidos delays artificiais (`sleep`) para acelerar a importação.
- Atualizado aviso na página do importador para informar o limite estável de 60 locais.

## [2.1.0] - 2026-03-12

### Funcionalidades
- Nova página admin "Categorias de Locais" para gerenciar categorias dinamicamente (CRUD)
- Cada categoria define um slug, rótulo e tipos Google Places associados
- Dropdown do meta box e do importador agora leem das categorias cadastradas
- Botão "Restaurar Padrão" para resetar categorias originais
- Suporte a categorias ilimitadas (cultura, compras, lazer, restaurante, etc.)

### Melhorias
- Importador Google Places agora busca múltiplos tipos por categoria automaticamente

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
