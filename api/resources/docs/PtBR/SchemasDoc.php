<?php

namespace App\Docs\PtBR;

/**
 * @OA\Schema(
 *     schema="SuccessResponse",
 *     type="object",
 *     title="Resposta de Sucesso",
 *     description="Estrutura padrão de resposta de sucesso da API",
 *     required={"success", "data"},
 *     @OA\Property(
 *         property="success",
 *         type="boolean",
 *         example=true,
 *         description="Indica se a requisição foi bem-sucedida"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Operação concluída com sucesso.",
 *         description="Mensagem de sucesso legível para o usuário"
 *     ),
 *     @OA\Property(
 *         property="data",
 *         description="Dados da resposta (pode ser objeto, array ou null)"
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         description="Metadados adicionais (paginação, timestamps, etc)",
 *         example={}
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     type="object",
 *     title="Resposta de Erro",
 *     description="Estrutura padrão de resposta de erro da API",
 *     required={"success", "message", "code"},
 *     @OA\Property(
 *         property="success",
 *         type="boolean",
 *         example=false,
 *         description="Sempre false para respostas de erro"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Ocorreu um erro ao processar a requisição.",
 *         description="Mensagem de erro legível para o usuário"
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         example="ERROR_CODE",
 *         description="Código de erro único para identificação e tratamento programático"
 *     ),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         description="Detalhes específicos do erro (vazio se não houver detalhes)",
 *         example={}
 *     ),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         description="Metadados adicionais do erro",
 *         example={}
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="ValidationErrorResponse",
 *     type="object",
 *     title="Resposta de Erro de Validação",
 *     description="Resposta retornada quando há erros de validação nos dados enviados",
 *     required={"success", "message", "code", "errors"},
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Erro de validação."),
 *     @OA\Property(property="code", type="string", example="VALIDATION_ERROR"),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         description="Erros de validação organizados por campo",
 *         example={
 *             "email": {"O campo email é obrigatório.", "O email deve ser válido."},
 *             "password": {"A senha deve ter pelo menos 8 caracteres."}
 *         }
 *     ),
 *     @OA\Property(property="meta", type="object", example={})
 * )
 * 
 * @OA\Schema(
 *     schema="RateLimitErrorResponse",
 *     type="object",
 *     title="Resposta de Limite de Taxa Excedido",
 *     description="Resposta quando o limite de requisições por minuto é excedido",
 *     required={"success", "message", "code"},
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Muitas tentativas. Tente novamente em 1 minuto(s)."
 *     ),
 *     @OA\Property(property="code", type="string", example="TOO_MANY_REQUESTS"),
 *     @OA\Property(property="errors", type="object", example={}),
 *     @OA\Property(
 *         property="meta",
 *         type="object",
 *         @OA\Property(
 *             property="retry_after_seconds",
 *             type="integer",
 *             example=60,
 *             description="Segundos até poder tentar novamente"
 *         ),
 *         @OA\Property(
 *             property="retry_after",
 *             type="string",
 *             format="date-time",
 *             example="2025-01-04T23:46:00+00:00",
 *             description="Timestamp ISO-8601 de quando poderá tentar novamente"
 *         )
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     title="Metadados de Paginação",
 *     description="Informações sobre paginação de resultados",
 *     @OA\Property(property="current_page", type="integer", example=1, description="Número da página atual"),
 *     @OA\Property(property="from", type="integer", example=1, description="Índice do primeiro item da página atual (base 1)"),
 *     @OA\Property(property="last_page", type="integer", example=10, description="Número da última página disponível"),
 *     @OA\Property(property="per_page", type="integer", example=15, description="Quantidade de itens por página"),
 *     @OA\Property(property="to", type="integer", example=15, description="Índice do último item da página atual (base 1)"),
 *     @OA\Property(property="total", type="integer", example=150, description="Número total de itens em todas as páginas"),
 *     @OA\Property(
 *         property="links",
 *         type="object",
 *         description="URLs de navegação entre páginas",
 *         @OA\Property(property="first", type="string", example="http://api.com/users?page=1"),
 *         @OA\Property(property="last", type="string", example="http://api.com/users?page=10"),
 *         @OA\Property(property="prev", type="string", nullable=true, example=null),
 *         @OA\Property(property="next", type="string", example="http://api.com/users?page=2")
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="UUID",
 *     type="string",
 *     format="uuid",
 *     pattern="^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$",
 *     example="8796e85c-7e9c-4788-a503-840ede8cf78a",
 *     description="Identificador Único Universal (UUID versão 4)"
 * )
 * 
 * @OA\Schema(
 *     schema="Timestamp",
 *     type="string",
 *     format="date-time",
 *     example="2025-01-04T23:30:00.000000Z",
 *     description="Data e hora no formato ISO-8601 (UTC)"
 * )
 */
class SchemasDoc {}