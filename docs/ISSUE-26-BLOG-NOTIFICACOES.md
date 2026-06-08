# Issue #26 — Posts WP como notificações + blog para voluntários

> **Issue:** [esvianna/ZELO#26](https://github.com/esvianna/ZELO/issues/26)  
> **Status:** **In review** — plugin 2.13.0 + PWA 116 (2026-06-04)  
> **Relacionadas:** [#9](https://github.com/esvianna/ZELO/issues/9) motor notificações, [#16](https://github.com/esvianna/ZELO/issues/16) inbox servidor, [#8](https://github.com/esvianna/ZELO/issues/8) Web Push, [#15](https://github.com/esvianna/ZELO/issues/15) carrossel home  
> **Última atualização:** 2026-06-04

---

## 1. Objetivo

Permitir que a **equipe do evento** publique novidades no **WordPress** (Posts nativos) e que:

1. Posts marcados como **notificação** aparecem no **hub de avisos** (sino) da PWA, incrementando o badge — **apenas para utilizadores logados** (cadastro WP na PWA).
2. Utilizadores logados acedem ao **Blog / Novidades** por **card na home** e item no **menu hambúrguer** do header.
3. Página de novidades com layout mobile-first (cards, imagem destacada, data, corpo em **português**).

**Fora do escopo inicial (MVP #26):** Web Push em tempo real (#8), inbox com «lido» no servidor (#16), carrossel na home (#15), conteúdo multilíngue (posts só PT).

---

## 2. Pedido original (issue)

> Permitir que ao criar um post no backend ele seja exibido como uma notificação para todos os usuários.  
> Criar botão para os voluntários (apenas cadastrados) para Blog.  
> Criar página com layout atrativo para exibir todas as novidades/blog e notificações.

---

## 3. Contexto no código hoje

| Peça | Situação |
|------|----------|
| Hub avisos (`view-avisos`) | MVP build **78** — agrega avisos **no cliente** (`buildAvisosFeed()` em `app-v5.js`) |
| «Lido» / badge | `localStorage` `zelo_avisos_read` (array de ids) — **sem servidor** (ADR-012) |
| Aviso de evento | Campo único `home_notice` em `zelo_event_data` via `GET /evento` — não escala para múltiplas novidades |
| Avisos pessoais (ops) | Derivados de `/ops/voluntarios` — compromisso, check-in, swaps, etc. |
| Posts WordPress | **Não** expostos à PWA hoje; só CPT `zelo_local` para locais externos |
| Web Push | SW preparado (`push` / `notificationclick`); subscribe API stub **501** (#8) |
| Bottom nav | 5 itens fixos (Início · Mapa · S.O.S · Info · Perfil/Operação) — **sem** slot para Blog |
| Voluntário | `canViewOps()` → `auth.user.caps.view_ops` (role/capability no login) |

**Implicação:** #26 adiciona **fonte externa** ao feed de avisos sem substituir o motor ops; persistência «lida» continua local no MVP, alinhado a #16 futura.

---

## 4. Decisões de produto (fechadas — 2026-06-04)

| # | Pergunta | **Decisão** |
|---|----------|-------------|
| D1 | Onde publicar no WP? | **Posts nativos** + meta box (`_zelo_in_app`) |
| D2 | Quem lê blog e notificações de post? | **Apenas logados** (`auth.user` / cookie WP) — visitante anónimo não vê posts no sino nem acede ao blog |
| D3 | Idioma do conteúdo | **Só PT** — admin publica em português; rótulos da UI continuam via `i18n.js` |
| D4 | Atalho Blog | **Card na home** + **item no menu hambúrguer** (header); link «Ver todas» no hub avisos |
| D5 | HTML no corpo | Lista = excerpt; detalhe = HTML sanitizado (`wp_kses_post`) |
| D6 | `home_notice` vs posts | **Manter ambos** — alerta crítico único vs novidades recorrentes |
| D7 | Bottom nav | **Sem** 6.º item (ADR-012) |

> **Nota:** O pedido original citava «notificação para todos»; alinhado ao evento, o canal de novidades/posts fica **restrito a contas logadas** (voluntários cadastrados na PWA).

---

## 4.1 Decisões técnicas derivadas

| Tema | Escolha |
|------|---------|
| API `/news` | **Auth obrigatória** — `permission_callback` exige sessão WP (401 sem login) |
| Meta `_zelo_audience` | **Não usar no MVP** — audiência implícita = logados |
| Offline | Cache localStorage da última lista **por utilizador** (chave inclui user id ou só fetch pós-login) |

## 5. Modelo de dados proposto

### 5.1 Meta box no editor de Posts (plugin)

Campos sugeridos (post meta):

| Meta key | Tipo | Descrição |
|----------|------|-----------|
| `_zelo_in_app` | bool | Exibir na PWA (utilizadores logados) |
| `_zelo_as_notification` | bool | Entra no hub avisos + badge (requer `_zelo_in_app`) |
| `_zelo_notification_priority` | `normal` \| `important` | Ícone / destaque no feed (opcional MVP) |

**Regras:**

- Post `publish` + `_zelo_in_app` → listado em `GET /news` (**sessão WP obrigatória**).
- `_zelo_as_notification` → id estável `post-{ID}` no feed (`buildAvisosFeed`) — **só se `auth.user`**.
- Conteúdo admin em **português**; sem campos en/es no MVP.

### 5.2 Payload REST (lista)

```json
{
  "items": [
    {
      "id": 42,
      "slug": "horario-shuttle-atualizado",
      "title": "Horário do shuttle atualizado",
      "excerpt": "Texto curto…",
      "featured_image": "https://…/thumb.jpg",
      "published_at": "2026-06-04T14:30:00-03:00",
      "as_notification": true,
      "priority": "normal"
    }
  ],
  "page": 1,
  "per_page": 20,
  "total": 3
}
```

### 5.3 Payload REST (detalhe)

```json
{
  "id": 42,
  "title": "…",
  "content_html": "<p>…</p>",
  "featured_image": "…",
  "published_at": "…",
  "author_name": "Equipe Informações",
  "as_notification": true
}
```

---

## 6. API REST (plugin)

| Método | Rota | Auth | Notas |
|--------|------|------|-------|
| `GET` | `/zelo/v1/news` | **Sessão WP** | Query: `page`, `per_page`, `notifications_only=1`. **401** sem login |
| `GET` | `/zelo/v1/news/{id}` | **Sessão WP** | 404 se não `_zelo_in_app` ou não publicado |

**Implementação sugerida:** `inc/zelo-news-api.php` — `WP_Query` em `post_type=post`, `post_status=publish`, meta_query `_zelo_in_app=1`.

**Cache:** `set_transient` 5–15 min (invalidar em `save_post`); PWA espelha padrão `evento` (`API.cache.news` + snapshot localStorage).

**Segurança:** `permission_callback` explícito; conteúdo com `wp_kses_post`; sem PII; rate limit leve opcional (#22).

---

## 7. PWA — alterações

### 7.1 API client (`api-v5.js`)

- `fetchNews({ page, notificationsOnly })`
- `fetchNewsItem(id)`
- Cache + snapshot `zelo_news` (offline: última lista conhecida); detalhe por post — ADR-025 / PWA 123+ (`zelo_news_item_v1_*`)

### 7.2 Hub avisos (`buildAvisosFeed`)

Inserir **após** `event-notice`, **antes** bloco ops — **apenas se `this.auth.user`** e `this.data.news` carregado:

```javascript
// ids: post-42
{
  id: `post-${post.id}`,
  category: 'event',           // ou 'news'
  icon: post.priority === 'important' ? '📣' : '📰',
  title: post.title,
  summary: post.excerpt,
  time: formatRelative(post.published_at),
  action: 'news',
  postId: post.id
}
```

- `handleAvisoClick`: `action === 'news'` → `router.navigate('blog-post', { id })` + `markAvisoRead`.
- Novo filtro chip **Novidades** (opcional) ou incluir em **Evento**.

### 7.3 Views novas

| View | Rota hash | Quem acede |
|------|-----------|------------|
| `blog` | `#blog` | **Logado** — lista paginada; anónimo → redirect `login` |
| `blog-post` | `#blog-post?id=` | **Logado** |

**Ficheiros:** `index.html` (`view-blog`, `view-blog-post`), `app-v5.js` (`renderBlog`, `renderBlogPost`), `style-v5.css` (cards `.blog-card`, `.blog-post-body`).

**Layout (referência):** reutilizar linguagem visual de `.avisos-item` + `.action-card` / banner evento; imagem 16:9 no topo; data + título + excerpt; botão voltar.

### 7.4 Entrada «Blog / Novidades»

Condição: **`auth.user`** (qualquer conta logada na PWA).

| Local | Comportamento |
|-------|----------------|
| **Home** | Card «Novidades» (secção visível após login — junto ao dashboard voluntário ou bloco dedicado `#home-news-card`) |
| **Menu hambúrguer** | Item `#header-menu-news` — «Novidades» / «Blog» (`data-i18n="header_menu_news"`) |
| **Hub avisos** | Link «Ver todas as novidades» → `#blog` |
| **Bottom nav** | Sem alteração (ADR-012) |

Visitante anónimo: **sem** card, **sem** item no menu, **sem** posts no sino; API `/news` retorna 401.

---

## 8. Admin WordPress

- Meta box **«Zelo — App móvel»** no editor de Posts (side).
- Checkbox «Publicar na PWA», «Mostrar como notificação».
- Nota no admin: conteúdo em **português**; visível só a utilizadores logados na app.
- Capacidade: `edit_posts` (já existente); sem endpoint de escrita REST na v1.

**Alternativa futura:** submenu em Locais Zelo «Novidades» filtrando só posts com meta (atalho `edit.php?zelo_in_app=1`).

---

## 9. Sequência de entrega sugerida

| Fase | Entrega | Saída |
|------|---------|-------|
| **1** | Meta box + `GET /news` + teste Postman | Plugin **2.13.0** |
| **2** | `api-v5.js` + cache offline | — |
| **3** | View lista + detalhe (`blog`, `blog-post`) | PWA build **116** |
| **4** | Hub avisos + badge + card home + item menu hambúrguer | PWA **117** |
| **5** | i18n strings + `TESTING.md` §4 | Docs |
| **6** | (Opcional pós-MVP) Publicar → fila Web Push #8 | Issue separada |

---

## 10. Critérios de aceite

### Admin
- [ ] Post «Publicar na PWA» + publicado → aparece em `GET /news` **com sessão**.
- [ ] «Mostrar como notificação» → item no hub avisos (id `post-{id}`) para utilizador logado.
- [ ] `GET /news` sem cookie → **401**.

### PWA — utilizador logado
- [ ] Sino: badge para notificação de post não lida; clicar abre detalhe e marca lida.
- [ ] Card «Novidades» na home + item no menu hambúrguer → `#blog`.
- [ ] `#blog` lista posts (imagem, título, data, excerpt); `#blog-post` mostra corpo PT.
- [ ] Offline: última lista em cache (pós-login) com indicador stale.

### PWA — anónimo
- [ ] Sem posts no hub avisos; sem card/menu Blog; `/news` não chamado ou 401.

### Regressão
- [ ] Avisos ops inalterados; `home_notice` crítico OK; bottom nav 5 itens.

---

## 11. Relação com outras issues

| Issue | Relação |
|-------|---------|
| **#16** | MVP #26 mantém `localStorage` para lido; #16 migra ids `post-*` para API `PATCH /news/{id}/read` |
| **#9** | Motor unificado pode tratar `source: wp_post` nos eventos de notificação |
| **#8** | Hook `save_post` → enqueue push quando `_zelo_as_notification` (fase 2) |
| **#15** | Carrossel home pode reutilizar `GET /news?featured=1` (campo meta futuro) |
| **#14** | Programação é destino distinto; pode ser post com categoria especial ou link externo |

---

## 12. Testes (`TESTING.md` — proposta §4.3)

1. Criar post PT + notificação → PWA **logada** → badge +1 → abrir → lido.
2. PWA **anónima** → sem post no sino; menu sem «Novidades».
3. Logado → card home + menu hambúrguer → `#blog` → detalhe HTML.
4. Modo avião após 1.ª carga logada → lista em cache.
5. Regressão: compromisso pendente ainda aparece para voluntário.

---

## 13. Riscos

| Risco | Mitigação |
|-------|-----------|
| Conteúdo HTML quebra layout | `wp_kses_post`; CSS `.blog-post-body` isolado |
| Muitas notificações → badge alto | Só posts com `_zelo_as_notification`; arquivo após 7 dias (opcional) |
| Duplicação com #9/#16 | Escopo MVP explícito; ADR curto ao ir para Ready |
| Posts em PT only | Label UI traduzida; conteúdo como publicado |

---

## 14. Estimativa

| Área | Tamanho |
|------|---------|
| Plugin (meta + REST + cache) | **M** |
| PWA views + hub + home card | **M** |
| i18n + CSS + docs | **S** |
| **Total** | **~1–2 sessões** (sem Web Push) |

---

## 15. Próximo passo

1. Mover issue para **Ready** no [Project 3](https://github.com/users/esvianna/projects/3).
2. Implementar fase 1 (meta box + API auth) → fases 3–4 (PWA + entradas home/menu).
