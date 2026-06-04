# SECURITY.md — ZELO

Princípios e checklist de segurança adaptados a **WordPress REST + PWA estática**.

---

## Princípios gerais

1. **Menor privilégio** — cada endpoint com `permission_callback` explícito; capabilities Zelo apenas onde necessário.
2. **Dados públicos vs sensíveis** — locais/evento são públicos; escala, check-ins e swaps são sensíveis.
3. **Defesa em profundidade** — sanitizar na entrada, escapar na saída (admin e REST quando renderizado em HTML).
4. **HTTPS em produção** — obrigatório para login e cookies.
5. **Sem segredos no repositório** — API keys Google, senhas SMTP, etc. ficam em `wp-config` ou options do WP, nunca commitadas.

---

## Backend (PHP / WordPress)

### Entrada (input)

| Área | Prática |
|------|---------|
| REST params | `sanitize_text_field`, `sanitize_email`, validação de tipos nos `args` da rota |
| Admin POST | nonces WP, `current_user_can` |
| Cadastro | senha mín. 8 caracteres; rate limit por IP (`zelo_registration_rate_limit_ok`) |
| Importadores | validar coordenadas; limitar batch (evitar DoS/timeouts) |

### Saída (output)

| Área | Prática |
|------|---------|
| Admin listagens | `esc_html`, `esc_html_e` |
| REST JSON | não incluir senhas, tokens internos ou paths de servidor |
| E-mails | links de verificação com token único e expiração |

### Autenticação e autorização

Matriz completa (roles, endpoints, IDOR): **[docs/OPS-PERMISSIONS.md](docs/OPS-PERMISSIONS.md)** (ZELO#13, plugin 2.11.8+).

| Endpoint | Regra esperada |
|----------|----------------|
| `/auth/login` | Público; falha genérica; verificar e-mail se aplicável |
| `/auth/register` | Público com rate limit; filtro `zelo_registration_enabled` |
| `/ops/voluntarios` | **Autenticado + `zelo_view_ops`** — **não** activar `zelo_ops_voluntarios_public_read` |
| `/ops/checkin`, `/checkout` | `zelo_checkin_ops` + titular ou supervisão na linha (governança) |
| `/ops/reallocate` | `zelo_reallocate_volunteer` + supervisão na linha (2.11.8+) |
| `/ops/schedule` | `zelo_edit_schedule` + escopo dia/turno na governança |
| `/ops/swap-requests` GET/PATCH | Gestor ou supervisor do turno da designação (2.11.8+) |
| `/ops/swap-requests` POST | `zelo_checkin_ops` + titular da linha |
| `/ops/export` | `zelo_manage_ops` ou `manage_options`; rate limit 60 s/usuário |
| `/clima` | Público; proxy Open-Meteo; sem PII; cache transient 30 min |

### Ameaças e mitigação

| Ameaça | Mitigação no ZELO |
|--------|-------------------|
| SQL Injection | `$wpdb->prepare` se SQL raw; preferir WP APIs |
| XSS (admin) | `esc_*` em colunas e notices |
| XSS (PWA) | preferir `textContent` / templates escapados; cuidado com `innerHTML` dinâmico em `app-v5.js` |
| CSRF REST | `X-WP-Nonce` após login; cookies same-site |
| Brute force login | considerar plugin WP ou rate limit futuro |
| Exposição de escala | **remover** filtro `zelo_ops_voluntarios_public_read` |
| IDOR em ops | validar que usuário só altera assignments permitidos (revisar em mudanças) |

### Logs

- Não logar senhas, nonces completos ou tokens de verificação.
- `error_log` em produção: evitar dados pessoais (e-mail, telefone) em volume.

---

## Frontend (PWA)

| Tópico | Prática |
|--------|---------|
| Credenciais | senha só via HTTPS POST; nonce em `localStorage` (`zelo_user`) — risco XSS se houver injeção |
| API base | `api-v5.js` aponta produção; não commitar credenciais |
| Offline cache | não cachear respostas autenticadas sensíveis além do necessário |
| Links externos | `rel="noopener"` em `target="_blank"` quando aplicável |

---

## Checklist antes de deploy em evento

- [ ] HTTPS ativo
- [ ] Filtro público de ops **removido**
- [ ] Roles Zelo atribuídas apenas a contas necessárias
- [ ] Cadastro público: decisão consciente (ligado/desligado)
- [ ] Backup do banco (locais + options)
- [ ] Chaves Google Places / SMTP não expostas no git
- [ ] Smoke test login + ops com nonce (ver `TESTING.md`)

---

## Reportar vulnerabilidades

Registrar em `DECISIONS.md` ou issue privada com o mantenedor do projeto; não divulgar detalhes de exploit em commits públicos até correção.
