# Changelog — Zelo Assistente

Todas as alterações relevantes ao plugin backend do Zelo são documentadas aqui.

## [2.24.1] - 2026-06-24

### Alterado
- **Export PDF mapa evento (#63):** pinos numerados na ordem de cadastro; legenda com «N — Nome» de cada local; PWA/admin inalterados.

## [2.24.0] - 2026-06-24

### Adicionado
- **Export PDF mapa evento (#62):** botão na aba Mapa evento; composite GD alta resolução com pinos e legenda; download via `admin-post`.

## [2.23.3] - 2026-06-24

### Corrigido
- **Admin mapa evento (#61):** pinos não apareciam — erro de sintaxe JS (`DOMContentLoaded` sem `});`) impedia `zeloMapRefreshPins()`.

## [2.23.2] - 2026-06-24

### Alterado
- **Mapa evento pinos por pavimento (#61):** destinos com cor por valor da coluna Pav.; legenda dinâmica no admin; balcões mantêm azul/teal.

## [2.23.1] - 2026-06-24

### Alterado
- **Mapa evento pinos (#61):** admin — pinos maiores no modo Posicionar (2×), highlight na linha e contorno no canvas; pinos base ligeiramente maiores na visualização.

## [2.23.0] - 2026-06-23

### Adicionado / alterado
- **Voluntários extras fase 2 (#60):** encaminhamento em lote (`extra_ids[]`); SMS resumo ao responsável após lote; estados manuais `encaminado`/`atendido` (`close_forward`, `mark_served`); comparecimento só em pedidos atendidos; PDF por pedido `GET /ops/dept-volunteer-requests/{id}/pdf`; qty pedida deixa de bloquear encaminhamentos.

## [2.22.0] - 2026-06-23

### Adicionado
- **Voluntários extras (#60):** Pool B separado (`zelo_extra_volunteers`, pedidos, encaminhamentos); REST `/ops/extra-volunteers*`, `/ops/dept-volunteer-requests*`, `/ops/dept-volunteer-assignments*`, dashboard `/ops/extra-volunteers-ops`; export CSV/PDF; SMS Comtele ao encaminhar (telefone obrigatório no cadastro).

## [2.21.5] - 2026-06-22

### Adicionado
- **Lembretes manuais e aviso de recusas (#59):** `POST /ops/shifts/remind-pending` (cooldown 24h por designação) e `POST /ops/shifts/notify-declines` (cooldown 6h por responsável/turno); histórico `commitment_reminder_sent` e `shift_declines_notified`; permissão gestor ou supervisor do turno.

## [2.21.4] - 2026-06-22

### Alterado
- **Selectors voluntário (#57):** swap (PWA + admin), add/edit linha e editor de turno listam primeiro quem não está em nenhuma linha da escala (`<optgroup>`); lista única A–Z quando todos alocados ou só um grupo.

## [2.21.3] - 2026-06-22

### Alterado
- **Swap (#56):** selector de substituto lista todos os utilizadores WP cadastrados (`zelo_voluntario`, homem-chave, supervisor, admin) — não depende do catálogo roster do admin.

## [2.21.1] - 2026-06-22

### Corrigido
- **Swap (#56):** lista de substitutos vazia quando roster tinha conta WP só na escala (sem `linked_wp_user_id` no catálogo); ortografia PT «Selecione».

## [2.21.0] - 2026-06-22

### Adicionado
- **Swap notificações (#56, ADR-041):** recusa com `rejection_reason` obrigatório e histórico `swap_rejected`; aprovação exige `replacement_user_id` do roster com conta WP; `zelo_notify_deliver_timely` + SMS imediato; `swap_roster_candidates` no payload ops; admin swaps com selector e textarea.

## [2.20.1] - 2026-06-22

### Corrigido
- **Admin Config (#54):** salvar aba Config (SMS/push) falhava silenciosamente com escala grande — JS desactiva campos das outras abas no POST; aba Onboarding fora do form principal; aviso se `max_input_vars` truncar envio.

## [2.20.0] - 2026-06-22

### Adicionado
- **SMS Comtele (#54, ADR-040):** `zelo-sms-comtele.php` + `zelo-notify-sms-queue.php`; envio paralelo a push/e-mail no cron ops; dedup `zelo_notify_sms_log`; fila SMS; admin Config (chave API, rota, teste, contadores, log); alerta ~80% orçamento.

## [2.19.1] - 2026-06-20

### Adicionado
- **Registro delegados (#51):** `PATCH`/`DELETE /ops/delegate-support-reports/{id}` para gestores; metadados `updated_at` / `updated_by_name`.

## [2.19.0] - 2026-06-20

### Adicionado
- **Registro apoio delegados (#51, ADR-039):** `POST/GET /ops/delegate-support-reports`; export CSV/PDF; option `zelo_delegate_support_reports`; rate limit 10/h por utilizador; permissões `zelo_view_ops` (envio) e `zelo_manage_ops` (lista/export).

## [2.18.0] - 2026-06-20

### Adicionado
- **E-mails escala (#44, ADR-037):** digest por voluntário+dia (24h, escala alterada, compromisso); push-first para check-in/out e minutos antes; fila + throttle 250/h e 800/d; contadores e alerta admin ~80%; painel resumo na aba Config.

## [2.17.1] - 2026-06-20

### Corrigido
- **Admin Config:** checkboxes «Lembrete 24h» / «Presença 1 dia antes» (e push) voltavam a «Ativo» após Salvar — `call_user_func_array( …, array( &$data ) )` para persistência PHP 8; hidden `value=0` + parse explícito; `zelo_ops_normalize_settings` preserva `false`; redirect PRG após save por aba; Enter na aba activa usa o Salvar correcto (não «Limpar duplicatas»).

## [2.17.0] - 2026-06-18

### Adicionado
- **Reset push (#42):** `zelo_push_delete_all_subscriptions()`; regenerar VAPID trunca subscriptions após gravar chaves; botão admin «Limpar subscriptions push»; `vapidPublicKeyFingerprint` em REST; contagem de dispositivos na aba Config; histórico `push_vapid_rotated` / `push_subscriptions_cleared`.

### Corrigido
- Cabeçalho do plugin (`Version:`) alinhado com `ZELO_VERSION` (2.17.0).

## [2.16.0] - 2026-06-18

### Adicionado
- **Aprovação de voluntários (#41):** registo PWA cria `subscriber`; após verificar e-mail entra na fila `pending`; REST `GET/POST /ops/volunteer-approvals` (só `manage_options`); notificação e-mail + push aos administradores; meta auditoria; migração legados → `approved`.

### Alterado
- **`GET /news`** e **`GET /indoor-map`:** exigem `zelo_view_ops` (antes login/público).

## [2.15.2] - 2026-06-18

### Corrigido
- **Admin ops (#39):** `call_user_func()` em PHP 8 não passa `&$data` por referência — save por aba não persistia alterações; invocação directa `$handler( $data )`.

## [2.15.1] - 2026-06-18

### Corrigido
- **Admin ops (#39):** `SyntaxError` em `zeloOpsPrepareSaveForm` (chaves `tab-*` sem aspas) impedia `zeloOpsTab` — abas só mudavam o hash na URL; sync hash no load + null-check no painel.

## [2.15.0] - 2026-06-18

### Alterado
- **Admin ops (#39):** «Salvar abas» substituído por **Salvar** em cada aba (escala, turnos, locais, idiomas, voluntários, governança, config, mapa); POST isolado por aba; notice na mesma página (sem redirect PRG).

## [2.14.8] - 2026-06-18

### Corrigido
- **Admin ops (#38):** «Este link expirou» ao «Salvar abas» — dois `wp_nonce_field` no mesmo form usavam `_wpnonce`; o POST enviava só o nonce da dedupe; nomes únicos por acção + `check_admin_referer( …, false )`.

## [2.14.7] - 2026-06-18

### Corrigido
- **Admin ops (#38):** hotfix «Salvar abas» — `input hidden` `zelo_ops_tabs_save` restaurado; botão «A guardar…» só desactiva após `setTimeout(0)`; dedupe desactiva o hidden para não gravar abas; flash do notice em `user_meta` (mais fiável que transient).

## [2.14.6] - 2026-06-18

### Corrigido
- **Admin ops (#38):** formulário «Limpar duplicatas» aninhado dentro de «Salvar abas» invalidava o HTML — o browser fechava o `<form>` principal e o botão Salvar deixava de enviar dados; dedupe passou a ser botão `submit` no mesmo form; aviso admin via flash + redirect (PRG).

## [2.14.5] - 2026-06-18

### Corrigido
- **Admin ops (#38):** «Salvar abas» bloqueado por validação HTML5 em abas ocultas (`novalidate`, sem `required` client-side); feedback «A guardar…» no clique; linhas vazias de catálogo removidas antes do POST.

## [2.14.4] - 2026-06-18

### Corrigido
- **Admin ops (#38):** «Salvar abas» grava catálogos/config mesmo com escala inválida; botão VAPID não aborta save; exclusão de voluntário persiste; designações desvinculadas; reindex de checkboxes ao remover linhas.

## [2.14.3] - 2026-06-18

### Corrigido
- **Escala (#37):** «Limpar duplicatas» sem duplicatas → aviso de sucesso, não erro.

## [2.14.2] - 2026-06-18

### Corrigido
- **Web Push (#36):** `zelo_push_normalize_endpoint()` — unsubscribe usava `sanitize_text_field` em vez de `esc_url_raw`, gerando hash diferente do subscribe.

## [2.14.1] - 2026-06-18

### Adicionado
- **Escala (#37):** botão «Limpar duplicatas» no admin; `zelo_ops_schedule_dedup_key`, `zelo_dedupe_schedule_rows`, `zelo_ops_dedupe_volunteer_ops_schedule`; aviso quando há duplicatas; mantém linha com compromisso/check-in.

## [2.14.0] - 2026-06-18

### Adicionado
- **Web Push (#36):** `inc/zelo-web-push.php` — VAPID configurável; tabela `wp_zelo_push_subscriptions`; `GET /push/vapid-public`, `POST|DELETE /ops/push/subscribe`, `GET /ops/push/status`, `POST /ops/push/test`; push em posts Novidades (`_zelo_as_notification`), escala alterada (`zelo_assignment_schedule_changed`), lembretes check-in/check-out no cron; dependência `minishlink/web-push` (Composer).

## [2.13.8] - 2026-06-12

### Adicionado
- **Informações do evento (#34):** toggles `trans_section_active`, `wifi_section_active`, `cred_section_active`; contacto imprensa/autoridades (`press_contact_*`) exposto em `GET /evento` → `info_uteis.press_contact`.

## [2.13.7] - 2026-06-12

### Alterado
- **Remoção de linha da escala (#31):** `zelo_ops_cleanup_orphan_assignment_data` rejeita pedidos de substituição `pending` quando a designação é removida.

## [2.13.6] - 2026-06-04

### Corrigido
- Avatar perfil: fallback `medium` / URL completa se thumbnail em falta (`zelo_get_user_avatar_url`).

## [2.13.5] - 2026-06-04

### Adicionado
- **Rate limit REST (#22):** `inc/rate-limit.php` — helper transients; `POST /auth/login` 30/15 min/IP + 10/15 min/username (sucesso e falha); resposta 429 `zelo_rate_limit`; filtro `zelo_rate_limit_enabled`.

### Alterado
- Cadastro: `zelo_registration_rate_limit_ok()` usa helper unificado (comportamento 8/h/IP inalterado).

## [2.13.4] - 2026-06-04

### Adicionado
- **Emergência (#17):** `inc/emergency-services.php` — 3 slots (Polícia/SAMU/Bombeiros) com número, rótulo e «quando ligar» em PT/EN/ES; checkbox «Mostrar telefone interno do evento»; API `emergency_services[]`.

### Alterado
- Admin: secção «Emergência pública (PWA)» substitui lista livre de telefones; migração automática de `phones[]` legado.

## [2.13.3] - 2026-06-04

### Adicionado
- **Carrossel home (#15):** meta `_zelo_carousel` («Destaque no carrossel da home»); checkbox no meta box «Zelo — App móvel»; `GET /news?carousel_only=1` (máx. 8, exige imagem destacada); cache transiente inclui flag `carousel_only`.

## [2.13.2] - 2026-06-04

### Corrigido
- **Novidades:** `html_entity_decode` + normalização de travessões (–, &#8211;, etc.) → hífen `-` na API.

## [2.13.1] - 2026-06-04

### Corrigido
- **Novidades API:** título/resumo com entidades HTML (`&#8211;` → travessão UTF-8).
- **Novidades cache:** invalidação de transients `zelo_news_list_v1_*` ao gravar post.

## [2.13.0] - 2026-06-04

### Adicionado
- **Novidades / blog (ZELO#26):** meta box «Zelo — App móvel» em Posts; `GET /zelo/v1/news` e `/news/{id}` (sessão WP); cache transiente 10 min.

## [2.12.3] - 2026-06-04

### Adicionado
- **Mapa evento:** pinos Balcão 1 (azul) vs Balcão 2 (teal) com número + legenda no admin e PWA (`booth_slot`).

## [2.12.2] - 2026-06-04

### Corrigido
- **Operação Voluntários:** «Salvar abas» deixava tela em branco (redirect HTTP após output no admin); aba activa persiste via POST + `user_meta`.

## [2.12.1] - 2026-06-04

### Corrigido
- **Mapa evento admin:** direções por destino passam a gravar correctamente (campos indexados pelo `id` do local; balcões já não desalinhavam o POST).
- **Operação Voluntários:** «Salvar abas» mantém a aba activa (`ops_tab` na URL + redirect PRG).

## [2.12.0] - 2026-06-04

### Adicionado
- **Mapa do evento (ZELO#28):** aba «Mapa evento» em Operação Voluntários — upload diagrama, CRUD `places[]` (balcão, departamento, serviço, extra), editor de pinos, direções Balcão 1/2 × pt/en/es no formulário de cada destino.
- `inc/indoor-map.php` — normalização, rotas, payload público filtrado (dept. 8–35 ocultos).
- `GET /indoor-map` — resposta com `places`, `routes`, `volunteer_notice`.

## [2.11.9] - 2026-06-04

### Adicionado
- `PATCH /auth/profile` — nome, telefone, e-mail (re-verificação), senha, idiomas (ZELO#25).
- `POST /auth/profile/avatar` — upload JPEG/PNG/WebP (máx. 2 MB); meta `zelo_avatar_id`.

## [2.11.8] - 2026-06-04

### Corrigido
- **Supervisão por governança:** `zelo_user_can_supervise_assignment` deixa de conceder acesso global a qualquer `zelo_reallocate_volunteer`; homem-chave actua só nos turnos da governança (alinhado a ADR-018 e TESTING §4.5).
- **Swaps:** `GET/PATCH /ops/swap-requests` filtrados por turno supervisionado; gestores (`zelo_manage_ops`) mantêm acesso global.

### Documentação
- Matriz REST ops/swaps: `docs/OPS-PERMISSIONS.md` (ZELO#13).

## [2.11.7] - 2026-06-04

### Corrigido
- Export PDF: erro PHP 8.2+ `Cannot access protected property FPDF::$lMargin` na governança compacta.
- Rate limit de export só após sucesso (falha 500 não bloqueia 60 s).

## [2.11.6] - 2026-06-04

### Alterado
- Export PDF: governança do dia em **duas linhas horizontais** (grupos + homens-chave); **nova página** por dia (sexta/sábado/domingo) para facilitar leitura.

## [2.11.5] - 2026-06-04

### Adicionado
- Export PDF (`/ops/export`): layout **dia → turno → faixa horária → voluntários**, linha **Responsável** (homem-chave) no cabeçalho do turno; tabela por faixa com Voluntário / Idiomas / Status.

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
