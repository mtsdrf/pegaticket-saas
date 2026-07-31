# Levantamento para adoção de Cloudflare R2 no PegaTicket

Data: 2026-07-29  
Status: levantamento técnico, sem implementação ainda  
Objetivo: mapear o que precisa mudar no PegaTicket para adotar Cloudflare R2 de forma segura, gradual e compatível com a arquitetura atual.

## Resumo executivo

Hoje o PegaTicket usa **duas estratégias diferentes de armazenamento**:

- **BLOB no banco** para avatar de usuário, logo da empresa e imagem de produto
- **`Storage::disk('public')` local** para anexos de suporte e mensagens do contador

Isso significa que a entrada do Cloudflare R2 **não é uma troca simples de `disk`**. A mudança correta exige:

1. separar tipos de arquivo por sensibilidade e uso
2. definir quais ativos serão públicos, privados ou híbridos
3. introduzir um serviço de mídia desacoplado do modelo atual em BLOB
4. migrar dados existentes sem quebrar URLs já emitidas

## Diagnóstico do estado atual

### 1. Mídia pública hoje armazenada no banco

Ativos atualmente salvos em colunas binárias:

- `users.avatar_data` + `users.avatar_mime`
- `products.image_data` + `products.image_mime`
- `tenants.logo_data` + `tenants.logo_mime`

Evidências:

- [api/database/migrations/2026_07_15_140000_add_avatar_data_to_users_table.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/migrations/2026_07_15_140000_add_avatar_data_to_users_table.php)
- [api/database/migrations/2026_07_15_140001_add_image_data_to_products_table.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/migrations/2026_07_15_140001_add_image_data_to_products_table.php)
- [api/database/migrations/2026_07_15_140002_add_logo_data_to_tenants_table.php](/home/mtsdrf/workspace/pegaticket-saas/api/database/migrations/2026_07_15_140002_add_logo_data_to_tenants_table.php)

Serviço atual:

- [api/app/Http/Controllers/User/UserAvatarController.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Controllers/User/UserAvatarController.php)
- [api/app/Http/Controllers/Product/ProductImageController.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Controllers/Product/ProductImageController.php)
- [api/app/Http/Controllers/Tenant/TenantLogoController.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Controllers/Tenant/TenantLogoController.php)

As URLs são montadas por resources e servidas pela API:

- [api/app/Http/Resources/Auth/ProfileResource.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Resources/Auth/ProfileResource.php)
- [api/app/Http/Resources/Product/ProductResource.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Resources/Product/ProductResource.php)
- [api/app/Http/Resources/Tenant/TenantResource.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Http/Resources/Tenant/TenantResource.php)

### 2. Anexos já usam `Storage`, mas ainda local

Casos atuais:

- mensagens contador x empresa
- chamados de suporte

Evidências:

- [api/app/Services/Accounting/AccountingMessageService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Accounting/AccountingMessageService.php)
- [api/app/Services/Support/SupportTicketService.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Services/Support/SupportTicketService.php)
- [api/config/filesystems.php](/home/mtsdrf/workspace/pegaticket-saas/api/config/filesystems.php)

Hoje o disk `public` é local e aponta para `public/storage`, por uma decisão feita para contornar a limitação de `symlink()` da Hostinger.

### 3. Certificados fiscais não devem entrar na mesma trilha

O tenant também armazena:

- `fiscal_certificate_a1_data`
- `fiscal_certificate_a1_password`

Evidência:

- [api/app/Models/Tenant/Tenant.php](/home/mtsdrf/workspace/pegaticket-saas/api/app/Models/Tenant/Tenant.php)

Esses arquivos são **sigilosos e críticos**. Neste momento, o caminho mais seguro é **não migrar o certificado A1 para R2 no primeiro ciclo**.

## Achados técnicos importantes

### 1. O adaptador S3 não está instalado de fato

Apesar de `composer.lock` mencionar os pacotes como suporte do framework, o backend **não tem hoje**:

- `aws/aws-sdk-php`
- `league/flysystem-aws-s3-v3`

Validação local feita hoje:

- `api/vendor/aws/aws-sdk-php`: ausente
- `api/vendor/league/flysystem-aws-s3-v3`: ausente

Consequência:

- a implementação do R2 exige adicionar dependências explícitas no `composer.json`

### 2. O projeto ainda está acoplado a URLs montadas pela API

Hoje a UI espera:

- `avatar_url`
- `image_url`
- `logo_url`

Essas URLs apontam para endpoints do próprio backend. Se adotarmos R2, há três caminhos:

1. manter a API como proxy de leitura
2. passar a devolver URL pública do bucket/domínio
3. usar estratégia híbrida por tipo de ativo

### 3. A arquitetura atual favorece uma migração em duas ondas

O melhor caminho não é “mudar tudo para R2” de uma vez.

O cenário mais seguro é:

- **Onda 1**: anexos e novas mídias
- **Onda 2**: migração de BLOBs legados

## O que o Cloudflare R2 permite hoje

Com base na documentação oficial da Cloudflare em 2026-07-29:

- R2 expõe API compatível com S3 no endpoint `https://<ACCOUNT_ID>.r2.cloudflarestorage.com` e usa região `auto` [Cloudflare R2 S3 API](https://developers.cloudflare.com/r2/api/s3/api/)
- uploads diretos do cliente podem usar **presigned PUT URL** gerada no backend [Cloudflare R2 Upload objects](https://developers.cloudflare.com/r2/objects/upload-objects/)
- downloads privados podem usar **presigned GET URL** [Cloudflare R2 Presigned URLs](https://developers.cloudflare.com/r2/api/s3/presigned-urls/)
- **presigned URLs não funcionam com custom domain**, apenas com o domínio S3 da R2 [Cloudflare R2 Presigned URLs](https://developers.cloudflare.com/r2/api/s3/presigned-urls/)
- buckets públicos com custom domain podem usar cache do Cloudflare; para cache amplo, a Cloudflare recomenda `Cache Everything` [Cloudflare R2 Public buckets](https://developers.cloudflare.com/r2/buckets/public-buckets/)
- uploads diretos do browser exigem **CORS configurado no bucket** [Cloudflare R2 Configure CORS](https://developers.cloudflare.com/r2/buckets/cors/)
- para gerar credenciais S3 do R2, é preciso criar token específico do R2; a própria doc informa que é necessário ter R2 contratado para gerar o token [Cloudflare R2 Authentication](https://developers.cloudflare.com/r2/api/tokens/)

## Recomendação arquitetural para o PegaTicket

## Estratégia recomendada

Adotar **modelo híbrido**, com separação por sensibilidade:

### Classe A — arquivos públicos/cacheáveis

Mover para R2 com custom domain público:

- logo da empresa
- avatar do usuário
- imagem de produto
- possíveis imagens públicas futuras da loja

Forma recomendada:

- bucket público com domínio próprio, por exemplo `media.pegaticket.com`
- leitura direta por URL pública
- escrita controlada pelo backend no primeiro ciclo
- cache agressivo em CDN/browser para reduzir leitura desnecessária no storage

### Regra obrigatória de cache para Classe A

Para mídia pública, o projeto **precisa implementar cache forte** para evitar:

- custos extras de leitura no R2
- consultas desnecessárias ao storage
- carga repetida no backend apenas para montar/redirecionar mídia

Regras recomendadas:

1. cada ativo público deve ter URL versionável
2. a versão só muda quando a imagem for alterada ou removida
3. o cache deve ser invalidado **apenas** para a imagem afetada
4. imagens não alteradas devem continuar servindo da camada de cache

Estratégia prática sugerida:

- manter `logo_updated_at` como precedente do padrão
- introduzir equivalentes para avatar e imagem de produto
- gerar `avatar_url`, `image_url` e `logo_url` com parâmetro de versão, por exemplo `?v=<timestamp-ou-hash>`
- responder com cabeçalhos de cache longo para objetos públicos

Consequência arquitetural:

- não usar invalidação global de cache
- não regenerar URLs de mídia sem mudança real de arquivo
- não atrelar versionamento de imagem ao `updated_at` genérico da entidade, exceto quando isso for realmente exclusivo da mídia

### Classe B — arquivos privados ou semiprotegidos

Mover para R2 privado, sem exposição direta pública:

- anexos de suporte
- anexos de mensagens do contador

Forma recomendada:

- bucket privado
- leitura por presigned GET ou proxy autenticado pelo backend
- upload por backend no primeiro ciclo

### Classe C — arquivos altamente sensíveis

Manter fora da migração inicial:

- certificado A1 fiscal
- qualquer material criptográfico

Forma recomendada:

- continuar em banco criptografado/BLOB no primeiro momento
- reavaliar depois com trilha própria de segredo/arquivo sensível

## Recomendação prática para a primeira versão

### Fase 1

Implementar R2 só para:

- imagens públicas do produto
- logo da empresa
- avatar do usuário
- anexos de suporte e contador

### Fase 2

Criar rotina de migração dos BLOBs legados para objeto remoto.

### Fase 3

Opcionalmente permitir upload direto do frontend para R2 em casos específicos, se fizer sentido operacional.

## Mudanças necessárias no backend Laravel

### 1. Dependências

Adicionar explicitamente:

- `aws/aws-sdk-php`
- `league/flysystem-aws-s3-v3`

### 2. `filesystems.php`

Adicionar disks novos, por exemplo:

- `r2_public`
- `r2_private`

Com endpoint R2, região `auto`, bucket, chave, segredo e URL pública customizada.

### 3. Novo serviço de mídia

Criar uma camada dedicada, por exemplo:

- `App\Services\Media\MediaStorageService`

Responsabilidades:

- salvar arquivo no storage correto
- gerar key determinística ou UUID
- devolver path, mime, size, checksum
- gerar URL pública ou signed URL
- apagar arquivo antigo quando houver substituição
- centralizar invalidação seletiva de cache da mídia alterada

### 4. Novo modelo de persistência

Hoje o domínio guarda:

- `*_data`
- `*_mime`
- `*_path`

O ideal é evoluir para algo assim:

- `*_disk`
- `*_path`
- `*_mime`
- `*_size`
- `*_checksum`
- `*_visibility`
- `*_uploaded_at`

No curto prazo, podemos manter compatibilidade mínima com:

- `logo_path`
- `image_path`
- `avatar_path`

e deixar de usar `*_data` em novos uploads.

Também é recomendado introduzir timestamps dedicados de mídia quando ainda não existirem:

- `logo_updated_at` já existe e deve ser preservado
- `image_updated_at` para produto
- `avatar_updated_at` para usuário

Esses campos ajudam a manter cache-busting preciso sem depender de atualização geral do registro.

### 5. Compatibilidade dos resources

Os resources deverão mudar de:

- URL construída para endpoint da API

para:

- URL pública R2 quando o ativo for público
- URL assinada/proxy quando o ativo for privado

### 6. Migração dos controllers de mídia

Controllers atuais de leitura binária podem:

1. continuar existindo temporariamente para legado
2. redirecionar para URL pública/assinada
3. virar fallback para registros ainda não migrados

Essa terceira opção é a mais segura durante a transição.

### 7. Regra obrigatória de remoção de objeto antigo

Quando o usuário:

- substituir uma logo
- substituir um avatar
- substituir a imagem de um produto
- remover qualquer uma dessas imagens

o sistema deve:

1. identificar o objeto remoto anterior
2. remover esse objeto do R2
3. persistir o novo estado do registro
4. invalidar apenas a referência/cache da mídia afetada

Sem isso, o projeto corre risco de:

- acúmulo invisível de lixo no bucket
- aumento de custo por storage
- crescimento descontrolado da base de objetos

Essa remoção deve ser tratada como parte do fluxo oficial de atualização de mídia, não como limpeza eventual.

## Mudanças necessárias no frontend

### 1. Pouco impacto estrutural

O frontend já consome:

- `avatar_url`
- `image_url`
- `logo_url`

Se a API continuar devolvendo string pronta, o impacto de UI tende a ser baixo.

### 2. Impacto só aumenta se houver upload direto

Se quisermos upload direto browser → R2, será preciso:

- endpoint para gerar presigned PUT
- fluxo de upload em duas etapas
- CORS no bucket
- validação de tamanho, mime e extensão antes do PUT
- confirmação final no backend para associar o objeto ao registro

Minha recomendação inicial é **não começar com upload direto do browser**.

Independente da estratégia de upload, o frontend não deve tentar “limpar cache” manualmente de forma global. Ele deve apenas consumir a nova URL versionada devolvida pela API.

## Mudanças necessárias no banco

## Opção recomendada

Não apagar BLOB imediatamente.

Fazer migração progressiva:

1. adicionar novos campos de storage remoto
2. começar a gravar uploads novos no R2
3. migrar legados por comando Artisan em lote
4. só depois remover `*_data`

### Campos sugeridos

Para `users`, `products`, `tenants`:

- `avatar_disk` / `image_disk` / `logo_disk`
- `avatar_size` / `image_size` / `logo_size`
- `avatar_checksum` / `image_checksum` / `logo_checksum`
- `avatar_uploaded_at` / `image_uploaded_at` / `logo_uploaded_at`

### Ganho esperado

- redução do peso do banco
- respostas mais leves
- menos carga de CPU/memória no PHP ao servir binário
- melhor cache/CDN para mídia pública
- menos leitura repetida no storage por reaproveitamento de cache

## Impacto em deploy e ambiente

Será necessário incluir no `.env` do backend variáveis como:

```env
FILESYSTEM_DISK=local
R2_ACCOUNT_ID=
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_PUBLIC_BUCKET=
R2_PRIVATE_BUCKET=
R2_PUBLIC_URL=
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_REGION=auto
```

Observação importante:

- eu **não recomendo** trocar o `FILESYSTEM_DISK` global do sistema inteiro para `s3`
- o melhor é usar disks nomeados e escolher explicitamente por caso de uso

Assim evitamos quebrar:

- backup local
- health check de disco
- fluxos que hoje assumem `public` local

## Impacto na Hostinger

### Ponto positivo

R2 reduz dependência de:

- `public/storage`
- tamanho do banco para mídias
- throughput do PHP para servir arquivos

### Ponto de atenção

O backend em hospedagem compartilhada seguirá dependendo de:

- saída HTTPS para o endpoint R2
- tempo razoável de upload para PUT de objetos

Isso costuma ser viável, mas precisa de validação prática com:

- upload de imagem pequena
- upload de anexo maior
- leitura pública e privada

## Estratégia de migração recomendada

### Etapa 0 — preparação

- configurar conta R2
- criar buckets
- criar token R2
- configurar custom domain público
- configurar CORS se houver upload direto

### Etapa 1 — fundação no código

- instalar dependências S3/Flysystem
- criar disks R2
- criar serviço central de mídia
- adaptar uploads novos para gravar em R2

### Etapa 2 — compatibilidade

- resources passam a devolver URL remota quando existir
- controllers binários viram fallback para legado

### Etapa 3 — migração de legado

- comando Artisan para varrer `users/products/tenants`
- enviar BLOB para R2
- preencher path/disk/checksum
- opcionalmente marcar registro como migrado

### Etapa 4 — limpeza

- remover dependência dos BLOBs
- remover colunas binárias em migration futura

## Testes necessários

### Backend

- upload de logo/avatar/imagem de produto em R2
- substituição remove ou invalida objeto anterior
- leitura pública devolve URL correta
- anexo privado gera acesso autenticado correto
- fallback legado continua funcionando
- migration command idempotente

### Frontend

- cadastro/edição de empresa com logo
- cadastro/edição de produto com imagem
- perfil do usuário com avatar
- renderização da loja pública com mídia remota
- anexos de suporte/contador

### Operacional

- cache busting continua funcionando
- cache só é invalidado para a imagem realmente alterada ou removida
- imagem antiga é removida do bucket quando houver substituição ou exclusão
- rollback para ativo local ainda possível
- deploy não expõe chaves R2 no frontend

## Principais riscos

1. migrar tudo de uma vez e quebrar URLs públicas já emitidas
2. tentar usar presigned URL com custom domain, o que a Cloudflare não suporta
3. mover certificado A1 para a mesma estratégia de mídia comum
4. trocar `FILESYSTEM_DISK` global e afetar fluxos que não deveriam ir para R2
5. esquecer limpeza de objeto antigo, gerando lixo e custo
6. esquecer CORS se houver upload direto browser → R2
7. invalidar cache de forma ampla e aumentar custo/tráfego sem necessidade

## Decisão recomendada

A recomendação mais segura para o PegaTicket hoje é:

1. **adotar Cloudflare R2 em modo híbrido**
2. **começar por mídia pública e anexos não críticos**
3. **manter certificado fiscal fora da primeira onda**
4. **não usar upload direto do browser no primeiro ciclo**
5. **migrar BLOB legado em lote, com fallback**
6. **implementar cache forte com versionamento estável e invalidação seletiva**
7. **apagar obrigatoriamente o objeto antigo no R2 quando houver substituição ou remoção**

## Próximo passo sugerido

Se você quiser, a próxima etapa já pode ser a implementação da fundação técnica:

1. instalar adaptador S3 no backend
2. configurar disks `r2_public` e `r2_private`
3. criar `MediaStorageService`
4. migrar primeiro `tenants.logo`
5. depois `products.image`
6. depois `users.avatar`
7. por fim anexos privados

Isso é o caminho com menor risco e melhor retorno imediato.
