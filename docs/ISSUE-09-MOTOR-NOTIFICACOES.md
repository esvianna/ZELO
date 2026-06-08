# Issue #9 — Motor de notificações unificado + inbox servidor (C5)

> **Issue:** [esvianna/ZELO#9](https://github.com/esvianna/ZELO/issues/9)  
> **Status:** **Descartado** (ADR-028, 2026-06-04) — hub sino + cron e-mail + localStorage bastam; #16 descartada em conjunto.  
> **Relacionadas:** [#16](https://github.com/esvianna/ZELO/issues/16) inbox servidor (B10), [#26](https://github.com/esvianna/ZELO/issues/26) posts/notificações WP, [#2](https://github.com/esvianna/ZELO/issues/2) e-mail auth, [#8](https://github.com/esvianna/ZELO/issues/8) Web Push (**descartado**, ADR-026)  
> **Última atualização:** 2026-06-04

---

## 1. Objetivo

Completar um **motor de notificações unificado** (hoje parcial: hook + e-mail) com **inbox persistido no servidor**, alinhado ao **hub de avisos** (sino) da PWA.

Traduzindo: deixar de depender só de agregação no cliente e de e-mails soltos; ter eventos registados no backend, consumíveis pela PWA (e futuros canais), com estado «lido» sincronizado.

---

## 2. Pedido original (issue)

> Completar motor de notificações (hoje parcial: hook + e-mail) com inbox persistido no servidor, alinhado ao hub de avisos da PWA.

**Critérios de aceite (GitHub):**

- [ ] Modelo de dados e API REST definidos (leitura / marcação lida).
- [ ] Integração com eventos existentes (escala, check-in, etc.) documentada.
- [ ] PWA consome inbox sem regressão do MVP actual (sino build 78).

**Notas:** pode ser **épico**; quebrar em sub-issues após definição. Coordenar com **#2** (e-mail) e **#16** (inbox B10).

---

## 3. Contexto no código hoje

### 3.1 Hub PWA (sino) — **implementado (MVP)**

| Peça | Situação |
|------|----------|
| View `view-avisos` | Build **78+** (ADR-012) |
| Agregação | `buildAvisosFeed()` em `app-v5.js` — **100% cliente**, recomputa a cada render |
| «Lido» / badge | `localStorage` `zelo_avisos_read` (array de ids) — **sem servidor** |
| Badge | `updateNotificationsBadge()` — conta itens do feed não lidos localmente |

**Fontes actuais do feed** (ids estáveis):

| Fonte | Id exemplo | Quem vê |
|-------|------------|---------|
| Aviso evento (`home_notice`) | `event-notice` | Todos (activo) |
| Posts WP notificação (#26) | `post-{id}` | Logados |
| Vínculo roster pendente | `registration-pending` | Logados ops |
| Compromisso pendente | `commitment-{asgId}` | Titular / supervisor |
| Escala alterada | `schedule-changed-{asgId}` | Titular / supervisor |
| Check-in pendente | `checkin-{asgId}` | Titular |
| Check-out pendente | `checkout-{asgId}` | Titular |
| Turno em 24h | `shift-next-{asgId}` | Titular |
| Recusa (supervisor) | `decline-{asgId}` | Supervisão |
| Pedido swap pendente | `swap-{id}` | `manage_ops` / reallocate |

### 3.2 E-mail (cron) — **implementado, paralelo ao hub**

Ficheiro: `inc/zelo-volunteer-notifications.php`

- Cron horário: `zelo_volunteer_notify_tick`
- Dedup: `user_meta` `zelo_notify_log` (`assignment_id|window`, max 200 entradas)
- Janelas / eventos por e-mail:

| Window | Trigger |
|--------|---------|
| `schedule_changed` | Compromisso `pending` + `pending_reason: schedule_changed` |
| `commitment_due` | Pendente + deadline a ≤3 dias |
| `before_1day` | Turno aceite ~24h antes (config `presence.notify_1_day_before`) |
| `before_24h` | Turno aceite ~24h antes (config `notify_24h`) |
| `before_min` | Turno aceite N min antes (config presença) |
| `checkin_open` | Janela check-in aberta, ainda `pending` |
| `checkout_open` | Janela check-out aberta, `checked_in` |

**Não passa** pelo hook unificado — lógica directa + `wp_mail`.

### 3.3 Hook «motor» — **esqueleto apenas**

```php
function zelo_notification_dispatch( $event, $context = array() ) {
    do_action( 'zelo_notification_dispatch', $event, $context );
}
```

- Definido em `zelo-volunteer-commitments.php`
- **Único call site:** `commitment_declined_supervisor` (após e-mail a supervisores)
- **Nenhum** `add_action( 'zelo_notification_dispatch', ... )` no plugin — canal inbox/push não ligado

### 3.4 Outros canais e-mail (fora do «motor»)

| Fluxo | Ficheiro | Relação com #9 |
|-------|----------|----------------|
| Registo / verificação e-mail | `zelo-volunteer-registration.php` | **#2** — transaccional auth, não inbox |
| Recusa → supervisores | `zelo-volunteer-commitments.php` | E-mail + dispatch hook |
| Posts WP (#26) | `zelo-news.php` | Só REST `/news`; sem e-mail automático |

### 3.5 Web Push

- **Descartado** (ADR-026, #8). Stub `POST /ops/push/subscribe` (501). SW tem handlers `push` / `notificationclick` sem evolução.

---

## 4. Sobreposição #9 vs #16

| | **#9 C5** | **#16 B10** |
|---|-----------|-------------|
| Foco | Motor **unificado** + eventos + inbox | Persistência servidor + multi-dispositivo |
| Critérios | Modelo + API + integração eventos | API lido + paginação + offline |
| Nota GitHub | Épico; sub-issues | «Definir junto com C5» |

**Recomendação:** tratar como **um épico** com duas fases ou **fechar #16** como sub-tarefa de #9 ao ir para Ready — evitar dois modelos de inbox.

---

## 5. Gap vs critérios de aceite

| Critério | Hoje | Gap |
|----------|------|-----|
| Modelo + API REST | Inexistente | Tabela ou `user_meta` estruturado; `GET /notifications`, `PATCH .../read` |
| Integração eventos documentada | Lógica espalhada (cron, JS, commitments) | Mapa evento → canal; refactor cron para `zelo_notification_dispatch` |
| PWA sem regressão sino | Feed cliente funciona | Migrar fonte para API **ou** híbrido (API + fallback cliente durante transição) |

---

## 6. Decisões de produto (abertas)

| # | Pergunta | Opções | Notas |
|---|----------|--------|-------|
| **D1** | #9 absorve #16? | **A)** Sim, épico único · **B)** #9 = motor/eventos; #16 = só «lido» posts | ROADMAP já sugere A |
| **D2** | O hub deixa de agregar no cliente? | **A)** API única · **B)** Híbrido (ops dinâmicos no cliente + histórico servidor) · **C)** Manter cliente (só sync «lido») | B reduz risco; check-in muda em tempo real |
| **D3** | Quais eventos gravam inbox? | Mínimo: posts #26, schedule_changed, swap, decline · Opcional: lembretes turno | Lembretes duplicam feed actual `shift-next-*` |
| **D4** | E-mail continua? | **A)** Sim, como canal do motor · **B)** Só inbox PWA | Cron actual é valor operacional (#2 separado) |
| **D5** | Persistência | **A)** Tabela custom `wp_zelo_notifications` · **B)** CPT · **C)** JSON em `user_meta` | A escala melhor; C mais rápido MVP |
| **D6** | Retenção | 30 / 90 dias / até fim evento | GDPR + tamanho DB |
| **D7** | Offline PWA | Cache última lista + merge read local · badge stale | Alinhado ADR-025 |
| **D8** | Visitante anónimo | Só `home_notice` no cliente; inbox API só logados | Consistente com #26 |

---

## 7. Modelo de dados proposto (MVP)

### 7.1 Registo de notificação (servidor)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | string | `ntf_{uuid}` ou int auto |
| `user_id` | int | Destinatário |
| `event` | string | `schedule_changed`, `wp_post`, `swap_pending`, … |
| `source_id` | string | `post-42`, `asg_xxx`, `swap_yyy` — dedup |
| `title` | string | Snapshot para listagem |
| `summary` | string | Texto curto |
| `payload` | json | `action`, `post_id`, `assignment_id`, … |
| `read_at` | datetime nullable | Marcação lida |
| `created_at` | datetime | |

Índice único sugerido: `(user_id, event, source_id)` — evita duplicados ao republicar post ou reenviar cron.

### 7.2 API REST proposta

| Método | Rota | Auth | Notas |
|--------|------|------|-------|
| `GET` | `/zelo/v1/notifications` | Login | Query: `page`, `per_page`, `unread_only` |
| `PATCH` | `/zelo/v1/notifications/{id}/read` | Titular | Idempotente |
| `POST` | `/zelo/v1/notifications/read-all` | Titular | Opcional MVP |
| `GET` | `/zelo/v1/notifications/unread-count` | Login | Badge rápido (opcional) |

**Não** expor criação via REST pública — só `zelo_notification_dispatch()` interno + hooks admin (publicar post).

### 7.3 Pipeline unificado (alvo)

```
Evento (escala, post, swap, …)
    → zelo_notification_dispatch( $event, $context )
        → zelo_notification_persist_inbox( … )     // novo
        → zelo_notification_send_email( … )       // refactor cron / commitments
        → do_action extensível (SMS, etc.)
```

PWA: `loadNotifications()` no login; `buildAvisosFeed()` passa a **mesclar** API + itens dinâmicos ops (check-in aberto) se D2=B.

---

## 8. Mapa de integração eventos (existentes → motor)

| Evento | Hoje PWA feed | Hoje e-mail | Migrar dispatch |
|--------|---------------|-------------|-----------------|
| `home_notice` | Cliente `/evento` | — | Opcional (broadcast?) |
| Post WP `_zelo_as_notification` | Cliente `/news` | — | **Sim** — `save_post` |
| `schedule_changed` | Cliente ops | Cron | **Sim** |
| Compromisso pendente | Cliente ops | Cron `commitment_due` | Opcional inbox |
| Lembrete 24h / turno | Cliente `shift-next-*` | Cron `before_*` | D3 — provável só e-mail |
| Check-in/out aberto | Cliente ops | Cron | D3 — dinâmico no cliente |
| `commitment_declined` | Cliente supervisão | wp_mail + dispatch | **Sim** — completar persist |
| Swap pendente | Cliente ops | — | **Sim** |
| Link roster pendente | Cliente ops | — | Opcional |

---

## 9. Proposta técnica por fase

### Fase 1 — Motor mínimo (~2 sessões)

1. `inc/zelo-notifications.php` — persistência (user_meta ou tabela via dbDelta).
2. Listener `add_action( 'zelo_notification_dispatch', 'zelo_notification_handle_dispatch' )`.
3. Emitir inbox em: post publicado (#26), swap criado/aprovado, decline supervisor.
4. REST GET + PATCH read.
5. PWA: fetch inbox no login; merge em `buildAvisosFeed()`; «lido» chama API + localStorage fallback.

### Fase 2 — E-mail via motor (~1 sessão)

1. Refactor `zelo-volunteer-notifications.php` para chamar dispatch em vez de `wp_mail` directo.
2. Handler e-mail único com templates por `event`.
3. Manter dedup `zelo_notify_log` ou unificar com `source_id`.

### Fase 3 — Ops dinâmicos + offline (~1 sessão)

1. Decidir D2: o que fica só cliente vs servidor.
2. Cache offline lista notificações; estratégia badge stale (#16).
3. Documentar em `docs/OPS-NOTIFICATIONS.md` + `TESTING.md`.

**Total estimado:** 3–4 sessões (épico completo #9+#16).  
**MVP Fase 1 only:** ~2 sessões.

---

## 10. Riscos

| Risco | Mitigação |
|-------|-----------|
| Duplicar feed (API + cliente) | Regras claras D2; ids estáveis iguais aos actuais |
| Badge errado offline | `unread-count` stale + ops dinâmicos sempre locais |
| Explosão de registos (cron horário) | Não persistir lembretes repetíveis; só dedup e-mail |
| Scope creep com #2 | Auth e-mail fora; só notificações operacionais/de evento |
| Regressão #26 | Posts continuam em `/news`; inbox espelha `post-*` |

---

## 11. Cenários de decisão (como #8 / #10)

| Cenário | Quando | Acção |
|---------|--------|-------|
| **Implementar Fase 1** | Querem «lido» multi-dispositivo + histórico posts | Ready → código |
| **Implementar épico completo** | Evento grande, many supervisors | Fases 1–3 |
| **Descartar / adiar** | Hub + e-mail cron bastam (como #8/#10) | ADR won't fix; fechar ou congelar #16 |

### O que **já funciona** sem #9

- Sino com avisos ops + posts (#26) + aviso evento
- Badge local por dispositivo
- E-mails automáticos de turno, check-in, escala alterada
- Sem necessidade de push (#8 descartado)

### O que **não** se resolve sem #9

- «Lido» sincronizado telemóvel + desktop
- Histórico de notificações após item sair do feed dinâmico
- Um único ponto para auditar «o que foi enviado a quem»
- Extensão futura (SMS, etc.) além do hook vazio

---

## 12. Recomendação (para aprovação)

| Tema | Proposta |
|------|----------|
| **Prioridade** | Média — melhoria de produto, não bloqueia operação actual |
| **Épico** | Unificar **#9 + #16** num plano; fechar #16 como duplicata ou sub-issue |
| **MVP sugerido** | Fase 1: inbox servidor para **posts #26** + **swap/decline** + API read; ops dinâmicos (check-in) **permanecem no cliente** (D2=B) |
| **Manter** | Cron e-mail actual até Fase 2 |
| **Não fazer no MVP** | Push, SMS, reescrever todo `buildAvisosFeed` de uma vez |
| **Próximo passo** | Confirmar D1 (merge #16), D2 (híbrido vs API total) e se o valor justifica ~2 sessões — ou **descartar** se multi-dispositivo não for necessário |

---

## 13. Sub-issues sugeridas (se Ready)

1. **#9a** — Schema + `zelo_notification_dispatch` handler + REST GET/PATCH  
2. **#9b** — Emitters: `save_post` (#26), swap, decline  
3. **#9c** — PWA merge feed + sync «lido»  
4. **#9d** — Refactor cron e-mail → motor (Fase 2)  
5. **#9e** — Offline + testes `TESTING.md`  

*(Opcional: absorver #16 em #9c/#9e.)*

---

## 14. Como testar (rascunho — após implementação)

1. Publicar post com `_zelo_as_notification` → inbox API + sino; segundo dispositivo vê mesma notificação.
2. Marcar lido no dispositivo A → badge zero no B após refresh.
3. Criar pedido swap → supervisor vê inbox + feed; voluntário não.
4. Regressão: cron e-mail `schedule_changed` continua (Fase 2).
5. Offline: lista cacheada; badge coerente com nota stale.
6. Anónimo: sem `/notifications`; `home_notice` intacto.

---

## 15. Comparação com issues recentes

| | **#8 Push** | **#10 Cobertura** | **#9 Motor** |
|---|-------------|-------------------|--------------|
| Valor actual | In-app #26 substitui | Filtros escala substituem | Hub + e-mail **já operam** |
| Gap principal | Push nativo | Matriz posto×idioma | Sync servidor + histórico |
| Esforço | Alto | Médio | Médio–alto (épico) |
| Dependência | Descartado | Descartado | #26 done; #16 overlap |
