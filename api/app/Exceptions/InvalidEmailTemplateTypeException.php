<?php

namespace App\Exceptions;

/**
 * `type` de e-mail fora de EmailTemplateService::CUSTOMIZABLE_TYPES — inclui
 * tanto type inexistente quanto os types de plataforma/segurança
 * (password_reset/portal_otp/email_confirmation) propositalmente fora do
 * CRUD do tenant.
 */
class InvalidEmailTemplateTypeException extends \RuntimeException {}
