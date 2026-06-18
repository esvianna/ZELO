# Matriz de permissões — REST `/ops/*` e swaps (ZELO)

Documento de referência para auditoria de segurança (ROADMAP A2, [ZELO#13](https://github.com/esvianna/ZELO/issues/13)).  
**Plugin:** 2.14.0+ · Namespace: `zelo/v1`

## Roles e capabilities

| Role WP | Capabilities Zelo relevantes |
|---------|------------------------------|
| `zelo_voluntario` | `zelo_view_ops`, `zelo_checkin_ops` |
| `zelo_homem_chave` | + `zelo_reallocate_volunteer`, `zelo_edit_schedule` |
| `zelo_supervisor_grupo` | + `zelo_manage_ops` |
| `zelo_supervisor_app` | + `zelo_manage_roles` |
| `administrator` | Todas (via `add_cap` na ativação) |

**Supervisão por turno (governança):** homem-chave cadastrado em `governance[day].keymen_user_ids[shift]`, mais supervisores de grupo A/B e app (`zelo_resolve_shift_supervisor_user_ids`). Gestores (`zelo_manage_ops`) ignoram escopo.

**Não activar em produção:** filtro `zelo_ops_voluntarios_public_read` (expõe escala sem login).

---

## Matriz endpoint × auth

Legenda: **P** = público · **L** = login · **V** = `zelo_view_ops` · **C** = `zelo_checkin_ops` · **R** = `zelo_reallocate_volunteer` (REST gate) · **E** = escopo governança no handler · **M** = `zelo_manage_ops` · **S** = `zelo_edit_schedule` + escopo · **A** = `manage_options` (admin WP)

| Método | Rota | `permission_callback` | Handler (2ª linha) | Voluntário | Homem-chave (seu turno) | Homem-chave (outro turno) | Supervisor / gestor |
|--------|------|----------------------|--------------------|------------|-------------------------|---------------------------|---------------------|
| GET | `/ops/languages` | Público | — | OK | OK | OK | OK |
| GET | `/ops/voluntarios` | L + V (ou filtro público) | Payload filtrado por role | OK escala equipa | OK | OK | OK + history |
| GET | `/ops/voluntarios?mine=1` | idem | Só linhas próprias | OK | OK | OK | OK |
| GET | `/ops/export` | L + M ou admin | Rate limit pós-sucesso | **403** | **403** | **403** | OK |
| POST | `/ops/checkin` | L + C | Titular ou **E** `on_behalf` | Própria linha | **E** | **403** | **E** / M |
| POST | `/ops/checkout` | L + C | idem check-in | idem | idem | **403** | idem |
| POST | `/ops/reallocate` | L + R | **E** obrigatório | **403** | OK | **403** | OK |
| POST | `/ops/schedule` | L + V + S | **E** dia+turno | **403** | OK | **403** | OK |
| POST | `/ops/assignments/{id}/commit` | L + C | Titular ou **E** `on_behalf` | Própria | **E** | **403** | **E** / M |
| GET | `/ops/swap-requests` | L + (M ou R) | Lista filtrada por **E** | **403** | Pedidos do turno | **403** (lista vazia) | Todos |
| POST | `/ops/swap-requests` | L + C | Só titular da linha | Própria | Própria | Própria | Própria |
| PATCH | `/ops/swap-requests/{id}` | L + (M ou R) | **E** no pedido | **403** | OK | **403** | OK |
| GET | `/ops/onboarding` | L + A | Admin WP | **403** | **403** | **403** | **403** |
| POST | `/ops/push/subscribe` | L | Persiste subscription | OK | OK | OK | OK |
| DELETE | `/ops/push/subscribe` | L | Remove subscription | OK | OK | OK | OK |
| GET | `/ops/push/status` | L | Estado push do utilizador | OK | OK | OK | OK |
| GET | `/push/vapid-public` | L | Chave pública VAPID | OK | OK | OK | OK |
| POST | `/ops/push/test` | L + A | Envio de teste | **403** | **403** | **403** | **403** |

Rotas públicas relacionadas (não `/ops/*` mas dados ops): `GET /indoor-map` (mapa indoor a partir de options).

---

## Validações IDOR (handler)

| Ação | Função |
|------|--------|
| Check-in/out | `zelo_validate_presence_action` — titular ou supervisor **E** |
| Compromisso | `zelo_commitment_can_act` |
| Realocação | `zelo_user_can_supervise_assignment` |
| Guardar escala | `zelo_user_can_edit_schedule_day_shift` |
| Resolver swap | `zelo_user_can_resolve_swap_request` (2.11.8+) |
| Supervisão turno | `zelo_user_can_supervise_assignment` — só gestor global ou IDs da governança (2.11.8+) |

---

## Smoke manual (401/403)

Ver `TESTING.md` §14. Resumo:

1. Voluntário → `POST /ops/schedule` → **403**
2. Homem-chave turno A1 → `POST /ops/reallocate` linha turno B1 → **403**
3. Homem-chave → `PATCH /ops/swap-requests/{id}` de outro turno → **403**
4. Sem login → `GET /ops/voluntarios` → **401** (filtro público desligado)
5. Voluntário → `GET /ops/export` → **403**

Ferramentas: DevTools ou `curl` com cookie WP + header `X-WP-Nonce`.

---

## Histórico

| Versão | Alteração |
|--------|-----------|
| 2.11.8 | Supervisão por governança (remove bypass `zelo_reallocate_volunteer` global); swaps filtrados por turno |
| ≤2.11.7 | Homem-chave com cap `reallocate` podia supervisionar qualquer linha (lacuna corrigida) |
