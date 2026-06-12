# Fluxo de trabalho — GitHub Issues + Project

Backlog oficial do ZELO a partir de **2026-06-04**.

| Recurso | URL |
|---------|-----|
| Repositório | https://github.com/esvianna/ZELO |
| Project (Kanban) | https://github.com/users/esvianna/projects/3 |

## Status do quadro

| Status | Significado | Codificação |
|--------|-------------|-------------|
| **Backlog** | Demanda registrada; priorização e plano pendentes | **Não** |
| **Ready** | Plano aprovado; pode iniciar implementação | **Sim** |
| **In progress** | Em desenvolvimento | **Sim** |
| **In review** | Implementação entregue; **aguarda validação/smoke** do responsável (`TESTING.md`) | **Sim** (agente para aqui) |
| **Done** | Entregue **e validada** pelo responsável (ou PR mergeado + smoke OK) | — (só humano após OK) |

## Ciclo de vida de uma issue (agente / Cursor)

```
Backlog → Ready → In progress → In review → Done
                                    ↑
                         agente para AQUI após implementar
                         (não mover para Done sozinho)
```

1. **Backlog** — issue criada; plano pendente.
2. **Ready** — plano aprovado; pode codificar.
3. **In progress** — agente ou dev a implementar.
4. **In review** — código/docs entregues; comentário na issue com **como testar** e versões (plugin/PWA). **Issue permanece aberta.** Aguardar OK do responsável.
5. **Done** — responsável validou em staging/produção e move no Project (ou fecha a issue).

### Regras para agentes de IA

- **Nunca** mover para **Done** nem **fechar** a issue após implementar — salvo pedido explícito do usuário («pode fechar», «testado OK», etc.).
- **Sempre** mover o card para **In review** via CLI ao terminar o código/docs — comentar na issue **sem** mover o card não cumpre o fluxo.
- Comentar na issue: resumo da entrega, versões deploy, passos de `TESTING.md`.
- **Done** + fechar issue: apenas quando o usuário confirmar testes ou pedir explicitamente.

## Nova tarefa

1. Criar issue em `esvianna/ZELO`.
2. Adicionar ao [Projeto ZELO](https://github.com/users/esvianna/projects/3) em **Backlog**.
3. Preencher **Priority**, **Size**, **Target date** quando souber.
4. Elaborar **plano** (escopo, arquivos, testes, riscos) e obter aprovação do responsável.
5. Mover para **Ready** → só então iniciar código (humano ou agente Cursor).

## Migração SITE-NOVO-VTIS → ZELO (2026-06-04)

| Antiga (fechada) | Canônica em ZELO |
|------------------|------------------|
| [SITE-NOVO-VTIS#1](https://github.com/esvianna/SITE-NOVO-VTIS/issues/1) | [ZELO#1](https://github.com/esvianna/ZELO/issues/1) |
| [SITE-NOVO-VTIS#2](https://github.com/esvianna/SITE-NOVO-VTIS/issues/2) | [ZELO#2](https://github.com/esvianna/ZELO/issues/2) |

A transferência nativa GitHub não é permitida de repositório **privado** para **público**; as issues em SITE-NOVO-VTIS foram fechadas com link para ZELO.

## Relação com documentação no repo

- **`PROJECT_STATUS.md`** — continuidade entre sessões (não substitui o Project).
- **`ROADMAP.md`** — visão de fases; itens grandes viram issues/epics no GitHub.
- **`AGENTS.md`** / **`.cursor/rules/`** — agentes devem seguir este fluxo.

## Comandos `gh`

```powershell
gh issue create -R esvianna/ZELO --title "Título" --body "Descrição e critérios de aceite"
gh project item-add 3 --owner esvianna --url https://github.com/esvianna/ZELO/issues/NUMERO
gh issue develop NUMERO -R esvianna/ZELO   # branch vinculada
```

PR com `Closes #NUMERO` fecha a issue ao merge.

### Mover status de um card (agentes — obrigatório)

O Project usa **node IDs** (não o número `3` em `--project-id`).

| Recurso | ID |
|---------|-----|
| `project-id` | `PVT_kwHOBfIcG84BZhqu` |
| Campo Status | `PVTSSF_lAHOBfIcG84BZhquzhUftHw` |
| Backlog | `f75ad846` |
| Ready | `61e4505c` |
| In progress | `47fc9ee4` |
| In review | `df73e18b` |
| Done | `98236657` |

**Obter `item-id` da issue N:**

```powershell
gh project item-list 3 --owner esvianna --format json --limit 100 --jq ".items[] | select(.content.number == N) | .id"
```

**Exemplos:**

```powershell
# In progress — ao iniciar codificação
gh project item-edit --project-id PVT_kwHOBfIcG84BZhqu --id <item-id> --field-id PVTSSF_lAHOBfIcG84BZhquzhUftHw --single-select-option-id 47fc9ee4

# In review — ao concluir implementação (padrão do agente)
gh project item-edit --project-id PVT_kwHOBfIcG84BZhqu --id <item-id> --field-id PVTSSF_lAHOBfIcG84BZhquzhUftHw --single-select-option-id df73e18b
```

**Done** (`98236657`): somente após validação do responsável.

### Sincronizar Done → documentação

Ao **iniciar sessão** ou pegar **nova issue**, o agente consulta o Project e alinha o repo:

| Onde | Ação quando issue = **Done** |
|------|------------------------------|
| `PROJECT_STATUS.md` | Sair de pendências; entrar em «Validadas no Project (Done)» |
| `CHANGELOG.md` | Na entrada `[Unreleased]` da issue, linha `\| Validação \| **Done** no Project (#N) \|` |
| Release datada | **Não** — só quando plugin/PWA forem publicados em produção |

Consulta rápida (todas as issues do Project):

```powershell
gh api graphql -f query='query { user(login: \"esvianna\") { projectV2(number: 3) { items(first: 50) { nodes { content { ... on Issue { number title } } fieldValues(first: 10) { nodes { ... on ProjectV2ItemFieldSingleSelectValue { name field { ... on ProjectV2SingleSelectField { name } } } } } } } } } }' --jq '.data.user.projectV2.items.nodes[] | select(.content.number != null) | {number: .content.number, status: ([.fieldValues.nodes[] | select(.field.name == \"Status\") | .name][0])}'
```

Detalhes: `.cursor/rules/zelo-github-backlog.mdc` § «Sincronizar Done».
