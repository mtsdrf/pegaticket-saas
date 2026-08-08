<?php

namespace App\Enums\Payment;

/**
 * Estados possíveis de payments.status (roadmap R6, gap 2.7 do plano de
 * homologação PagBank — ver .claude/skills/pagbank-integration.md §61).
 * Formalização de um valor que já era gravado como string livre desde a
 * migration original (2026_07_20_110003_create_payments_table) — os 8 casos
 * abaixo são os únicos valores efetivamente escritos em payments.status hoje
 * (levantados via grep em SalePaymentService/mapStatus dos providers, não a
 * lista do comentário antigo da migration, que incluía 'requested' e
 * 'not_applicable' — esses dois nunca foram valores de Payment.status, são
 * de Refund.status e do contrato de retorno de preapproval, respectivamente).
 *
 * Escopo desta fase é SÓ formalizar o tipo (enum + cast no Model) — não há
 * guard de transição de estado aqui de propósito (mudança de comportamento
 * maior, arriscada sem mapear todas as transições reais primeiro).
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case InAnalysis = 'in_analysis';
    case Paid = 'paid';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Refunded = 'refunded';
    case Divergent = 'divergent';
}
