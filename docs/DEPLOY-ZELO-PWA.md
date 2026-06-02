# Deploy Zelo PWA e WordPress (operação voluntários)

## Same-origin (MVP)

- Sirva a PWA **no mesmo domínio** que o WordPress (ou subdomínio com cookies partilhados conforme configuração do servidor) para o login REST com cookies funcionar.
- Use **HTTPS** em produção.
- Teste: login na PWA → `GET /wp-json/zelo/v1/ops/voluntarios` com cabeçalho `X-WP-Nonce` retornado no login deve responder **200** (não 401).

## Checklist rápido

1. URL em `frontend-pwa/assets/js/api-v5.js` (`baseUrl` / `siteUrl`) aponta para o site correto.
2. WordPress: utilizadores com roles Zelo atribuídas (menu **Zelo → Roles Zelo**).
3. Escala preenchida em **Zelo → Operação Voluntários** (abas Escala / Governança / Config). Para e-mails de lembrete, preencha **datas do evento** (Y-m-d) por dia da semana.
4. Cron WordPress a correr (visitas ao site ou cron do sistema) para `zelo_volunteer_notify_tick` (horário).

## Cross-domain (futuro)

Se a PWA for hospedada noutro domínio, será necessário JWT ou Application Passwords + CORS; não faz parte do MVP atual.

## Exportação

O endpoint `GET /wp-json/zelo/v1/ops/export` (plugin **2.9.0+**) gera PDF da escala para utilizadores com `zelo_manage_ops`. Parâmetros: `format=pdf`, `day` (sexta|sabado|domingo), `shift` (opcional). Botão na PWA build **89+**. No FTP, incluir **`inc/lib/fpdf.php`** e a pasta **`inc/lib/font/`** (14 ficheiros `.php`; plugin **2.9.3+**).

## Cadastrar escala do Congresso (dia + turno)

A programação do dept. de informações tem **três blocos** (Sexta, Sábado, Domingo). No ZELO:

1. **Config** — datas: Sexta `2026-06-26`, Sábado `2026-06-27`, Domingo `2026-06-28` (ajuste ao evento real).
2. **Turnos** — horários globais: A1/B1 `07:00–12:30`, A2/B2 `12:30–18:30`; **local/posto** por turno (2.10.1+).
3. **Governança** — **um bloco por dia**: supervisores Grupo A/B/App + homens-chave A1–B2 (podem **rodar** entre dias).
4. **Voluntários** — roster com `expected_email` para cadastro no app.
5. **Escala** — **uma linha por voluntário por faixa horária** (Dia, Turno A1–B2, **Início/Fim** — local vem do turno; plugin 2.10.0+). Várias linhas no mesmo turno com horários diferentes são permitidas.
6. **Onboarding** — confirmar cadastros com e-mail pendente; aprovar vínculos cadastro ↔ roster.

Após salvar Config, os selects de dia no admin e a PWA exibem a data (ex.: `Sexta · 26/06`).
