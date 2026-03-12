# Changelog — Zelo Assistente

Todas as alterações relevantes ao plugin backend do Zelo são documentadas aqui.

## [2.4.5] - 2026-03-12

### Correções (PWA)
- **Universal Open Status Engine**: Implementada identificação de dia da semana via índice numérico (0-6), tornando o filtro "Aberto agora" imune a variações de idioma.
- **Parser de Tempo Blindado**: O motor agora entende simultaneamente formatos AM/PM e 24h, além de suportar múltiplos tipos de caracteres de traço do Google.
- **IA de Extração de Endereço**: Nova lógica de busca de fragmentos que identifica automaticamente "Bairro, Cidade" e ignora partes puramente numéricas ou irrelevantes (CEP, N°, Siglas).

## [2.4.4] - 2026-03-12

### Correções (PWA)
- **Suporte AM/PM no Horário**: O parser agora identifica e converte horários no formato 12h (AM/PM), garantindo que o filtro "Aberto agora" funcione em todos os locais.
- **Filtro de "Sujeira" Extremo**: Dropdowns de Bairro e Cidade agora ignoram agressivamente números de casa, CEPs isolados e siglas de estado que poluíam a lista.
- **Sincronização de Status**: A lógica de abertura no detalhe e na pesquisa agora utilizam o mesmo motor de parsing robusto.

## [2.4.3] - 2026-03-12

### Correções (PWA)
- **Sanitização de Filtros**: Implementada lógica para ignorar números de residência, CEPs e siglas isoladas nos dropdowns de Bairro e Cidade.
- **Correção "Aberto agora"**: Ajustado para reconhecer dias da semana em Inglês e Português simultaneamente, corrigindo a falha no filtro.
- **Robustez de Endereço**: Melhorada a separação de rua, bairro e cidade em endereços com formatos mistos.

## [2.4.2] - 2026-03-12

### Correções (PWA)
- **Filtro "Aberto agora"**: Implementado parser de horários inteligente que valida a abertura em tempo real, além de locais 24h.
- **Filtros de Localidade**: Refinada a extração de Bairro e Cidade do endereço para evitar dados duplicados ou incorretos nos dropdowns.
- **Status de Funcionamento**: Novo distintivo visual de "Aberto" ou "Fechado" nos detalhes baseado no horário real.

## [2.4.1] - 2026-03-12

### Correções
- **Imagens no PWA**: Corrigido problema onde as imagens importadas não eram exibidas nas visualizações do aplicativo.
- **UI de Detalhes**: Adicionado suporte a imagem de fundo (hero) com overlay de legibilidade no PWA.
- **Lista de Locais**: Miniaturas agora priorizam a foto real do local em vez do ícone da categoria.

## [2.4.0] - 2026-03-12

### Funcionalidades
- **Coluna de Miniaturas**: Adicionado campo visual de "Foto" na listagem de Locais para facilitar a identificação rápida.
- **Filtro de Imagem**: Novo filtro "Com Imagem / Sem Imagem" para localizar rapidamente locais que possuem ou não fotos vinculadas.
- **Melhoria da UX**: Adicionado ícone de fallback quando um local não possui imagem.

## [2.3.0] - 2026-03-12

### Funcionalidades
- **Filtros por Categoria**: Adicionado seletor de filtros na listagem de Locais para facilitar a visualização por tipo.
- **Zona de Perigo**: O botão "Limpar todos os locais" foi movido da listagem geral para uma seção segura dentro das Configurações do Evento.
- **Prevenção de Cliques Acidentais**: Melhorada a UI e feedback do botão de limpeza global.

## [2.2.1] - 2026-03-12

### Correções
- Corrigido erro fatal no novo importador AJAX causado por uma função faltante (`zelo_get_google_types_for_category`) que foi removida acidentalmente durante a refatoração.

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
