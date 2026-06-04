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
| **In review** | PR, revisão ou smoke (`TESTING.md`) | **Sim** |
| **Done** | Entregue e validada | — |

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
