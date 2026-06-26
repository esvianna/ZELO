# Issue #65 — SMS ao publicar novidades (Posts WP)

> **Issue:** [esvianna/ZELO#65](https://github.com/esvianna/ZELO/issues/65)
> **Status:** **In review** — plugin **2.25.0** / PWA **180**  
> **Relacionadas:** [#26](https://github.com/esvianna/ZELO/issues/26) blog/novidades, [#54](https://github.com/esvianna/ZELO/issues/54) SMS Comtele (ADR-040), [#8](https://github.com/esvianna/ZELO/issues/8) Web Push  
> **Última atualização:** 2026-06-25

---

## 1. Objetivo

Permitir que, ao publicar um **Post** marcado como novidade/notificação na PWA, o ZELO envie **SMS** (Comtele) aos voluntários elegíveis — em **paralelo** ao push e ao sino in-app, **sem substituir** esses canais.

Hoje: push broadcast só se `_zelo_in_app` + `_zelo_as_notification`; **SMS não existe** para novidades.

---

## 2. Contexto no código

| Peça | Ficheiro | Comportamento actual |
|------|----------|----------------------|
| Meta box novidades | `inc/zelo-news.php` | `_zelo_in_app`, `_zelo_as_notification`, carrossel |
| Push ao gravar post | `inc/zelo-web-push.php` → `zelo_push_maybe_news_notification` | Broadcast a todas as subscrições push |
| SMS ops | `inc/zelo-sms-comtele.php`, `inc/zelo-notify-sms-queue.php` | Cron ops; dedup `assignment_id\|window\|sms` |
| Audiência `/news` | ADR-023 | Só logados com `zelo_view_ops` |
| Orçamento SMS | ADR-040 | ~1.000 SMS/evento; alerta 80% |

---

## 3. Decisões de produto (proposta — aprovar antes de codificar)

| # | Pergunta | **Proposta** |
|---|----------|--------------|
| D1 | Disparar SMS em que condição? | Post `publish` + `_zelo_in_app` + **nova meta** `_zelo_sms_notify=1` (checkbox explícito no meta box). **Não** amarrar automaticamente a «Mostrar como notificação (sino)» — admin escolhe SMS à parte (custo). |
| D2 | Quem recebe? | Utilizadores WP com **`zelo_view_ops`** + **`zelo_phone`** válido (E.164 BR) — mesma audiência lógica de `/news`, não visitantes nem subscribers pendentes. |
| D3 | Um SMS por quê? | **1 SMS por utilizador por post** (dedup `post_id\|news_sms` em `user_meta` ou log dedicado). |
| D4 | Conteúdo | Título truncado + link curto PWA (`zelo_comtele_short_link()` + hash `#blog-post?id=N` se couber) — **≤140 chars**, PT (ADR-040). |
| D5 | Cascata | SMS **em paralelo** a push (se `_zelo_as_notification`) e sino; falha SMS não bloqueia publicação. |
| D6 | Orçamento | Antes do envio em massa: estimativa na UI («~N destinatários»); respeitar alerta 80% créditos; **não** enviar se Comtele desactivado. |
| D7 | Re-publicar / editar | Dedup por `post_id` + `post_modified_gmt` (como push) — evita SMS duplicado em autosave; **nova** modificação publicada pode re-disparar só se admin marcar de novo ou confirmar «Reenviar SMS» (fase 2; MVP: só na **primeira** publicação com checkbox). |
| D8 | LGPD | Actualizar texto cadastro/perfil: «…avisos operacionais **e novidades do evento** por SMS». Opt-in separado = fase 2 (ADR-040). |
| D9 | Confirmação admin | Checkbox + aviso de custo no meta box; opcional: modal «Enviar ~N SMS?» no save (decidir no Ready). |

**Alternativa descartada (MVP):** SMS automático sempre que «sino» está marcado — risco de esgotar créditos com posts frequentes.

---

## 4. Decisões técnicas (proposta)

| Tema | Escolha |
|------|---------|
| Hook | `save_post_post` priority 26 — `zelo_news_maybe_sms_notification( $post_id )` (após push 25) |
| Destinatários | `get_users( [ 'capability' => 'zelo_view_ops' ] )` + `zelo_comtele_user_phone()` |
| Envio | Lote via `zelo_comtele_send_sms( $phones[], $msg, $custom )` ou fila `zelo_notify_sms_queue` se >20 destinatários / falha API |
| Dedup | `custom` = `news|{post_id}|{modified_gmt}`; log em option `zelo_news_sms_log` ou reutilizar padrão fila |
| Meta nova | `_zelo_sms_notify` bool |
| Admin | Meta box «Zelo — App móvel»: checkbox «Enviar SMS aos voluntários» (disabled se Comtele off ou sem `_zelo_in_app`) |
| Config | Reutilizar Comtele Config (#54); sem chave nova |
| ADR | **ADR-043** em `DECISIONS.md` após aprovação |

### Ficheiros previstos

| Ficheiro | Alteração |
|----------|-----------|
| `inc/zelo-news.php` | Meta `_zelo_sms_notify`; save meta; UI checkbox + contagem estimada |
| `inc/zelo-news-sms.php` (novo) | Orquestração, dedup, mensagem, envio/fila |
| `inc/zelo-notify-sms-queue.php` | Helper genérico «enqueue bulk» se necessário |
| `inc/zelo-sms-comtele.php` | (mínimo) helper contagem destinatários news |
| `zelo-assistente.php` | require novo include |
| `TESTING.md` | Caso **S65a–S65c** |
| `DECISIONS.md` | ADR-043 |
| `SECURITY.md` | Nota volume SMS + permissão só editor/admin publica post |

**Fora do escopo MVP:** opt-in por utilizador; SMS a extras Pool B; e-mail em massa para novidades; PWA changes.

---

## 5. Riscos

| Risco | Mitigação |
|-------|-----------|
| Esgotar 1.000 SMS | Checkbox explícito; estimativa N; alerta 80%; log admin |
| SMS duplicado ao editar post | Dedup `post_id` + modified; MVP só first publish |
| Telefone em falta | Ignorar user; log «skipped N sem telefone» |
| Rota Comtele Marketing vs transacional | Confirmar rota no painel; documentar em ADR-043 |
| Latência save_post | Envio assíncrono (fila + cron) se N > limiar |

---

## 6. Critérios de aceite

1. Post com «Publicar na PWA» + «Enviar SMS» → voluntários com `view_ops` + telefone recebem 1 SMS com título + link.
2. Post **sem** checkbox SMS → zero SMS (push/sino inalterados se marcados).
3. Comtele desactivado → checkbox disabled; save post OK.
4. Dedup: republicar sem alterar modified não reenvia; editar e re-marcar (ou fase 2) documentado.
5. Admin vê no log Config contagem enviada / falhas.
6. `TESTING.md` S65a–S65c passam smoke.

---

## 7. Como testar (rascunho `TESTING.md`)

| ID | Caso | Quem | Passos |
|----|------|------|--------|
| S65a | SMS novidade activado | `manage_options` + Comtele on | Criar post, marcar PWA + SMS; publicar; 2+ voluntários com telefone recebem SMS; dedup OK |
| S65b | SMS desligado | editor | Post só PWA/sino → sem SMS |
| S65c | Sem telefone | voluntário sem `zelo_phone` | Não recebe SMS; outros sim |

---

## 8. Estimativa e versão

- **Size:** M (backend only, reutiliza #54)  
- **Plugin:** patch **2.25.x** (sem PWA)  
- **Próximo passo:** aprovar plano → mover #65 para **Ready** → implementar
