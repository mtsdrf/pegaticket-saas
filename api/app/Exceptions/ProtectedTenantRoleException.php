<?php

namespace App\Exceptions;

/**
 * Violação de proteção do perfil "owner" de uma tenant (alteração, sync de
 * permissões ou exclusão bloqueada) — distinta de \RuntimeException genérica
 * pelo mesmo motivo de DuplicateNameException: exceções HTTP do Symfony
 * (NotFoundHttpException/ModelNotFoundException) também estendem
 * \RuntimeException e não podem ser capturadas junto por engano.
 */
class ProtectedTenantRoleException extends \RuntimeException
{
}
