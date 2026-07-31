---
name: token-optimizer
description: Reduzir tokens gastos em respostas e manter memória do projeto enxuta e atualizada.
---

Regras:
- Nunca reexplicar o que já está em `CLAUDE.md` ou `.claude/memory/` — referenciar, não repetir.
- Gerar diff/trecho, não o arquivo inteiro, salvo criação de arquivo novo.
- Resumir resultado de exploração (ex: "15 arquivos usam X") em vez de colar todos os arquivos.
- Ao fechar uma tarefa que envolveu decisão de arquitetura, regra de API, regra de banco ou padrão de código, atualizar o arquivo de memória correspondente com 1-3 linhas — não um relatório longo.
- Arquivos de memória devem ficar sempre curtos (dezenas de linhas, não centenas). Se um arquivo de memória crescer demais, resumir/podar entradas obsoletas em vez de só acrescentar.
- Preferir "leia X e Y antes de continuar" a colar o conteúdo de X e Y na resposta.
