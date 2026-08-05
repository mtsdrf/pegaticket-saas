<?php

namespace App\Exceptions;

/**
 * Cota de inventário por canal (TicketTypeChannelQuota) esgotada para o
 * canal desta venda — distinta de "sem estoque geral" (mensagem própria,
 * ver messages.ticket_type_channel_quota.channel_quota_exceeded).
 */
class InsufficientChannelQuotaException extends \RuntimeException {}
