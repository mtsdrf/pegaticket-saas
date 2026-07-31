import AutoStoriesOutlinedIcon from '@mui/icons-material/AutoStoriesOutlined'
import BoltOutlinedIcon from '@mui/icons-material/BoltOutlined'
import CheckCircleOutlinedIcon from '@mui/icons-material/CheckCircleOutlined'
import ErrorOutlineOutlinedIcon from '@mui/icons-material/ErrorOutlineOutlined'
import HubOutlinedIcon from '@mui/icons-material/HubOutlined'
import LockOutlinedIcon from '@mui/icons-material/LockOutlined'
import MilitaryTechOutlinedIcon from '@mui/icons-material/MilitaryTechOutlined'
import OpenInNewOutlinedIcon from '@mui/icons-material/OpenInNewOutlined'
import PlayCircleOutlineOutlinedIcon from '@mui/icons-material/PlayCircleOutlineOutlined'
import PsychologyAltOutlinedIcon from '@mui/icons-material/PsychologyAltOutlined'
import QuizOutlinedIcon from '@mui/icons-material/QuizOutlined'
import RestartAltOutlinedIcon from '@mui/icons-material/RestartAltOutlined'
import SchoolOutlinedIcon from '@mui/icons-material/SchoolOutlined'
import TaskAltOutlinedIcon from '@mui/icons-material/TaskAltOutlined'
import TrendingUpOutlinedIcon from '@mui/icons-material/TrendingUpOutlined'
import WarningAmberOutlinedIcon from '@mui/icons-material/WarningAmberOutlined'
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Divider,
  IconButton,
  LinearProgress,
  Paper,
  Stack,
  Typography,
} from '@mui/material'
import { useEffect, useMemo, useState } from 'react'
import { Link as RouterLink } from 'react-router-dom'
import { ACCESS } from '../../access/requirements'
import { PageHeader } from '../../components/layout/PageHeader'
import { useAccessControl } from '../../hooks/useAccessControl'
import { useAuth } from '../../hooks/useAuth'
import { useOnboardingChecklist } from '../../hooks/useOnboardingChecklist'
import { useUserProfile } from '../../hooks/useUserProfile'
import { FORM_GRID_2_SX, FORM_GRID_3_SX, PAGE_CONTAINER_SX, UI_SIZE } from '../../styles/layoutStandards'
import { ELEVATED_SURFACE_SX, SOFT_PANEL_SX } from '../../styles/surfaces'
import type { PermissionRequirement } from '../../types/access'
import { readTrainingCenterProgress, trainingCenterEmptyProgress, writeTrainingCenterProgress, type TrainingCenterStoredProgress } from '../../utils/trainingCenterStorage'

type ModuleStatus = 'implemented' | 'partial' | 'planned'
type TrackAudience = 'owner' | 'operations' | 'finance' | 'catalog' | 'delivery' | 'support'

interface TrainingOperation {
  title: string
  purpose: string
  when: string
  actor: string
  route?: string
  permissionLabel: string
  requirements: string[]
  effects: string[]
  commonErrors: string[]
  goodPractices: string[]
}

interface TrainingModule {
  id: string
  name: string
  summary: string
  status: ModuleStatus
  route?: string
  audience: string[]
  functionality?: string
  requirement?: PermissionRequirement
  whoUses: string
  dependencies: string[]
  connectsTo: string[]
  teaches: string[]
  risks: string[]
  operations: TrainingOperation[]
}

interface TrainingTrack {
  id: string
  title: string
  audience: TrackAudience
  description: string
  modules: string[]
  outcome: string
}

interface TrainingQuiz {
  question: string
  options: string[]
  correctIndex: number
  explanation: string
}

const TRAINING_MODULES: TrainingModule[] = [
  {
    id: 'empresa',
    name: 'Empresa e implantação',
    summary: 'Explica a base da empresa ativa, plano, dados cadastrais, dono e sequência correta de implantação.',
    status: 'implemented',
    route: '/configuracoes/assinatura',
    audience: ['Proprietário', 'Administrador', 'Implantação'],
    whoUses: 'Proprietário da empresa, administradores da plataforma e quem conduz implantação.',
    dependencies: [],
    connectsTo: ['usuarios', 'catalogo', 'loja', 'financeiro'],
    teaches: [
      'Como o plano limita funcionalidades',
      'Por que endereço e dados da empresa impactam loja, fiscal e cobrança',
      'Qual é a ordem correta para colocar a empresa em operação',
    ],
    risks: [
      'Implantar sem dados da empresa gera bloqueios em loja, entrega e fiscal',
      'Trocar plano sem revisar permissões pode surpreender a operação',
    ],
    operations: [
      {
        title: 'Conferir plano e status da empresa',
        purpose: 'Validar se a empresa está em trial, ativa, suspensa ou em renovação.',
        when: 'No início da implantação e sempre que houver dúvida de acesso.',
        actor: 'Proprietário da empresa',
        route: '/configuracoes/assinatura',
        permissionLabel: 'subscription:read',
        requirements: ['Empresa ativa no contexto', 'Usuário com acesso à área de assinatura'],
        effects: ['Mostra plano atual, ciclo, cobrança, trial e histórico operacional'],
        commonErrors: ['Plano incompatível com o módulo que o usuário quer usar'],
        goodPractices: ['Validar o plano antes de cadastrar processos que dependem de módulos pagos'],
      },
    ],
  },
  {
    id: 'usuarios',
    name: 'Usuários, grupos e perfis',
    summary: 'Mostra quem pode operar, como o acesso é construído e como evitar erro de permissão.',
    status: 'implemented',
    route: '/admin/tenant-users',
    audience: ['Proprietário', 'Administrador', 'RH interno'],
    functionality: 'tenant_users',
    requirement: ACCESS.tenantUsersRead,
    whoUses: 'Proprietário e administradores que organizam o acesso da equipe.',
    dependencies: ['empresa'],
    connectsTo: ['pedidos', 'catalogo', 'estoque', 'financeiro'],
    teaches: [
      'Diferença entre grupos globais e perfis da empresa',
      'Como o plano e o perfil se combinam',
      'Como liberar acesso sem abrir permissões desnecessárias',
    ],
    risks: [
      'Dar acesso amplo demais expõe telas e operações sensíveis',
      'Criar usuário sem perfil impede operação mesmo com login válido',
    ],
    operations: [
      {
        title: 'Vincular usuário à empresa',
        purpose: 'Permitir que a pessoa opere a empresa ativa no PegaTicket.',
        when: 'Ao montar equipe inicial ou incluir um novo colaborador.',
        actor: 'Proprietário ou administrador',
        route: '/admin/tenant-users',
        permissionLabel: 'tenant_users:read/create',
        requirements: ['Empresa já criada', 'Perfil da empresa já definido'],
        effects: ['Cria vínculo do usuário com a empresa e conecta perfil/permissões'],
        commonErrors: ['Usuário criado, mas sem perfil ativo', 'Perfil sem as permissões do módulo necessário'],
        goodPractices: ['Separar perfis por função real: operação, financeiro, estoque, gestor'],
      },
    ],
  },
  {
    id: 'catalogo',
    name: 'Produtos e catálogo',
    summary: 'Ensina a estruturar categorias, tipos, produtos, preços e opcionais do catálogo.',
    status: 'implemented',
    route: '/produtos',
    audience: ['Cadastro', 'Comercial', 'Contador', 'Operação'],
    functionality: 'products',
    requirement: ACCESS.productsRead,
    whoUses: 'Quem mantém os itens vendidos ou publicados na loja.',
    dependencies: ['empresa'],
    connectsTo: ['estoque', 'pedidos', 'loja', 'delivery', 'fiscal'],
    teaches: [
      'Diferença entre categoria, tipo e produto',
      'Como o cadastro impacta loja, estoque, relatórios e integrações',
      'Quando usar preço promocional, atacado e opcionais',
    ],
    risks: [
      'Produto mal cadastrado quebra estoque, fiscal, loja e integração externa',
      'SKU inconsistente dificulta importação do iFood e conferência operacional',
    ],
    operations: [
      {
        title: 'Cadastrar produto operacional',
        purpose: 'Criar um item pronto para estoque, venda e publicação.',
        when: 'Durante implantação inicial ou expansão do catálogo.',
        actor: 'Cadastro, comercial ou contador com acesso fiscal',
        route: '/produtos/novo',
        permissionLabel: 'products:create',
        requirements: ['Categoria e tipo definidos', 'Estratégia de preço conhecida'],
        effects: ['Produto passa a aparecer em pedidos, loja, estoque e relatórios'],
        commonErrors: ['Categoria errada', 'Preço base vazio', 'Dados fiscais ausentes quando o módulo fiscal exige'],
        goodPractices: ['Manter SKU estável', 'Preencher dados comerciais e fiscais já no cadastro'],
      },
    ],
  },
  {
    id: 'estoque',
    name: 'Disponibilidade e reserva',
    summary: 'Mostra como a disponibilidade entra no fluxo do pedido e por que reserva e baixa continuam críticas para a operação.',
    status: 'implemented',
    route: '/pedidos/novo',
    audience: ['Estoque', 'Operação', 'Gestão'],
    functionality: 'orders',
    requirement: ACCESS.ordersRead,
    whoUses: 'Gestão, operação e quem precisa validar disponibilidade antes de vender.',
    dependencies: ['catalogo'],
    connectsTo: ['pedidos', 'loja', 'delivery'],
    teaches: [
      'Como reservas e baixas evitam venda acima do saldo',
      'Como o produto usa local padrão e saldo disponível para aprovar o fluxo',
      'Como interpretar falta de disponibilidade antes de prometer prazo ao cliente',
    ],
    risks: [
      'Prometer item sem disponibilidade gera retrabalho operacional',
      'Saldo incoerente ou local padrão ausente bloqueia parte do fluxo de pedidos',
    ],
    operations: [
      {
        title: 'Validar disponibilidade antes de vender',
        purpose: 'Conferir se o pedido pode seguir sem bloquear a operação depois.',
        when: 'Durante a montagem ou revisão do pedido.',
        actor: 'Operação, atendimento ou gestor',
        route: '/pedidos/novo',
        permissionLabel: 'orders:create',
        requirements: ['Produto ativo', 'Estratégia de estoque da empresa configurada'],
        effects: ['Evita prometer item sem saldo e reduz correção manual posterior'],
        commonErrors: ['Ignorar regra de bloquear pedido sem estoque', 'Assumir que todo item usa o mesmo local padrão'],
        goodPractices: ['Revisar disponibilidade junto com prazo prometido', 'Corrigir saldo antes de reabrir venda do item'],
      },
    ],
  },
  {
    id: 'clientes',
    name: 'Cliente no contexto do pedido',
    summary: 'Mostra quais dados do cliente realmente sustentam pedido, entrega, cobrança e leitura operacional nesta fase.',
    status: 'implemented',
    route: '/pedidos/novo',
    audience: ['Comercial', 'Atendimento', 'Financeiro'],
    functionality: 'orders',
    requirement: ACCESS.ordersRead,
    whoUses: 'Atendimento, comercial e financeiro.',
    dependencies: ['empresa', 'catalogo'],
    connectsTo: ['pedidos', 'financeiro', 'loja', 'analytics'],
    teaches: [
      'Quando o cliente precisa existir antes do pedido',
      'Por que endereço e telefone influenciam entrega e cobrança',
      'Como um cadastro incompleto prejudica rastreio, contato e leitura do histórico',
    ],
    risks: [
      'Cliente duplicado distorce histórico e relatórios',
      'Endereço incompleto dificulta rota, taxa e rastreio',
    ],
    operations: [
      {
        title: 'Preencher o cliente certo no pedido',
        purpose: 'Garantir que a venda saia com contexto confiável para entrega, contato e histórico.',
        when: 'Na criação ou revisão de um pedido interno.',
        actor: 'Atendimento ou comercial',
        route: '/pedidos/novo',
        permissionLabel: 'orders:create',
        requirements: ['Dados mínimos de contato', 'Endereço válido quando houver entrega'],
        effects: ['Pedido nasce com contexto suficiente para cobrança, logística e histórico'],
        commonErrors: ['Telefone principal ausente', 'Cliente errado reaproveitado só para acelerar a venda'],
        goodPractices: ['Validar contato e endereço no momento da venda', 'Evitar duplicar cadastro para pequenas variações de nome'],
      },
    ],
  },
  {
    id: 'pedidos',
    name: 'Pedidos e operação diária',
    summary: 'Mostra como o pedido impacta estoque, financeiro, entrega, loja, fiscal e analytics.',
    status: 'implemented',
    route: '/pedidos',
    audience: ['Operação', 'Atendimento', 'Gestão'],
    functionality: 'orders',
    requirement: ACCESS.ordersRead,
    whoUses: 'Atendimento, operação, logística, gestão e financeiro.',
    dependencies: ['clientes', 'catalogo', 'estoque'],
    connectsTo: ['financeiro', 'fiscal', 'rotas', 'delivery', 'relatorios'],
    teaches: [
      'Fluxo completo: criar, pagar, entregar, cancelar, revisar itens',
      'Como o pedido reserva e baixa estoque',
      'O que muda quando o pedido vem da loja ou do iFood',
    ],
    risks: [
      'Cancelar pedido pago exige tratamento correto',
      'Editar itens sem revisar parcelas pode gerar inconsistência operacional',
    ],
    operations: [
      {
        title: 'Criar pedido interno',
        purpose: 'Registrar a venda operacional com impacto em estoque, financeiro e histórico do cliente.',
        when: 'Venda por atendimento interno, balcão ou equipe comercial.',
        actor: 'Operação ou atendimento',
        route: '/pedidos/novo',
        permissionLabel: 'orders:create',
        requirements: ['Cliente e produtos válidos', 'Saldo disponível quando a empresa bloqueia venda sem estoque'],
        effects: ['Reserva estoque, cria totais, parcelas e torna o pedido rastreável'],
        commonErrors: ['Produto sem saldo', 'Cliente incompleto para entrega', 'Parcelas incompatíveis com o total'],
        goodPractices: ['Conferir itens antes de salvar', 'Separar claramente origem, pagamento e data de entrega'],
      },
    ],
  },
  {
    id: 'loja',
    name: 'Loja online e pedidos da loja',
    summary: 'Explica a jornada pública do cliente e o que a empresa precisa configurar para vender online.',
    status: 'implemented',
    route: '/configuracoes/loja-online',
    audience: ['Proprietário', 'Marketing', 'Operação digital'],
    functionality: 'storefront',
    requirement: ACCESS.storefrontUpdate,
    whoUses: 'Quem mantém a loja digital e quem opera pedidos vindos dela.',
    dependencies: ['empresa', 'catalogo', 'clientes'],
    connectsTo: ['pedidos', 'portal', 'financeiro', 'delivery'],
    teaches: [
      'Como ativar catálogo, horários, taxa e cupom',
      'Quando o pedido entra na fila da loja',
      'Como rastreio e portal aumentam a autonomia do cliente final',
    ],
    risks: [
      'Loja sem endereço/horário coerente gera expectativa errada no cliente',
      'Cupom e taxa mal configurados distorcem margem',
    ],
    operations: [
      {
        title: 'Configurar loja para primeira venda',
        purpose: 'Publicar uma operação mínima confiável para o cliente final.',
        when: 'Na implantação da loja ou ao reabrir o canal digital.',
        actor: 'Proprietário ou responsável digital',
        route: '/configuracoes/loja-online',
        permissionLabel: 'storefront:update',
        requirements: ['Produtos ativos', 'Endereço da loja', 'Horários e taxa definidos'],
        effects: ['Habilita leitura pública da loja e a jornada de checkout'],
        commonErrors: ['Taxa sem bairro configurado', 'Produto indisponível ainda publicado'],
        goodPractices: ['Testar carrinho e checkout antes de divulgar o link da loja'],
      },
    ],
  },
  {
    id: 'financeiro',
    name: 'Financeiro operacional e conciliação',
    summary: 'Ensina como fechar contexto financeiro do pedido e como usar a conciliação para revisar pagamentos e divergências.',
    status: 'implemented',
    route: '/financeiro/conciliacao',
    audience: ['Financeiro', 'Gestão'],
    functionality: 'finance',
    requirement: ACCESS.financeRead,
    whoUses: 'Financeiro, proprietários e gestão.',
    dependencies: ['pedidos'],
    connectsTo: ['analytics', 'assinatura'],
    teaches: [
      'Como pagamento do pedido afeta leitura financeira da operação',
      'Quando usar a tela de conciliação para investigar divergências',
      'Como separar falha operacional de falha no provedor de pagamento',
    ],
    risks: [
      'Baixa indevida altera indicadores e reconciliação',
      'Ignorar divergência de pagamento prolonga erro operacional e contábil',
    ],
    operations: [
      {
        title: 'Revisar conciliação financeira',
        purpose: 'Entender pagamentos, reembolsos e eventos que não fecharam como esperado.',
        when: 'Na rotina diária ou ao investigar divergência de cobrança.',
        actor: 'Financeiro',
        route: '/financeiro/conciliacao',
        permissionLabel: 'finance:read',
        requirements: ['Pedidos com pagamento processado', 'Equipe alinhada sobre fluxo de recebimento'],
        effects: ['Expõe divergências, status financeiros e sinais para correção operacional'],
        commonErrors: ['Tratar erro de webhook como inadimplência', 'Ajustar pedido sem revisar o evento financeiro'],
        goodPractices: ['Cruzar o pedido com o evento financeiro antes de corrigir', 'Usar a conciliação como rotina, não só como pós-incidente'],
      },
    ],
  },
  {
    id: 'fiscal',
    name: 'Fiscal e documento interno',
    summary: 'Explica o fluxo fiscal já implementado hoje e deixa claro o que ainda é interno/manual.',
    status: 'partial',
    route: '/configuracoes/perfis-fiscais',
    audience: ['Contador', 'Financeiro', 'Proprietário'],
    functionality: 'tax-rules',
    requirement: ACCESS.taxRulesRead,
    whoUses: 'Contador, responsável fiscal e proprietário.',
    dependencies: ['empresa', 'catalogo', 'pedidos'],
    connectsTo: ['financeiro', 'contador'],
    teaches: [
      'Como preparar documento fiscal interno a partir do pedido',
      'Quais pendências impedem o preparo',
      'Onde o fluxo já existe e onde ainda não existe integração oficial',
    ],
    risks: [
      'Assumir que o fluxo manual já equivale à emissão oficial',
      'Preparar documento sobre cadastro fiscal incompleto',
    ],
    operations: [
      {
        title: 'Preparar documento fiscal do pedido',
        purpose: 'Congelar um rascunho fiscal estruturado do pedido.',
        when: 'Depois que o pedido estiver consistente para análise fiscal.',
        actor: 'Responsável fiscal',
        permissionLabel: 'orders:update + tax-rules:read',
        requirements: ['Pedido válido', 'Perfil fiscal e regras mínimas definidos'],
        effects: ['Gera snapshot fiscal interno, série e número reservados'],
        commonErrors: ['Pendência crítica no cadastro', 'Confundir rascunho interno com emissão oficial'],
        goodPractices: ['Usar a prévia fiscal antes da preparação definitiva'],
      },
    ],
  },
  {
    id: 'delivery',
    name: 'Delivery e integrações',
    summary: 'Mostra o status atual das integrações e como pedidos externos entram no centro operacional do PegaTicket.',
    status: 'partial',
    route: '/configuracoes/integracoes',
    audience: ['Operação digital', 'Proprietário', 'Implantação'],
    functionality: 'api-access',
    requirement: ACCESS.apiAccessRead,
    whoUses: 'Quem integra o sistema com parceiros e acompanha pedidos externos.',
    dependencies: ['catalogo', 'pedidos', 'loja'],
    connectsTo: ['pedidos', 'estoque'],
    teaches: [
      'Como a central de integrações conversa com pedido interno',
      'Como reprocessar falhas e reimportar pedidos',
      'Quais partes já estão prontas e quais ainda dependem de credencial/homologação',
    ],
    risks: [
      'Catálogo inconsistente dificulta matching e importação',
      'Confundir falha de credencial com falha operacional do pedido',
    ],
    operations: [
      {
        title: 'Operar pedido do iFood',
        purpose: 'Acompanhar a fila externa, reenfileirar falhas e importar para o fluxo interno.',
        when: 'Durante operação omnichannel ou testes de integração.',
        actor: 'Operação digital',
        route: '/pedidos-ifood',
        permissionLabel: 'api-access:read/update',
        requirements: ['Integração ativa', 'Merchant sincronizado', 'Catálogo minimamente compatível'],
        effects: ['Atualiza timeline, ações externas e vínculo com pedido interno'],
        commonErrors: ['Pedido sem matching de item', 'Evento com falha não reprocessado', 'Credencial inválida'],
        goodPractices: ['Acompanhar alerta operacional e fila de erros antes de abrir suporte'],
      },
    ],
  },
  {
    id: 'contador',
    name: 'Contador e pendências fiscais',
    summary: 'Apresenta o fluxo segregado do escritório contábil e a troca estruturada de pendências com a empresa.',
    status: 'implemented',
    route: '/configuracoes/contadores',
    audience: ['Proprietário', 'Contador'],
    functionality: 'accounting-access',
    requirement: ACCESS.accountingAccessRead,
    whoUses: 'Proprietário da empresa e escritório contábil.',
    dependencies: ['empresa'],
    connectsTo: ['fiscal', 'financeiro', 'clientes', 'catalogo'],
    teaches: [
      'Como aprovar o escritório de contabilidade',
      'Como a central de pendências organiza pedidos de documentação',
      'Quais dados fiscais o contador pode complementar',
    ],
    risks: [
      'Aprovar acesso sem revisar escopo desejado',
      'Trabalhar por e-mail fora da central perde rastreabilidade',
    ],
    operations: [
      {
        title: 'Aprovar acesso do contador',
        purpose: 'Habilitar o escritório para operar dados autorizados da empresa.',
        when: 'Após validar o pedido de acesso e o vínculo correto.',
        actor: 'Proprietário',
        route: '/configuracoes/contadores',
        permissionLabel: 'accounting-access:approve',
        requirements: ['Solicitação de acesso existente', 'Empresa correta em contexto'],
        effects: ['Libera relatórios, módulos fiscais e central de pendências para o escritório'],
        commonErrors: ['Aprovar empresa errada', 'Não orientar o contador a usar a central de mensagens'],
        goodPractices: ['Manter comunicação fiscal dentro da central para preservar histórico'],
      },
    ],
  },
]

const TRAINING_TRACKS: TrainingTrack[] = [
  {
    id: 'implantacao',
    title: 'Implantação guiada da empresa',
    audience: 'owner',
    description: 'Sequência ideal para tirar a empresa do zero e chegar à primeira venda sem suporte constante.',
    modules: ['empresa', 'usuarios', 'catalogo', 'estoque', 'clientes', 'loja', 'pedidos'],
    outcome: 'Empresa pronta para operar com catálogo, equipe, loja e pedidos básicos.',
  },
  {
    id: 'operacao',
    title: 'Operação de pedidos sem erro',
    audience: 'operations',
    description: 'Treinamento focado em criação, revisão, pagamento, entrega e correção operacional.',
    modules: ['clientes', 'catalogo', 'estoque', 'pedidos', 'delivery'],
    outcome: 'Equipe entende dependências do pedido e evita erros de fluxo e saldo.',
  },
  {
    id: 'financeiro',
    title: 'Conciliação e visão financeira',
    audience: 'finance',
    description: 'Treinamento focado em pagamento de pedidos, divergências financeiras e leitura operacional do caixa.',
    modules: ['pedidos', 'financeiro', 'empresa'],
    outcome: 'Financeiro entende os eventos do pedido e reduz retrabalho em conciliação.',
  },
  {
    id: 'fiscal-contador',
    title: 'Fiscal interno e relacionamento com contador',
    audience: 'support',
    description: 'Trilha para quem prepara documento interno, organiza regras fiscais e interage com o escritório.',
    modules: ['empresa', 'catalogo', 'pedidos', 'fiscal', 'contador'],
    outcome: 'Operação fiscal entende o que já existe, o que é manual e como trabalhar com o contador.',
  },
]

const TRAINING_QUIZZES: Record<string, TrainingQuiz> = {
  empresa: {
    question: 'Antes de abrir a operação da empresa, qual validação reduz mais risco de bloqueio funcional?',
    options: [
      'Conferir plano, dados básicos da empresa e contexto ativo',
      'Abrir pedidos diretamente para ver o que falta depois',
      'Cadastrar apenas usuários e deixar o resto para depois',
    ],
    correctIndex: 0,
    explanation: 'Plano e dados-base da empresa afetam permissões, loja, fiscal e cobrança desde o início da implantação.',
  },
  usuarios: {
    question: 'O que normalmente libera a operação real de um colaborador dentro da empresa?',
    options: [
      'Somente o login do usuário',
      'Vínculo com a empresa e perfil com permissões adequadas',
      'Apenas estar no grupo global administrators',
    ],
    correctIndex: 1,
    explanation: 'No PegaTicket, login não basta: a operação tenant-scoped depende do vínculo com a empresa e do perfil correto.',
  },
  catalogo: {
    question: 'Qual prática ajuda mais o catálogo a funcionar bem em estoque, pedido e integração externa?',
    options: [
      'Manter SKU estável e cadastro coerente desde a origem',
      'Trocar SKU sempre que houver promoção',
      'Criar produto sem categoria e revisar depois',
    ],
    correctIndex: 0,
    explanation: 'SKU consistente reduz retrabalho e melhora matching de pedido, estoque e marketplace.',
  },
  estoque: {
    question: 'Ao montar um pedido, o que reduz mais o risco de prometer algo que a operação não consegue cumprir?',
    options: ['Conferir disponibilidade e regra de bloqueio por estoque', 'Ignorar saldo e revisar só depois do pagamento', 'Assumir que todo item publicado sempre está disponível'],
    correctIndex: 0,
    explanation: 'Nesta fase, disponibilidade e reserva continuam sendo a base para não vender além do que a operação consegue entregar.',
  },
  clientes: {
    question: 'Por que telefone e endereço do cliente importam já na criação do pedido?',
    options: [
      'Porque influenciam contato, taxa, logística e histórico operacional',
      'Porque substituem a necessidade do pedido',
      'Porque só servem para marketing depois da venda',
    ],
    correctIndex: 0,
    explanation: 'No PegaTicket, o contexto do cliente afeta cobrança, logística, rastreio e a qualidade da operação desde o começo do pedido.',
  },
  pedidos: {
    question: 'Ao criar um pedido interno, qual impacto costuma acontecer de forma imediata?',
    options: [
      'Reserva de estoque e criação do contexto financeiro do pedido',
      'Fechamento automático da assinatura da empresa',
      'Liberação automática do contador externo',
    ],
    correctIndex: 0,
    explanation: 'Pedido é um agregado central: ele conversa com estoque, financeiro, cliente e depois pode conversar com fiscal e analytics.',
  },
  loja: {
    question: 'Qual combinação é mais importante antes de divulgar a loja online?',
    options: [
      'Endereço, horários, taxa e produtos válidos',
      'Somente o logotipo',
      'Apenas um cupom promocional',
    ],
    correctIndex: 0,
    explanation: 'A loja depende de uma operação mínima coerente: catálogo, localização, horários e política de entrega.',
  },
  financeiro: {
    question: 'Quando faz mais sentido abrir a conciliação financeira?',
    options: [
      'Quando existe divergência entre pedido, pagamento, reembolso ou evento financeiro',
      'Somente depois de fechar o mês e sem olhar o pedido',
      'Apenas quando o cliente reclama, sem revisar o provedor',
    ],
    correctIndex: 0,
    explanation: 'A conciliação existe para ligar o evento financeiro ao pedido e separar erro de operação de erro de integração.',
  },
  fiscal: {
    question: 'No estado atual do produto, o preparo fiscal do pedido representa:',
    options: [
      'Sempre emissão oficial concluída',
      'Rascunho fiscal interno estruturado, ainda não equivalendo por padrão à emissão oficial',
      'Somente um relatório sem valor operacional',
    ],
    correctIndex: 1,
    explanation: 'Hoje o fluxo já é útil e estruturado, mas a documentação viva precisa deixar claro quando ele ainda é interno/manual.',
  },
  delivery: {
    question: 'Quando um pedido do iFood falha na materialização, qual reação está mais alinhada à operação do PegaTicket?',
    options: [
      'Ignorar e esperar que resolva sozinho',
      'Acompanhar a fila operacional, reprocessar evento e revisar matching/catálogo',
      'Excluir a integração inteira imediatamente',
    ],
    correctIndex: 1,
    explanation: 'A central operacional do iFood já foi criada exatamente para isso: separar falha operacional de falha estrutural.',
  },
  contador: {
    question: 'Qual comportamento preserva melhor rastreabilidade com o escritório contábil?',
    options: [
      'Tratar tudo por e-mail externo',
      'Usar a central de pendências e aprovar o vínculo correto',
      'Dar acesso amplo a qualquer escritório sem revisão',
    ],
    correctIndex: 1,
    explanation: 'A central de pendências existe para manter histórico, contexto e responsabilidade por empresa.',
  },
}

function statusVisual(status: ModuleStatus) {
  if (status === 'implemented') return { label: 'Disponível', color: 'success' as const }
  if (status === 'partial') return { label: 'Parcial', color: 'warning' as const }
  return { label: 'Planejado', color: 'default' as const }
}

function trackAudienceVisual(audience: TrackAudience) {
  if (audience === 'owner') return { label: 'Proprietário', tone: 'var(--pt-primary)' }
  if (audience === 'operations') return { label: 'Operação', tone: 'var(--pt-accent)' }
  if (audience === 'finance') return { label: 'Financeiro', tone: 'var(--pt-secondary)' }
  if (audience === 'catalog') return { label: 'Catálogo', tone: 'var(--pt-info)' }
  if (audience === 'delivery') return { label: 'Delivery', tone: 'var(--pt-warning)' }
  return { label: 'Suporte', tone: 'var(--pt-text)' }
}

function initials(name: string | null | undefined) {
  if (!name) return 'MK'
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

const clampTextSx = {
  minWidth: 0,
  overflowWrap: 'anywhere',
  wordBreak: 'break-word',
} as const

export function TrainingCenterPage() {
  const { activeTenant, accessProfile } = useAuth()
  const { profile } = useUserProfile()
  const { can } = useAccessControl()
  const { checklist, isLoading: isChecklistLoading } = useOnboardingChecklist()

  const visibleModules = useMemo(() => {
    return TRAINING_MODULES.filter((module) => !module.requirement || can(module.requirement))
  }, [can])

  const onboardingPercent = checklist ? Math.round((checklist.completed / Math.max(checklist.total, 1)) * 100) : null

  const recommendedTrackId = useMemo(() => {
    if (checklist && !checklist.has_product) return 'implantacao'
    if (checklist && !checklist.has_first_order) return 'operacao'
    if (activeTenant?.plan_slug === 'diamante') return 'fiscal-contador'
    return 'financeiro'
  }, [checklist, activeTenant?.plan_slug])

  const accessibleFunctionalitySet = useMemo(() => new Set(accessProfile?.tenant_functionalities ?? []), [accessProfile?.tenant_functionalities])
  const [progressState, setProgressState] = useState<TrainingCenterStoredProgress>(trainingCenterEmptyProgress())
  const [selectedModuleId, setSelectedModuleId] = useState<string>(TRAINING_MODULES[0].id)
  const [selectedTrackId, setSelectedTrackId] = useState<string>(TRAINING_TRACKS[0].id)
  const [quizSelection, setQuizSelection] = useState<Record<string, number>>({})

  useEffect(() => {
    const next = readTrainingCenterProgress(profile?.uuid, activeTenant?.tenant_uuid)
    setProgressState(next)
    setSelectedModuleId(next.lastModuleId ?? visibleModules[0]?.id ?? TRAINING_MODULES[0].id)
    setSelectedTrackId(next.lastTrackId ?? recommendedTrackId)
    setQuizSelection({})
  }, [profile?.uuid, activeTenant?.tenant_uuid, recommendedTrackId, visibleModules])

  const unlockedTrackIds = useMemo(() => {
    const unlocked = new Set<string>(['implantacao', ...progressState.unlockedTrackIds])

    if (checklist?.has_product || progressState.completedModuleIds.includes('catalogo')) unlocked.add('operacao')
    if (checklist?.has_first_order || progressState.completedModuleIds.includes('pedidos')) unlocked.add('financeiro')
    if ((activeTenant?.plan_slug === 'diamante' || accessibleFunctionalitySet.has('accounting-access')) && progressState.completedModuleIds.includes('empresa')) {
      unlocked.add('fiscal-contador')
    }

    return unlocked
  }, [accessibleFunctionalitySet, activeTenant?.plan_slug, checklist?.has_first_order, checklist?.has_product, progressState.completedModuleIds, progressState.unlockedTrackIds])

  useEffect(() => {
    if (!unlockedTrackIds.has(selectedTrackId)) {
      setSelectedTrackId(recommendedTrackId)
    }
  }, [recommendedTrackId, selectedTrackId, unlockedTrackIds])

  const selectedModule = visibleModules.find((module) => module.id === selectedModuleId) ?? visibleModules[0] ?? TRAINING_MODULES[0]
  const selectedTrack = TRAINING_TRACKS.find((track) => track.id === selectedTrackId) ?? TRAINING_TRACKS[0]

  const trackModules = selectedTrack.modules
    .map((id) => TRAINING_MODULES.find((module) => module.id === id))
    .filter((module): module is TrainingModule => Boolean(module))
    .filter((module) => visibleModules.some((visibleModule) => visibleModule.id === module.id))

  const selectedQuiz = TRAINING_QUIZZES[selectedModule.id]
  const selectedQuizChoice = quizSelection[selectedModule.id]
  const selectedQuizCorrect = selectedQuiz && selectedQuizChoice === selectedQuiz.correctIndex
  const recommendedTrack = TRAINING_TRACKS.find((track) => track.id === recommendedTrackId) ?? TRAINING_TRACKS[0]
  const activeTrackCompletedCount = trackModules.filter((module) => progressState.completedModuleIds.includes(module.id)).length
  const activeTrackProgress = trackModules.length > 0 ? Math.round((activeTrackCompletedCount / trackModules.length) * 100) : 0

  const progress = useMemo(() => {
    const total = visibleModules.length
    const implemented = visibleModules.filter((module) => module.status === 'implemented').length
    const partial = visibleModules.filter((module) => module.status === 'partial').length
    const completed = progressState.completedModuleIds.filter((id) => visibleModules.some((module) => module.id === id)).length
    const started = progressState.startedModuleIds.filter((id) => visibleModules.some((module) => module.id === id)).length
    const quizHits = Object.entries(progressState.quizCorrectByModule).filter(([id, hits]) => visibleModules.some((module) => module.id === id) && hits > 0).length
    const weighted = total === 0 ? 0 : Math.round(Math.min(100, ((completed * 1.1 + started * 0.35 + quizHits * 0.45 + partial * 0.25) / total) * 100))
    return { total, implemented, partial, completed, started, quizHits, weighted }
  }, [progressState.completedModuleIds, progressState.quizCorrectByModule, progressState.startedModuleIds, visibleModules])

  function persistProgress(updater: (current: TrainingCenterStoredProgress) => TrainingCenterStoredProgress) {
    setProgressState((current) => {
      const next = updater(current)
      writeTrainingCenterProgress(profile?.uuid, activeTenant?.tenant_uuid, next)
      return next
    })
  }

  function openModule(moduleId: string) {
    setSelectedModuleId(moduleId)
    persistProgress((current) => ({
      ...current,
      startedModuleIds: current.startedModuleIds.includes(moduleId) ? current.startedModuleIds : [...current.startedModuleIds, moduleId],
      lastModuleId: moduleId,
    }))
  }

  function selectTrack(trackId: string) {
    if (!unlockedTrackIds.has(trackId)) return
    setSelectedTrackId(trackId)
    persistProgress((current) => ({ ...current, lastTrackId: trackId }))
  }

  function markModuleCompleted(moduleId: string) {
    persistProgress((current) => ({
      ...current,
      startedModuleIds: current.startedModuleIds.includes(moduleId) ? current.startedModuleIds : [...current.startedModuleIds, moduleId],
      completedModuleIds: current.completedModuleIds.includes(moduleId) ? current.completedModuleIds : [...current.completedModuleIds, moduleId],
      lastModuleId: moduleId,
    }))
  }

  function resetProgress() {
    const next = trainingCenterEmptyProgress()
    writeTrainingCenterProgress(profile?.uuid, activeTenant?.tenant_uuid, next)
    setProgressState(next)
    setSelectedTrackId(recommendedTrackId)
    setSelectedModuleId(visibleModules[0]?.id ?? TRAINING_MODULES[0].id)
    setQuizSelection({})
  }

  function submitQuiz(moduleId: string) {
    const quiz = TRAINING_QUIZZES[moduleId]
    const answer = quizSelection[moduleId]
    if (!quiz || answer === undefined) return

    persistProgress((current) => ({
      ...current,
      startedModuleIds: current.startedModuleIds.includes(moduleId) ? current.startedModuleIds : [...current.startedModuleIds, moduleId],
      completedModuleIds: answer === quiz.correctIndex && !current.completedModuleIds.includes(moduleId) ? [...current.completedModuleIds, moduleId] : current.completedModuleIds,
      quizCorrectByModule: {
        ...current.quizCorrectByModule,
        [moduleId]: answer === quiz.correctIndex ? (current.quizCorrectByModule[moduleId] ?? 0) + 1 : current.quizCorrectByModule[moduleId] ?? 0,
      },
      unlockedTrackIds: answer === quiz.correctIndex ? Array.from(new Set([...current.unlockedTrackIds, selectedTrackId])) : current.unlockedTrackIds,
      lastModuleId: moduleId,
    }))
  }

  return (
    <Box
      sx={{
        ...PAGE_CONTAINER_SX,
        maxWidth: 1480,
        position: 'relative',
        pb: 2,
        '@keyframes pt-training-float': {
          '0%, 100%': { transform: 'translate3d(0, 0, 0)' },
          '50%': { transform: 'translate3d(0, -14px, 0)' },
        },
        '@keyframes pt-training-glow': {
          '0%, 100%': { opacity: 0.28, transform: 'scale(1)' },
          '50%': { opacity: 0.5, transform: 'scale(1.08)' },
        },
        '@keyframes pt-training-shimmer': {
          '0%': { transform: 'translateX(-35%)' },
          '100%': { transform: 'translateX(135%)' },
        },
        '@media (prefers-reduced-motion: reduce)': {
          '& *, & *::before, & *::after': {
            animation: 'none !important',
            transition: 'none !important',
          },
        },
      }}
    >
      <Box
        aria-hidden="true"
        sx={{
          position: 'absolute',
          inset: 0,
          overflow: 'hidden',
          pointerEvents: 'none',
        }}
      >
        <Box
          sx={{
            position: 'absolute',
            top: 34,
            right: { xs: -120, md: -30 },
            width: { xs: 220, md: 320 },
            height: { xs: 220, md: 320 },
            borderRadius: '50%',
            background: 'color-mix(in srgb, var(--pt-accent) 18%, transparent)',
            filter: 'blur(60px)',
            animation: 'pt-training-glow 16s ease-in-out infinite',
          }}
        />
        <Box
          sx={{
            position: 'absolute',
            top: 260,
            left: -100,
            width: { xs: 180, md: 260 },
            height: { xs: 180, md: 260 },
            borderRadius: '50%',
            background: 'color-mix(in srgb, var(--pt-primary) 14%, transparent)',
            filter: 'blur(56px)',
            animation: 'pt-training-float 18s ease-in-out infinite',
          }}
        />
      </Box>

      <PageHeader
        title="Central de Treinamento"
        subtitle="Aprenda o PegaTicket com uma jornada visual mais guiada, por contexto real de empresa, operação e módulos acessíveis."
        accent
      />

      <Card
        sx={{
          position: 'relative',
          overflow: 'hidden',
          mb: 2,
          color: '#F8FAFC',
          border: 'none',
          background:
            'linear-gradient(138deg, color-mix(in srgb, var(--pt-primary) 94%, #ffffff 6%) 0%, color-mix(in srgb, var(--pt-secondary) 78%, #08101d 22%) 56%, color-mix(in srgb, var(--pt-accent) 38%, #08101d 62%) 100%)',
          boxShadow: 'var(--pt-shadow-md)',
        }}
      >
        <Box
          aria-hidden="true"
          sx={{
            position: 'absolute',
            inset: 0,
            backgroundImage:
              'radial-gradient(color-mix(in srgb, #FFFFFF 12%, transparent) 1px, transparent 1px)',
            backgroundSize: '20px 20px',
            opacity: 0.34,
            maskImage: 'linear-gradient(180deg, transparent 0%, black 20%, black 82%, transparent 100%)',
          }}
        />
        <Box
          aria-hidden="true"
          sx={{
            position: 'absolute',
            top: -90,
            right: -40,
            width: 280,
            height: 280,
            borderRadius: '50%',
            background: 'color-mix(in srgb, #FFFFFF 15%, transparent)',
            filter: 'blur(30px)',
            animation: 'pt-training-float 15s ease-in-out infinite',
          }}
        />
        <CardContent sx={{ position: 'relative', p: { xs: 2, md: 3 } }}>
          <Box
            sx={{
              display: 'grid',
              gridTemplateColumns: { xs: '1fr', xl: '1.35fr 0.8fr' },
              gap: 2,
              alignItems: 'stretch',
            }}
          >
            <Stack spacing={2.25}>
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                <Box
                  sx={{
                    width: 52,
                    height: 52,
                    borderRadius: 3,
                    display: 'grid',
                    placeItems: 'center',
                    bgcolor: 'rgba(255,255,255,0.14)',
                    border: '1px solid rgba(255,255,255,0.18)',
                    backdropFilter: 'blur(8px)',
                  }}
                >
                  <Typography sx={{ fontWeight: 800, fontSize: 18 }}>{initials(profile?.name)}</Typography>
                </Box>
                <Box sx={{ ...clampTextSx, flex: 1 }}>
                  <Typography sx={{ ...clampTextSx, fontSize: 12.5, opacity: 0.78, textTransform: 'uppercase', letterSpacing: '0.08em' }}>
                    Sessão ativa de aprendizado
                  </Typography>
                  <Typography sx={{ ...clampTextSx, fontSize: { xs: 26, md: 34 }, fontWeight: 800, lineHeight: 1.04 }}>
                    Olá, {profile?.name?.split(' ')[0] ?? 'time'}.
                  </Typography>
                </Box>
              </Stack>

              <Box sx={clampTextSx}>
                <Typography sx={{ ...clampTextSx, maxWidth: 760, fontSize: { xs: 16, md: 18 }, lineHeight: 1.55, opacity: 0.96 }}>
                  A central agora funciona como uma base guiada de implantação e operação: uma trilha visível, um módulo em foco e
                  blocos que se movem para deixar clara a próxima ação do usuário.
                </Typography>
                <Typography sx={{ ...clampTextSx, mt: 1.5, maxWidth: 720, fontSize: 14, opacity: 0.8 }}>
                  O foco atual está em <strong>{selectedTrack.title.toLowerCase()}</strong>, com conteúdo adaptado ao acesso da empresa
                  e ao estágio real de implantação.
                </Typography>
              </Box>

              <Box
                sx={{
                  display: 'grid',
                  gridTemplateColumns: { xs: 'repeat(2, minmax(0, 1fr))', md: 'repeat(4, minmax(0, 1fr))' },
                  gap: 1.25,
                }}
              >
                {[
                  { label: 'Progresso geral', value: `${progress.weighted}%`, detail: `${progress.completed} concluídos` },
                  { label: 'Trilha ativa', value: `${activeTrackCompletedCount}/${trackModules.length || 0}`, detail: 'módulos da jornada' },
                  { label: 'Miniavaliações', value: String(progress.quizHits), detail: 'módulos com acerto' },
                  { label: 'Próxima trilha', value: recommendedTrack.title, detail: 'sugerida pelo sistema' },
                ].map((item, index) => (
                  <Paper
                    key={item.label}
                    elevation={0}
                    sx={{
                      p: 1.5,
                      minHeight: 112,
                      borderRadius: 3,
                      color: 'inherit',
                      bgcolor: 'rgba(255,255,255,0.09)',
                      border: '1px solid rgba(255,255,255,0.12)',
                      backdropFilter: 'blur(10px)',
                      animation: `pt-training-float ${12 + index * 2}s ease-in-out infinite`,
                    }}
                  >
                    <Typography sx={{ ...clampTextSx, fontSize: 12.5, opacity: 0.78 }}>{item.label}</Typography>
                    <Typography sx={{ ...clampTextSx, mt: 0.5, fontSize: { xs: 24, md: 28 }, fontWeight: 800, lineHeight: 1.12 }}>{item.value}</Typography>
                    <Typography sx={{ ...clampTextSx, mt: 0.5, fontSize: 12.5, opacity: 0.8 }}>{item.detail}</Typography>
                  </Paper>
                ))}
              </Box>
            </Stack>

            <Paper
              elevation={0}
              sx={{
                position: 'relative',
                overflow: 'hidden',
                p: { xs: 2, md: 2.25 },
                borderRadius: 4,
                bgcolor: 'rgba(4, 10, 21, 0.28)',
                color: 'inherit',
                border: '1px solid rgba(255,255,255,0.12)',
                backdropFilter: 'blur(12px)',
              }}
            >
              <Box
                aria-hidden="true"
                sx={{
                  position: 'absolute',
                  inset: 0,
                  background:
                    'linear-gradient(120deg, transparent 0%, rgba(255,255,255,0.1) 50%, transparent 100%)',
                  transform: 'translateX(-35%)',
                  animation: 'pt-training-shimmer 11s linear infinite',
                }}
              />
              <Stack spacing={2} sx={{ position: 'relative' }}>
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                  <PsychologyAltOutlinedIcon sx={{ color: 'var(--pt-accent)' }} />
                  <Typography sx={{ fontSize: 17, fontWeight: 800 }}>Cockpit da sessão</Typography>
                  <Box sx={{ flex: 1 }} />
                  <IconButton size="small" onClick={resetProgress} aria-label="Reiniciar progresso da central" sx={{ color: 'inherit' }}>
                    <RestartAltOutlinedIcon fontSize="small" />
                  </IconButton>
                </Stack>

                <Stack spacing={1} sx={clampTextSx}>
                  <Typography sx={{ ...clampTextSx, fontSize: 12.5, opacity: 0.74 }}>Empresa ativa</Typography>
                  <Typography sx={{ ...clampTextSx, fontSize: 22, fontWeight: 800 }}>{activeTenant?.tenant_name ?? 'Sem empresa ativa'}</Typography>
                  <Stack direction="row" spacing={1} sx={{ flexWrap: 'wrap', rowGap: 0.75 }}>
                    {activeTenant?.plan_name ? (
                      <Chip size="small" label={activeTenant.plan_name} sx={{ bgcolor: 'rgba(255,255,255,0.12)', color: 'inherit' }} />
                    ) : null}
                    {accessProfile?.is_tenant_owner ? (
                      <Chip size="small" label="Proprietário" sx={{ bgcolor: 'rgba(255,255,255,0.12)', color: 'inherit' }} />
                    ) : null}
                  </Stack>
                </Stack>

                <Paper elevation={0} sx={{ p: 1.5, borderRadius: 3, bgcolor: 'rgba(255,255,255,0.08)', border: '1px solid rgba(255,255,255,0.1)' }}>
                  <Typography sx={{ ...clampTextSx, fontSize: 12.5, opacity: 0.74 }}>Seu próximo bloco recomendado</Typography>
                  <Typography sx={{ ...clampTextSx, mt: 0.5, fontSize: 18, fontWeight: 800 }}>{recommendedTrack.title}</Typography>
                  <Typography sx={{ ...clampTextSx, mt: 0.75, fontSize: 13, opacity: 0.82 }}>{recommendedTrack.outcome}</Typography>
                </Paper>

                <Box>
                  <Stack direction="row" spacing={1} sx={{ justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
                    <Typography sx={{ fontSize: 13, opacity: 0.78 }}>Cobertura da central neste contexto</Typography>
                    <Typography sx={{ fontSize: 13, fontWeight: 700 }}>{progress.weighted}%</Typography>
                  </Stack>
                  <LinearProgress
                    variant="determinate"
                    value={progress.weighted}
                    sx={{
                      height: 10,
                      borderRadius: 999,
                      bgcolor: 'rgba(255,255,255,0.12)',
                      '& .MuiLinearProgress-bar': {
                        borderRadius: 999,
                        background: 'linear-gradient(90deg, #FFFFFF 0%, var(--pt-accent) 100%)',
                      },
                    }}
                  />
                </Box>
              </Stack>
            </Paper>
          </Box>
        </CardContent>
      </Card>

      <Box
        sx={{
          display: 'grid',
          gridTemplateColumns: { xs: '1fr', lg: '360px minmax(0, 1fr)' },
          gap: 2,
          alignItems: 'start',
        }}
      >
        <Stack spacing={2} sx={{ position: { lg: 'sticky' }, top: { lg: 20 } }}>
          <Card>
            <CardContent sx={{ p: { xs: 2, md: 2.25 } }}>
              <Stack spacing={1.5}>
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                  <SchoolOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
                  <Typography sx={{ fontSize: 17, fontWeight: 800 }}>Trilhas guiadas</Typography>
                </Stack>
                <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
                  Uma única trilha fica em destaque por vez. As bloqueadas continuam visíveis, mas sem confundir a navegação principal.
                </Typography>

                <Stack spacing={1}>
                  {TRAINING_TRACKS.map((track) => {
                    const selected = selectedTrackId === track.id
                    const recommended = recommendedTrackId === track.id
                    const unlocked = unlockedTrackIds.has(track.id)
                    const audience = trackAudienceVisual(track.audience)

                    return (
                      <Paper
                        key={track.id}
                        variant="outlined"
                        onClick={() => selectTrack(track.id)}
                        sx={{
                          p: 1.5,
                          position: 'relative',
                          overflow: 'hidden',
                          cursor: unlocked ? 'pointer' : 'not-allowed',
                          borderRadius: 'var(--pt-radius-xl)',
                          borderColor: selected ? 'var(--pt-primary)' : 'var(--pt-border)',
                          background: selected
                            ? 'color-mix(in srgb, var(--pt-primary) 7%, var(--pt-surface))'
                            : 'var(--pt-surface)',
                          opacity: unlocked ? 1 : 0.56,
                          transition: 'transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease',
                          boxShadow: selected ? 'var(--pt-shadow-md)' : 'none',
                          '&:hover': unlocked ? { transform: 'translateY(-2px)', borderColor: 'var(--pt-primary)' } : undefined,
                        }}
                      >
                        <Stack spacing={1} sx={{ position: 'relative', ...clampTextSx }}>
                          <Stack direction="row" spacing={1} sx={{ justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', rowGap: 1 }}>
                            <Box sx={{ ...clampTextSx, flex: 1, minWidth: 180 }}>
                              <Typography sx={{ ...clampTextSx, fontSize: 15, fontWeight: 800, mb: 0.25 }}>{track.title}</Typography>
                              <Chip
                                size="small"
                                label={audience.label}
                                sx={{
                                  height: 24,
                                  bgcolor: `color-mix(in srgb, ${audience.tone} 10%, var(--pt-surface))`,
                                  color: audience.tone,
                                  border: `1px solid color-mix(in srgb, ${audience.tone} 22%, transparent)`,
                                }}
                              />
                            </Box>
                            <Stack direction="row" spacing={0.75} sx={{ flexWrap: 'wrap', justifyContent: 'flex-end', rowGap: 0.75, maxWidth: '100%' }}>
                              {!unlocked ? <Chip size="small" icon={<LockOutlinedIcon />} variant="outlined" color="warning" label="Bloqueada" /> : null}
                              {recommended ? <Chip size="small" color="primary" label="Sugestão" /> : null}
                            </Stack>
                          </Stack>
                          <Typography sx={{ ...clampTextSx, fontSize: 13.25, color: 'var(--pt-muted)' }}>{track.description}</Typography>
                          <Typography sx={{ ...clampTextSx, fontSize: 12.75, fontWeight: 700 }}>{track.outcome}</Typography>
                        </Stack>
                      </Paper>
                    )
                  })}
                </Stack>
              </Stack>
            </CardContent>
          </Card>

          <Card>
            <CardContent sx={{ p: { xs: 2, md: 2.25 } }}>
              <Stack spacing={1.5}>
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                  <TrendingUpOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
                  <Typography sx={{ fontSize: 17, fontWeight: 800 }}>Prontidão operacional</Typography>
                </Stack>

                <Paper variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: 1.5 }}>
                  <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
                    <Box sx={{ position: 'relative', width: 74, height: 74, flexShrink: 0 }}>
                      <CircularProgress variant="determinate" value={100} size={74} sx={{ color: 'var(--pt-border)', position: 'absolute', inset: 0 }} />
                      <CircularProgress variant="determinate" value={activeTrackProgress} size={74} sx={{ color: 'var(--pt-primary)', position: 'absolute', inset: 0 }} />
                      <Box sx={{ position: 'absolute', inset: 0, display: 'grid', placeItems: 'center' }}>
                        <Typography sx={{ fontWeight: 800 }}>{activeTrackProgress}%</Typography>
                      </Box>
                    </Box>
                    <Box sx={{ ...clampTextSx, flex: 1 }}>
                      <Typography sx={{ ...clampTextSx, fontSize: 14.5, fontWeight: 700 }}>Jornada ativa</Typography>
                      <Typography sx={{ ...clampTextSx, fontSize: 13.25, color: 'var(--pt-muted)' }}>
                        {activeTrackCompletedCount} de {trackModules.length || 0} módulos concluídos na trilha atual.
                      </Typography>
                    </Box>
                  </Stack>
                </Paper>

                <Paper variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: 1.5 }}>
                  <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 1 }}>
                    <TaskAltOutlinedIcon sx={{ color: 'var(--pt-success)' }} />
                    <Typography sx={{ fontSize: 14.5, fontWeight: 700 }}>Checklist de implantação real</Typography>
                  </Stack>

                  {isChecklistLoading ? (
                    <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>Carregando checklist…</Typography>
                  ) : checklist ? (
                    <Stack spacing={1}>
                      <Stack direction="row" spacing={1} sx={{ justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', rowGap: 0.5 }}>
                        <Typography sx={{ ...clampTextSx, fontSize: 13.25, color: 'var(--pt-muted)' }}>
                          {checklist.completed} de {checklist.total} marcos concluídos
                        </Typography>
                        <Typography sx={{ fontSize: 13.25, fontWeight: 700 }}>{onboardingPercent ?? 0}%</Typography>
                      </Stack>
                      <LinearProgress variant="determinate" value={onboardingPercent ?? 0} sx={{ height: 8, borderRadius: 999 }} />
                      {[
                        ['Produto inicial cadastrado', checklist.has_product],
                        ['Cliente inicial cadastrado', checklist.has_client],
                        ['Endereço da loja configurado', checklist.has_store_address],
                        ['Loja online configurada', checklist.storefront_configured],
                        ['Primeiro pedido concluído', checklist.has_first_order],
                      ].map(([label, done]) => (
                        <Stack key={String(label)} direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                          {done ? (
                            <CheckCircleOutlinedIcon sx={{ fontSize: 18, color: 'var(--pt-success)' }} />
                          ) : (
                            <ErrorOutlineOutlinedIcon sx={{ fontSize: 18, color: 'var(--pt-warning)' }} />
                          )}
                          <Typography sx={{ ...clampTextSx, fontSize: 13.25 }}>{label}</Typography>
                        </Stack>
                      ))}
                    </Stack>
                  ) : (
                    <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)' }}>
                      O checklist aparece quando a empresa ativa já possui contexto operacional disponível.
                    </Typography>
                  )}
                </Paper>
              </Stack>
            </CardContent>
          </Card>
        </Stack>

        <Stack spacing={2}>
          <Card sx={{ overflow: 'hidden' }}>
            <CardContent sx={{ p: { xs: 2, md: 2.5 } }}>
              <Stack spacing={1.5}>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} sx={{ justifyContent: 'space-between', alignItems: { md: 'center' } }}>
                  <Box sx={{ ...clampTextSx, flex: 1 }}>
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap', mb: 0.5 }}>
                      <HubOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
                      <Typography sx={{ ...clampTextSx, fontSize: 18, fontWeight: 800 }}>Mapa da trilha ativa</Typography>
                      <Chip size="small" color="primary" variant="outlined" label={selectedTrack.title} />
                    </Stack>
                    <Typography sx={{ ...clampTextSx, fontSize: 13.5, color: 'var(--pt-muted)' }}>{selectedTrack.description}</Typography>
                  </Box>
                  <Typography sx={{ ...clampTextSx, fontSize: 13.25, color: 'var(--pt-muted)', maxWidth: { md: 240 } }}>
                    Toque em um módulo para trazer o conteúdo ao palco principal.
                  </Typography>
                </Stack>

                <Box
                  sx={{
                    position: 'relative',
                    display: 'grid',
                    gridTemplateColumns: { xs: '1fr', md: 'repeat(2, minmax(0, 1fr))', xl: 'repeat(3, minmax(0, 1fr))' },
                    gap: 1.25,
                    '&::before': {
                      content: '""',
                      position: 'absolute',
                      left: { xs: 18, md: 22 },
                      right: { xs: 18, md: 22 },
                      top: { xs: 20, md: 24 },
                      height: 1,
                      display: { xs: 'none', xl: 'block' },
                      background: 'linear-gradient(90deg, transparent, var(--pt-border), transparent)',
                    },
                  }}
                >
                  {trackModules.map((module, index) => {
                    const visual = statusVisual(module.status)
                    const isSelected = module.id === selectedModule.id
                    const isCompleted = progressState.completedModuleIds.includes(module.id)
                    const isStarted = progressState.startedModuleIds.includes(module.id)

                    return (
                      <Paper
                        key={module.id}
                        variant="outlined"
                        onClick={() => openModule(module.id)}
                        sx={{
                          position: 'relative',
                          p: 1.5,
                          borderRadius: 'var(--pt-radius-xl)',
                          cursor: 'pointer',
                          borderColor: isSelected ? 'var(--pt-primary)' : 'var(--pt-border)',
                          background: isSelected
                            ? 'color-mix(in srgb, var(--pt-primary) 8%, var(--pt-surface))'
                            : 'var(--pt-surface)',
                          transition: 'transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease',
                          boxShadow: isSelected ? 'var(--pt-shadow-md)' : 'none',
                          '&:hover': {
                            transform: 'translateY(-3px)',
                            borderColor: 'var(--pt-primary)',
                          },
                        }}
                      >
                        <Stack spacing={1} sx={clampTextSx}>
                          <Stack direction="row" spacing={1} sx={{ justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', rowGap: 0.75 }}>
                            <Box
                              sx={{
                                width: 34,
                                height: 34,
                                borderRadius: 2.5,
                                display: 'grid',
                                placeItems: 'center',
                                bgcolor: isSelected ? 'var(--pt-primary)' : 'var(--pt-surface-soft)',
                                color: isSelected ? '#FFFFFF' : 'var(--pt-text)',
                                fontWeight: 800,
                                boxShadow: isSelected ? 'var(--pt-shadow-sm)' : 'none',
                              }}
                            >
                              {index + 1}
                            </Box>
                            <Stack direction="row" spacing={0.75} sx={{ flexWrap: 'wrap', justifyContent: 'flex-end', rowGap: 0.75, maxWidth: '100%' }}>
                              <Chip size="small" color={visual.color} label={visual.label} />
                              {isCompleted ? <Chip size="small" color="success" variant="outlined" label="Concluído" /> : null}
                              {!isCompleted && isStarted ? <Chip size="small" color="info" variant="outlined" label="Iniciado" /> : null}
                            </Stack>
                          </Stack>
                          <Typography sx={{ ...clampTextSx, fontSize: 15, fontWeight: 800 }}>{module.name}</Typography>
                          <Typography sx={{ ...clampTextSx, fontSize: 13.25, color: 'var(--pt-muted)' }}>{module.summary}</Typography>
                        </Stack>
                      </Paper>
                    )
                  })}
                </Box>
              </Stack>
            </CardContent>
          </Card>

          <Card sx={{ overflow: 'hidden' }}>
            <CardContent sx={{ p: { xs: 2, md: 2.75 } }}>
              <Stack spacing={2}>
                <Box
                  sx={{
                    position: 'relative',
                    overflow: 'hidden',
                    p: { xs: 2, md: 2.25 },
                    borderRadius: 'var(--pt-radius-xl)',
                    background: 'color-mix(in srgb, var(--pt-primary) 5%, var(--pt-surface))',
                    border: '1px solid color-mix(in srgb, var(--pt-primary) 14%, var(--pt-border))',
                  }}
                >
                  <Stack
                    direction={{ xs: 'column', xl: 'row' }}
                    spacing={2}
                    sx={{ position: 'relative', justifyContent: 'space-between', alignItems: { xl: 'center' } }}
                  >
                    <Box sx={{ ...clampTextSx, flex: 1 }}>
                      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap', mb: 0.75 }}>
                        <Typography sx={{ ...clampTextSx, fontSize: { xs: 24, md: 30 }, fontWeight: 800, lineHeight: 1.08 }}>{selectedModule.name}</Typography>
                        <Chip size="small" color={statusVisual(selectedModule.status).color} label={statusVisual(selectedModule.status).label} />
                        {progressState.completedModuleIds.includes(selectedModule.id) ? (
                          <Chip size="small" color="success" variant="outlined" label="Concluído por você" />
                        ) : progressState.startedModuleIds.includes(selectedModule.id) ? (
                          <Chip size="small" color="info" variant="outlined" label="Em andamento" />
                        ) : null}
                      </Stack>
                      <Typography sx={{ ...clampTextSx, maxWidth: 860, fontSize: 14.5, color: 'var(--pt-muted)' }}>{selectedModule.summary}</Typography>
                    </Box>
                    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} sx={{ width: { xs: '100%', xl: 'auto' }, alignSelf: { xs: 'stretch', xl: 'center' } }}>
                      <Button
                        variant="outlined"
                        startIcon={<TaskAltOutlinedIcon />}
                        onClick={() => markModuleCompleted(selectedModule.id)}
                        fullWidth
                        sx={{ minHeight: UI_SIZE.control, justifyContent: 'center', px: 2 }}
                      >
                        Marcar como concluído
                      </Button>
                      {selectedModule.route ? (
                        <Button
                          component={RouterLink}
                          to={selectedModule.route}
                          variant="contained"
                          startIcon={<OpenInNewOutlinedIcon />}
                          fullWidth
                          sx={{ minHeight: UI_SIZE.control, justifyContent: 'center', px: 2 }}
                        >
                          Abrir área real
                        </Button>
                      ) : null}
                    </Stack>
                  </Stack>
                </Box>

                <Box
                  sx={{
                    ...FORM_GRID_3_SX,
                    gridTemplateColumns: { xs: '1fr', md: 'repeat(3, minmax(0, 1fr))' },
                  }}
                >
                  {[
                    { title: 'Quem usa', value: selectedModule.whoUses },
                    { title: 'Dependências', value: selectedModule.dependencies.length > 0 ? selectedModule.dependencies.join(' • ') : 'Módulo de base' },
                    { title: 'Conecta com', value: selectedModule.connectsTo.join(' • ') },
                  ].map((item) => (
                    <Paper key={item.title} variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: 1.5, ...clampTextSx }}>
                      <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)' }}>{item.title}</Typography>
                      <Typography sx={{ ...clampTextSx, mt: 0.5, fontSize: 14.5, fontWeight: 700 }}>{item.value}</Typography>
                    </Paper>
                  ))}
                </Box>

                <Box
                  sx={{
                    display: 'grid',
                    gridTemplateColumns: { xs: '1fr', xl: '0.88fr 1.12fr' },
                    gap: 2,
                  }}
                >
                  <Stack spacing={2}>
                    <Paper variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: 1.75 }}>
                      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 1 }}>
                        <AutoStoriesOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
                        <Typography sx={{ fontSize: 16, fontWeight: 800 }}>O que este módulo ensina</Typography>
                      </Stack>
                      <Stack spacing={0.9}>
                        {selectedModule.teaches.map((item) => (
                          <Typography key={item} sx={{ ...clampTextSx, fontSize: 13.5 }}>
                            • {item}
                          </Typography>
                        ))}
                      </Stack>
                    </Paper>

                    <Paper variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: 1.75 }}>
                      <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 1 }}>
                        <WarningAmberOutlinedIcon sx={{ color: 'var(--pt-warning)' }} />
                        <Typography sx={{ fontSize: 16, fontWeight: 800 }}>Riscos e cuidados</Typography>
                      </Stack>
                      <Stack spacing={0.9}>
                        {selectedModule.risks.map((item) => (
                          <Typography key={item} sx={{ ...clampTextSx, fontSize: 13.5 }}>
                            • {item}
                          </Typography>
                        ))}
                      </Stack>
                    </Paper>
                  </Stack>

                  <Paper
                    variant="outlined"
                    sx={{
                      p: 1.75,
                      ...ELEVATED_SURFACE_SX,
                      background: 'color-mix(in srgb, var(--pt-accent) 6%, var(--pt-surface))',
                    }}
                  >
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center', mb: 1 }}>
                      <PlayCircleOutlineOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
                      <Typography sx={{ fontSize: 16, fontWeight: 800 }}>Simulação orientada</Typography>
                    </Stack>
                    <Typography sx={{ fontSize: 13.5, color: 'var(--pt-muted)', mb: 1.5 }}>
                      Em vez de um bloco confuso, a operação real agora entra em cards separados por decisão, pré-requisito, risco e efeito.
                    </Typography>

                    <Stack spacing={1.25}>
                      {selectedModule.operations.map((operation, index) => (
                        <Paper
                          key={operation.title}
                          variant="outlined"
                          sx={{
                            p: 1.5,
                            ...ELEVATED_SURFACE_SX,
                            transition: 'transform 180ms ease, box-shadow 180ms ease',
                            '&:hover': { transform: 'translateY(-2px)', boxShadow: 'var(--pt-shadow-sm)' },
                          }}
                        >
                          <Stack spacing={1.2}>
                            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} sx={{ justifyContent: 'space-between', alignItems: { md: 'flex-start' } }}>
                              <Stack direction="row" spacing={1.1} sx={{ alignItems: 'flex-start' }}>
                                <Box
                                  sx={{
                                    width: 32,
                                    height: 32,
                                    borderRadius: 2.5,
                                    display: 'grid',
                                    placeItems: 'center',
                                    bgcolor: 'color-mix(in srgb, var(--pt-primary) 12%, var(--pt-surface))',
                                    color: 'var(--pt-primary)',
                                    fontWeight: 800,
                                    flexShrink: 0,
                                  }}
                                >
                                  {index + 1}
                                </Box>
                                <Box sx={{ ...clampTextSx, flex: 1 }}>
                                  <Typography sx={{ ...clampTextSx, fontSize: 15, fontWeight: 800 }}>{operation.title}</Typography>
                                  <Typography sx={{ ...clampTextSx, fontSize: 13.25, color: 'var(--pt-muted)' }}>{operation.purpose}</Typography>
                                </Box>
                              </Stack>
                              <Stack direction="row" spacing={0.75} sx={{ flexWrap: 'wrap', rowGap: 0.75, maxWidth: '100%' }}>
                                <Chip size="small" color="primary" variant="outlined" label={operation.permissionLabel} />
                                <Chip size="small" variant="outlined" label={operation.actor} />
                              </Stack>
                            </Stack>

                            <Box
                              sx={{
                                ...FORM_GRID_2_SX,
                                gridTemplateColumns: { xs: '1fr', md: 'repeat(2, minmax(0, 1fr))' },
                              }}
                            >
                              <Paper variant="outlined" sx={{ ...SOFT_PANEL_SX, p: 1.25 }}>
                                <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', mb: 0.5 }}>Quando usar</Typography>
                                <Typography sx={{ ...clampTextSx, fontSize: 13.5 }}>{operation.when}</Typography>
                              </Paper>
                              <Paper variant="outlined" sx={{ ...SOFT_PANEL_SX, p: 1.25 }}>
                                <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', mb: 0.5 }}>Efeitos gerados</Typography>
                                <Typography sx={{ ...clampTextSx, fontSize: 13.5 }}>{operation.effects.join(' • ')}</Typography>
                              </Paper>
                            </Box>

                            <Box
                              sx={{
                                ...FORM_GRID_3_SX,
                                gridTemplateColumns: { xs: '1fr', xl: 'repeat(3, minmax(0, 1fr))' },
                              }}
                            >
                              {[
                                { title: 'Pré-requisitos', items: operation.requirements },
                                { title: 'Erros comuns', items: operation.commonErrors },
                                { title: 'Boas práticas', items: operation.goodPractices },
                              ].map((column) => (
                                <Paper key={column.title} variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: 1.25 }}>
                                  <Typography sx={{ fontSize: 12.5, color: 'var(--pt-muted)', mb: 0.75 }}>{column.title}</Typography>
                                  <Stack spacing={0.5}>
                                    {column.items.map((item) => (
                                      <Typography key={item} sx={{ ...clampTextSx, fontSize: 13 }}>
                                        • {item}
                                      </Typography>
                                    ))}
                                  </Stack>
                                </Paper>
                              ))}
                            </Box>

                            {operation.route ? (
                              <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
                                <Button
                                  component={RouterLink}
                                  to={operation.route}
                                  variant="outlined"
                                  startIcon={<BoltOutlinedIcon />}
                                  sx={{ minHeight: 42, px: 2, maxWidth: '100%' }}
                                >
                                  Ir para a operação real
                                </Button>
                              </Stack>
                            ) : null}
                          </Stack>
                        </Paper>
                      ))}
                    </Stack>
                  </Paper>
                </Box>

                {selectedQuiz ? (
                  <>
                    <Divider />
                    <Paper
                      variant="outlined"
                      sx={{
                        p: 1.75,
                        ...ELEVATED_SURFACE_SX,
                        background: 'color-mix(in srgb, var(--pt-primary) 4%, var(--pt-surface))',
                      }}
                    >
                      <Stack spacing={1.25}>
                        <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                          <QuizOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
                          <Typography sx={{ fontSize: 16, fontWeight: 800 }}>Checagem rápida de entendimento</Typography>
                          {(progressState.quizCorrectByModule[selectedModule.id] ?? 0) > 0 ? (
                            <Chip size="small" color="success" variant="outlined" label={`${progressState.quizCorrectByModule[selectedModule.id]} acerto(s)`} />
                          ) : null}
                        </Stack>
                        <Typography sx={{ ...clampTextSx, fontSize: 14.5, fontWeight: 700 }}>{selectedQuiz.question}</Typography>
                        <Box
                          sx={{
                            ...FORM_GRID_3_SX,
                            gridTemplateColumns: { xs: '1fr', md: 'repeat(3, minmax(0, 1fr))' },
                          }}
                        >
                          {selectedQuiz.options.map((option, index) => {
                            const isSelected = selectedQuizChoice === index
                            const answered = selectedQuizChoice !== undefined
                            const correct = selectedQuiz.correctIndex === index

                            return (
                              <Paper
                                key={option}
                                variant="outlined"
                                onClick={() => setQuizSelection((current) => ({ ...current, [selectedModule.id]: index }))}
                                sx={{
                                  p: 1.35,
                                  cursor: 'pointer',
                                  ...ELEVATED_SURFACE_SX,
                                  borderColor: isSelected
                                    ? 'var(--pt-primary)'
                                    : answered && correct
                                      ? 'var(--pt-success)'
                                      : 'var(--pt-border)',
                                  bgcolor: isSelected
                                    ? 'color-mix(in srgb, var(--pt-primary) 8%, var(--pt-surface))'
                                    : answered && correct
                                      ? 'color-mix(in srgb, var(--pt-success) 10%, var(--pt-surface))'
                                      : 'var(--pt-surface)',
                                  transition: 'transform 180ms ease, border-color 180ms ease',
                                  '&:hover': { transform: 'translateY(-2px)', borderColor: 'var(--pt-primary)' },
                                }}
                              >
                                <Typography sx={{ ...clampTextSx, fontSize: 13.5, fontWeight: isSelected ? 700 : 500 }}>{option}</Typography>
                              </Paper>
                            )
                          })}
                        </Box>
                        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} sx={{ justifyContent: 'space-between', alignItems: { md: 'center' } }}>
                          <Typography sx={{ ...clampTextSx, fontSize: 13, color: 'var(--pt-muted)' }}>
                            Acertos reforçam o progresso local e ajudam a desbloquear novas trilhas visíveis.
                          </Typography>
                          <Button
                            variant="contained"
                            onClick={() => submitQuiz(selectedModule.id)}
                            disabled={selectedQuizChoice === undefined}
                            sx={{ minHeight: UI_SIZE.control, px: 2, width: { xs: '100%', md: 'auto' } }}
                          >
                            Validar resposta
                          </Button>
                        </Stack>
                        {selectedQuizChoice !== undefined ? (
                          <Alert severity={selectedQuizCorrect ? 'success' : 'warning'} variant="outlined">
                            {selectedQuizCorrect ? 'Resposta correta. ' : 'Resposta ainda não ideal. '}
                            {selectedQuiz.explanation}
                          </Alert>
                        ) : null}
                      </Stack>
                    </Paper>
                  </>
                ) : null}
              </Stack>
            </CardContent>
          </Card>

          <Card>
            <CardContent sx={{ p: { xs: 2, md: 2.5 } }}>
              <Stack spacing={1.5}>
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
                  <MilitaryTechOutlinedIcon sx={{ color: 'var(--pt-primary)' }} />
                  <Typography sx={{ fontSize: 18, fontWeight: 800 }}>Leitura de maturidade do produto</Typography>
                </Stack>

                <Alert severity="warning" variant="outlined">
                  A tela separa explicitamente o que já pode ser ensinado como processo estável do que ainda está parcial ou em evolução.
                </Alert>

                <Box sx={{ ...FORM_GRID_3_SX, gridTemplateColumns: { xs: '1fr', md: 'repeat(3, minmax(0, 1fr))' } }}>
                  {[
                    {
                      title: 'Estável para treinar',
                      color: 'success' as const,
                      items: TRAINING_MODULES.filter((module) => module.status === 'implemented').map((module) => module.name),
                    },
                    {
                      title: 'Parcial e com ressalvas',
                      color: 'warning' as const,
                      items: TRAINING_MODULES.filter((module) => module.status === 'partial').map((module) => module.name),
                    },
                    {
                      title: 'Ainda em evolução',
                      color: 'default' as const,
                      items: TRAINING_MODULES.filter((module) => module.status === 'planned').map((module) => module.name).concat([
                        'Integrações homologadas além do iFood',
                        'Emissão fiscal oficial em provider governamental',
                      ]),
                    },
                  ].map((column) => (
                    <Paper key={column.title} variant="outlined" sx={{ ...ELEVATED_SURFACE_SX, p: 1.5, ...clampTextSx }}>
                      <Typography sx={{ ...clampTextSx, fontSize: 15, fontWeight: 800, mb: 1 }}>{column.title}</Typography>
                      <Stack direction="row" spacing={0.75} sx={{ flexWrap: 'wrap', mb: 1 }}>
                        <Chip size="small" color={column.color} label={`${column.items.length} itens`} />
                      </Stack>
                      <Stack spacing={0.5}>
                        {column.items.map((item) => (
                          <Typography key={item} sx={{ ...clampTextSx, fontSize: 13.25 }}>
                            • {item}
                          </Typography>
                        ))}
                      </Stack>
                    </Paper>
                  ))}
                </Box>
              </Stack>
            </CardContent>
          </Card>
        </Stack>
      </Box>
    </Box>
  )
}
