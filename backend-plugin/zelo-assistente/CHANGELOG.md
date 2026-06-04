# Changelog — Zelo Assistente

Todas as alterações relevantes ao plugin backend do Zelo são documentadas aqui.

## [2.11.4] - 2026-06-04

### Adicionado
- Payload `/ops/voluntarios`: `shift_contacts` (homem-chave por dia/turno) e `volunteer_phone` em cada linha da escala (WP `zelo_phone` ou roster).

## [2.11.3] - 2026-06-04

### Corrigido
- `zelo_get_volunteer_ops_payload`: voluntário com escala da equipa recebe `status` de compromisso de colegas (antes só as próprias linhas → badge «Aguardando confirmação» incorreto na equipa).

## [2.11.2] - 2026-06-02

### Corrigido
- `zelo_commitment_mark_schedule_changed`: ao exigir reconfirmação, preserva `prior_commitment` (status, `committed_at`, `committed_by`, etc.) do aceite/recusa anterior em vez de apagar o histórico.

## [2.11.1] - 2026-06-02

### Corrigido
- `zelo_ops_apply_schedule_scope`: reconciliação por fingerprint em vez de limpar compromissos de todo o turno; histórico `schedule_patch` com campo `reconcile`.

### Adicionado
- `zelo_commitment_mark_schedule_changed`, `zelo_get_commitment_pending_reason`, `pending_reason` / `schedule_changed_at` no compromisso.
- Cron: e-mail «Sua escala mudou — confirme no Zelo» (`schedule_changed`, dedup por designação).

## [2.11.0] - 2026-06-02

### Adicionado
- REST `POST /zelo/v1/ops/schedule` — merge escopado por dia+turno; validação `zelo_validate_schedule_rows`; limpeza de compromissos/check-ins de linhas removidas; histórico `schedule_patch`.
- Capability `zelo_edit_schedule` (homem-chave, supervisores, administrator); migração em `init`.
- Payload `/ops/voluntarios`: `permissions` (`schedule_view`, `schedule_edit`, `supervise_ops`); catálogos de editor (`shifts`, `locations`, `roster_volunteers`, `wp_users`) só para quem pode editar.
- Escala completa no GET para utilizadores com `zelo_view_ops` (parâmetro `?mine=1` mantido para dashboard leve).

### Alterado
- `POST /ops/reallocate` exige `zelo_user_can_supervise_assignment` na designação.
- Governança oculta no payload para voluntários sem papel de supervisão.

## [2.10.2] - 2026-06-02

### Corrigido
- Admin escala: coluna **Local** somente leitura (alinhada ao cabeçalho; valor do turno; sem `sched_loc_id`).

## [2.10.1] - 2026-06-02

### Alterado
- **Turnos:** campo `location_id` (select na aba Turnos). Linhas da escala recebem `location` do turno; coluna Local removida da aba Escala.
- Migração idempotente: infere `location_id` do turno a partir da escala existente (local mais frequente por código).

## [2.10.0] - 2026-06-02

### Adicionado
- **Escala:** campos `start`/`end` por linha (admin: inputs `type="time"`); persistidos em `schedule[]`; API/PWA/check-in/export usam faixa real.
- Validação: horário dentro do intervalo do turno no catálogo; duplicata apenas se dia+turno+voluntário+início+fim forem iguais (várias faixas no mesmo turno permitidas).

## [2.9.4] - 2026-05-29

### Adicionado
- Admin **Onboarding**: lista cadastros com e-mail não confirmado; botão **Confirmar cadastro** (`zelo_admin_approve_user_registration`, meta `zelo_email_verified_by` / `_at` / `_method`).

## [2.9.3] - 2026-05-29

### Corrigido
- Export PDF: pasta `inc/lib/font/` com definições core FPDF (Helvetica bold, etc.) — faltava no deploy.
- `FPDF_FONTPATH` definido para o plugin (evita path errado quando FPDF é carregado via ficheiro temporário em PHP 8.2+).

## [2.9.2] - 2026-05-29

### Corrigido
- Export PDF: `zelo_ops_require_fpdf()` — `str_replace(..., 1)` quebrava no PHP 8+ (4º argumento por referência); uso de `substr_replace` para injetar `AllowDynamicProperties`.

## [2.9.1] - 2026-05-29

### Corrigido
- Export PDF (`/ops/export`): `try/catch`, supressão de deprecações FPDF em PHP 8.2+, layout em paisagem, truncagem de células e valores escalares seguros (evita HTTP 500 / erro crítico WP).

## [2.9.0] - 2026-05-28

### Adicionado
- `GET /zelo/v1/ops/export` — PDF da escala (FPDF em `inc/lib/fpdf.php`), CSV opcional; filtros `day`, `shift`.
- Permissão `zelo_manage_ops` (e `manage_options`); rate limit 60 s por utilizador.

## [2.8.0] - 2026-05-28

### Adicionado
- Idiomas no voluntário: `roster_volunteers.language_ids`, `user_meta` `zelo_language_ids`.
- Migração idempotente de `schedule.languages` para roster/WP; herança em `zelo_ops_enrich_schedule_for_output`.
- REST `GET /zelo/v1/ops/languages` (público), `PATCH /zelo/v1/auth/profile`.
- Cadastro aceita `language_ids` opcional; sessão/login expõe `language_ids` e `languages`.

### Alterado
- Admin: coluna Idiomas removida da escala; multi-select na aba Voluntários e coluna Idiomas no Onboarding.

## [2.7.2] - 2026-05-28

### Corrigido
- Onboarding admin: lista todas as designações da escala (`schedule_items`); contagem de designações por voluntário resolve vínculo por nome/WP além de `roster_volunteer_id`.

## [2.7.1] - 2026-05-28

### Adicionado
- `zelo_ops_day_label()` / `zelo_ops_day_choices_with_labels()` — rótulo de dia com data (`event_dates`).
- Migração: governança para sexta/sábado/domingo; cópia de supervisores da sexta quando vazios; horários legados A1–B2 atualizados se ainda default antigo.
- Admin: selects e títulos de governança com data; textos de ajuda escala/governança.

## [2.7.0] - 2026-05-28

### Adicionado
- Compromisso antecipado por designação (`zelo_volunteer_commitments`): aceitar/recusar com prazo `commitment_deadline` no admin.
- Janelas configuráveis de check-in/check-out e lembretes (`settings.presence`).
- Governança com `*_supervisor_id` e `keymen_user_ids` para alertas por e-mail na recusa.
- Fila `zelo_link_requests` + aprovação admin; matching por e-mail no cadastro.
- REST: `POST /ops/assignments/{id}/commit`, `GET /ops/onboarding`, `GET/POST /ops/link-requests/*`, stub `POST /ops/push/subscribe` (501).
- Validação de check-in/out: titular ou supervisor, compromisso aceito, dia do evento, janela horária.
- Aba admin **Onboarding**; roster com e-mail esperado e status de cadastro.
- Hook `zelo_notification_dispatch` (base motor unificado).

## [2.6.5] - 2026-05-28

### Adicionado
- `GET /zelo/v1/clima`: previsão do tempo via Open-Meteo (coordenadas do evento), cache transient 30 min, payload normalizado para a PWA.
- Módulo `inc/weather.php` (mapeamento WMO, fallback stale).
- Admin: checkbox «Ativar previsão do tempo» nas configurações do evento.

## [2.6.4] - 2026-05-28

### Corrigido
- `zelo_ops_find_shift_by_code`: não acede a `$catalogs['shifts']` sem validar (evita warning com catálogo vazio/parcial).
- Notificações de voluntários: catálogos via `zelo_get_ops_catalogs()` antes de resolver horários da escala.

## [2.6.3] - 2026-05-28

### Alterado
- Escala admin: select **Contas WordPress** inclui utilizadores com role `administrator` (além dos roles Zelo).

## [2.6.2] - 2026-05-28

### Corrigido
- Escala: `sched_lang_ids[N][]` com índice por linha (idiomas em linhas adicionadas via JS).
- Save: `start`/`end` gravados a partir do catálogo de turnos (`zelo_ops_schedule_row_start_end`).

## [2.6.1] - 2026-05-28

### Alterado
- Escala admin: removida coluna Nome (nome derivado do voluntário selecionado).
- Horários início/fim só no catálogo de turnos; na escala são exibição; API/PWA recebem horários enriquecidos do turno.

## [2.6.0] - 2026-05-28

### Adicionado
- Catálogos operacionais em `zelo_volunteer_ops_data.catalogs`: turnos (código + horários), locais, idiomas, voluntários roster (nome + telefone).
- Abas admin: Turnos, Locais, Idiomas, Voluntários (CRUD).
- Escala: selects (dia, turno, local, idiomas múltiplos), vínculo unificado WordPress ou roster; `id` oculto.
- Campo `roster_volunteer_id` nas linhas da escala; migração automática de nomes sem `wp_user_id`.

### Alterado
- Validação ao salvar: exclusividade WP/roster; sem duplicar mesma pessoa no mesmo dia e turno.

## [2.5.3] - 2026-05-27

### Autenticação
- Login limpa cookies antigos antes de `wp_signon` e reforça `wp_set_auth_cookie` (evita 403 `rest_cookie_invalid_nonce` na PWA).
- PWA: `GET /auth/session` sem cabeçalho `X-WP-Nonce` (validação só por cookie).

## [2.5.2] - 2026-05-27

### Autenticação
- Novo endpoint `GET /zelo/v1/auth/session` para validar cookie WP e renovar nonce (PWA em `/zelo/`).
- Login reforça `wp_set_current_user` e cookies em HTTPS.

## [2.5.1] - 2026-05-27

### Segurança
- Removido filtro temporário `zelo_ops_voluntarios_public_read` — `/ops/voluntarios` exige autenticação novamente.

## [2.4.6] - 2026-03-12

### Correções (PWA)
- **Recuperação de Filtros**: Revertida a lógica de endereço para o padrão estável v54, garantindo que Bairro e Cidade voltem a aparecer corretamente.
- **Correção Crítica de Horário**: Corrigido bug no `isItemOpen` que falhava ao processar horários com colons (ex: 7:00), fazendo o filtro "Aberto agora" finalmente funcionar.
- **Sanitização Refinada**: Mantida a limpeza de números e lixo nos dropdowns, mas com fallback mais inteligente.

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
