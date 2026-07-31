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
      'O PegaTicket atende operacoes com catalogo, pedidos, loja online, estoque, integracoes e governanca fiscal em um plano unico, com tudo liberado.',
  },
  {
    question: 'Posso ter mais de uma unidade/filial?',
    answer:
      'Sim, o sistema é multiempresa e organiza lojas, filiais e depósitos dentro da mesma conta, com usuários e permissões próprios por unidade.',
  },
  {
    question: 'Existe troca de plano?',
    answer:
      'Neste momento nao. A plataforma trabalha com um plano unico, com todas as funcionalidades atuais liberadas.',
  },
  {
    question: 'Como funciona o suporte durante a implantação?',
    answer:
      'Você recebe acompanhamento para configurar a empresa, cadastrar produtos e publicar sua loja online antes de começar a operar com clientes reais.',
  },
]
