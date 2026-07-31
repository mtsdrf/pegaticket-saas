# PegaTicket — Brand Guidelines

## Nome da marca

PegaTicket

O nome deve ser mantido em todas as aplicações.

## Conceito da marca

PegaTicket é um sistema SaaS de gestão comercial criado para organizar pedidos, clientes, produtos e indicadores de forma clara, moderna e eficiente.

A marca deve transmitir:

- Clareza.
- Controle.
- Organização.
- Crescimento.
- Movimento.
- Tecnologia.
- Confiança.
- Profissionalismo.
- Agilidade operacional.

## Posicionamento

Para empresas que precisam controlar pedidos, clientes, produtos e resultados, PegaTicket é uma plataforma de gestão comercial que centraliza a operação em uma experiência clara, visual e inteligente.

## Tagline oficial

Gestão clara para empresas em movimento.

## Personalidade da marca

A PegaTicket é:

- Moderna.
- Confiável.
- Clara.
- Inteligente.
- Direta.
- Produtiva.
- Tecnológica.
- Profissional.
- Levemente premium.

A PegaTicket não é:

- Genérica.
- Infantil.
- Poluída.
- Financeira demais.
- Fria demais.
- Antiga.
- Clichê.
- Parecida com template comum.
- Visualmente exagerada.

## Direção da logo

A logo deve seguir a direção:

**M com movimento**

O símbolo deve ser baseado na letra `M`, com sensação sutil de avanço, evolução e crescimento.

A logo deve parecer:

- Geométrica.
- Minimalista.
- Moderna.
- Tecnológica.
- Memorável.
- Limpa.
- Profissional.
- Legível em tamanhos pequenos.

## Símbolo

**Atualização 2026-07-13**: o símbolo oficial passou a ser um logo fornecido pelo usuário
(`M` geométrico em azul-marinho + gráfico de barras ascendente com seta em teal,
`web/public/logo.png`, aplicado via `web/src/components/ui/Logo.tsx`). Isso **substitui** a
regra anterior que proibia seta literal e gráfico de barras/financeiro no símbolo — decisão
consciente do usuário ao fornecer o asset, não um esquecimento. Não reverter para SVG
geométrico sem seta/barras sem confirmar de novo com o usuário.

O símbolo da PegaTicket deve ser um `M` geométrico com movimento sutil para frente.

Ele deve funcionar como:

- Logo compacta.
- Favicon.
- Ícone da aplicação.
- Marca d’água discreta.
- Elemento visual em login.
- Avatar visual da marca.

## O que evitar na logo

Não usar:

- Seta literal.
- Gráfico de barras comum.
- Gráfico financeiro.
- Ícone genérico de analytics.
- Ícone de relatório comum.
- Imagem de banco de imagem.
- Elementos complexos demais.
- Muitos detalhes internos.
- Símbolo que não funcione em favicon.
- Visual corporativo antigo.

## Wordmark

O texto `PegaTicket` deve usar uma fonte sans-serif moderna, com peso médio ou semibold.

Fontes recomendadas:

- Manrope SemiBold.
- Geist SemiBold.
- Inter SemiBold.

O wordmark deve ter aparência limpa, tecnológica e confiável.

## Versões obrigatórias da marca

A identidade deve prever:

- Logo horizontal: símbolo + PegaTicket.
- Logo compacta: apenas símbolo.
- Favicon: símbolo simplificado.
- Versão light mode.
- Versão dark mode.
- Versão monocromática.
- Versão para sidebar/navbar.
- Versão para tela de login.

## Tom de voz

A PegaTicket fala de forma:

- Clara.
- Objetiva.
- Confiante.
- Humana.
- Profissional.
- Simples.
- Sem jargão desnecessário.
- Sem frases clichês.

## Experiência de marca no sistema

A marca não vive só na logo — vive em cada tela. Aplicar de forma consistente:

- Tom de voz humano e direto em toda mensagem visível ao usuário (sucesso, erro, vazio, loading).
- Nunca usar mensagem técnica crua ("Error 500", stack trace) — sempre traduzir para linguagem clara.
- Estado vazio nunca é "tela quebrada": sempre explica o que fazer a seguir (ex.: "Nenhum pedido ainda. Crie o primeiro pedido para começar.").
- Cor de destaque (`--pt-accent`) reservada para elementos que indicam progresso/positivo (ex.: métrica em alta, ação concluída) — não usar como cor decorativa aleatória.
- Loading nunca é só um spinner solto: usar skeleton ou mensagem curta que mantenha o usuário orientado.
- Toda tela nova deve parecer parte do mesmo produto que o login — mesma paleta, mesma tipografia, mesmo tom.

## Exemplos de texto

### Login

Título:

```txt
Bem-vindo ao PegaTicket
```

Subheadline:

```txt
Gestão clara para empresas em movimento.
```

Botão principal:

```txt
Entrar no painel
```

Link secundário:

```txt
Atualizar sistema
```

### Dashboard

Título da página:

```txt
Visão geral
```

Subtítulo:

```txt
Acompanhe os principais números da operação.
```

Ações rápidas:

```txt
Novo pedido
Adicionar cliente
Cadastrar produto
```

Métricas:

```txt
Pedidos entregues
Pedidos pendentes
Valor recebido
```