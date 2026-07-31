/**
 * Só perguntas com resposta confirmada no código/arquitetura real
 * (trial, cancelamento, arrependimento — ver `.claude/memory/architecture-decisions.md`,
 * `SubscriptionService`/`SubscriptionStateMachine`). Nenhuma política de
 * reembolso/SLA/certificação inventada.
 */
export interface FaqItem {
  question: string
  answer: string
}

export const FAQ_ITEMS: FaqItem[] = [
  {
    question: 'Existe período de teste gratuito?',
    answer:
      'Sim. Toda assinatura nova começa com 14 dias de teste antes da primeira cobrança, sem necessidade de negociar isso à parte.',
  },
  {
    question: 'Como funciona o cancelamento?',
    answer:
      'Você pode cancelar a qualquer momento diretamente na sua assinatura, imediatamente ou ao final do ciclo já pago — sem multa contratual.',
  },
  {
    question: 'Se eu me arrepender, dá para desistir?',
    answer:
      'Sim. Você tem 7 dias corridos após a contratação para solicitar arrependimento e o reembolso do valor pago, conforme o direito de arrependimento previsto em lei.',
  },
  {
    question: 'Funciona para o meu tipo de negócio?',
    answer:
      'O PegaTicket atende atacadistas, varejistas, laticínios e distribuidoras de bebidas nos planos Prata e Ouro; bares e casas noturnas com atendimento em mesas/comandas usam o módulo Balcão, disponível no plano Diamante.',
  },
  {
    question: 'Posso ter mais de uma unidade/filial?',
    answer:
      'Sim, o sistema é multiempresa e organiza lojas, filiais e depósitos dentro da mesma conta, com usuários e permissões próprios por unidade.',
  },
  {
    question: 'Posso mudar de plano depois?',
    answer:
      'Sim, é possível alterar o plano da assinatura conforme a operação cresce, sem precisar recomeçar o cadastro.',
  },
  {
    question: 'Como funciona o suporte durante a implantação?',
    answer:
      'Você recebe acompanhamento para configurar a empresa, cadastrar produtos e publicar sua loja online antes de começar a operar com clientes reais.',
  },
]
