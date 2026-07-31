<?php

namespace App\Docs\PtBR;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         version="1.0.0",
 *         title="API Empresarial",
 *         description="API RESTful completa com autenticação JWT, sistema de permissões granular, logs de auditoria e limitação de taxa. Esta API fornece acesso programático a todos os recursos do sistema de gerenciamento empresarial.",
 *         termsOfService="https://empresa.com.br/termos-de-uso",
 *         @OA\Contact(
 *             name="Equipe de Suporte da API",
 *             email="suporte-api@empresa.com.br",
 *             url="https://empresa.com.br/suporte"
 *         ),
 *         @OA\License(
 *             name="Proprietário",
 *             url="https://empresa.com.br/licenca"
 *         )
 *     ),
 *     @OA\Server(
 *         url=L5_SWAGGER_CONST_HOST,
 *         description="Servidor de Desenvolvimento Local"
 *     ),
 *     @OA\Server(
 *         url="https://api.empresa.com.br",
 *         description="Servidor de Produção"
 *     ),
 *     @OA\Server(
 *         url="https://homologacao-api.empresa.com.br",
 *         description="Servidor de Homologação"
 *     )
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Autenticação via token JWT Bearer. Formato: `Bearer {seu_token_aqui}`
 * 
 * **Como obter o token:**
 * 1. Use o endpoint `POST /api/v1/auth/login` com suas credenciais
 * 2. Copie o valor de `access_token` da resposta
 * 3. Clique no botão 'Authorize' acima
 * 4. Cole o token no formato: `Bearer eyJ0eXAiOiJKV1Q...`
 * 
 * **Importante:**
 * - Tokens expiram em 15 minutos (900 segundos)
 * - Use o `refresh_token` para renovar sem fazer login novamente
 * - Tokens revogados (após logout) não podem ser reutilizados"
 * )
 * 
 * @OA\Tag(
 *     name="Autenticação",
 *     description="Endpoints de autenticação e gerenciamento de sessão. 
 * 
 * **Fluxo de Autenticação:**
 * 1. **Login:** Obtenha `access_token` e `refresh_token`
 * 2. **Uso:** Inclua o `access_token` no header de todas as requisições protegidas
 * 3. **Renovação:** Use o `refresh_token` quando o access token expirar
 * 4. **Logout:** Revogue o token atual para encerrar a sessão
 * 
 * **Segurança:**
 * - Rate limiting: 5 tentativas de login por minuto
 * - Tokens são hasheados com SHA-512
 * - Todas as ações são registradas em logs de auditoria
 * - Suporte a múltiplos dispositivos simultâneos",
 *     @OA\ExternalDocumentation(
 *         description="Guia Completo de Autenticação",
 *         url="https://docs.empresa.com.br/autenticacao"
 *     )
 * )
 * 
 * @OA\Tag(
 *     name="Usuários",
 *     description="Operações de gerenciamento de usuários do sistema.
 * 
 * **Permissões Necessárias:**
 * - `users:read` - Listar e visualizar usuários
 * - `users:create` - Criar novos usuários
 * - `users:update` - Atualizar dados de usuários
 * - `users:delete` - Remover usuários (soft delete)
 * 
 * **Recursos:**
 * - Busca e filtros avançados
 * - Paginação automática
 * - Soft delete (preserva histórico)
 * - Auditoria completa de alterações",
 *     @OA\ExternalDocumentation(
 *         description="Guia de Gerenciamento de Usuários",
 *         url="https://docs.empresa.com.br/usuarios"
 *     )
 * )
 * 
 * @OA\Tag(
 *     name="Grupos",
 *     description="Gerenciamento de grupos e atribuição de permissões.
 * 
 * **Funcionalidades:**
 * - Criar e gerenciar grupos de usuários
 * - Atribuir permissões granulares a grupos
 * - Sincronizar membros do grupo
 * - Controle de acesso baseado em funções (RBAC)
 * 
 * **Permissões são herdadas:**
 * Um usuário em múltiplos grupos recebe a união de todas as permissões.",
 *     @OA\ExternalDocumentation(
 *         description="Guia de Grupos e Permissões",
 *         url="https://docs.empresa.com.br/grupos"
 *     )
 * )
 * 
 * @OA\Tag(
 *     name="Funcionalidades",
 *     description="Gerenciamento de funcionalidades e módulos do sistema.
 * 
 * **Funcionalidades** são os módulos/recursos do sistema que podem ter permissões atribuídas.
 * 
 * Exemplos: usuários, grupos, relatórios, configurações, etc."
 * )
 */
class BaseDoc {}