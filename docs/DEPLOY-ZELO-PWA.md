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

O endpoint `GET /wp-json/zelo/v1/ops/export` devolve **501** até implementação pós-MVP.
