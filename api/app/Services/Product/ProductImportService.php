<?php

namespace App\Services\Product;

use App\DTOs\Product\CreateProductCategoryDTO;
use App\DTOs\Product\CreateProductTypeDTO;
use App\Events\Product\ProductImportCommitted;
use App\Exceptions\DuplicateNameException;
use App\Exceptions\Product\InvalidProductImportFileException;
use App\Exceptions\Product\ProductImportLimitExceededException;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductType;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Importação de produtos por planilha CSV (roadmap A2), fluxo em 2 etapas:
 *
 * 1. preview(): parsing + validação linha a linha, SEM gravar nada.
 * 2. commit(): recebe de volta o mesmo shape de linha (reenviado pelo
 *    frontend, sem estado de sessão no servidor) e cria de fato, dentro de
 *    1 transação. Revalida cada linha do zero (nunca confia só no preview
 *    do cliente).
 *
 * Modelo de colunas do CSV (cabeçalho, case-insensitive, ordem livre):
 *   nome*, categoria, tipo*, preco*, descricao, sku, disponivel
 *   (* obrigatório). Delimitador ',' ou ';' (detectado pela 1ª linha).
 *
 * Regra de categoria/tipo: se `tipo` já existe (nome, case-insensitive)
 * no tenant, a linha usa esse tipo e ignora `categoria` (mesmo tipo já
 * pertence a uma categoria fixa). Se `tipo` não existe, `categoria` é
 * obrigatória: categoria existente é reaproveitada, senão criada junto
 * com o tipo. Duas linhas com o mesmo `tipo` novo mas `categoria`
 * divergente são um erro na 2ª linha em diante (evita criar o tipo com
 * uma categoria "vencedora" arbitrária sem avisar).
 */
class ProductImportService
{
    public const MAX_ROWS = 2000;

    private const REQUIRED_HEADERS = ['nome', 'tipo', 'preco'];

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ProductCategoryService $categoryService,
        private ProductTypeService $typeService,
    ) {
    }

    public function preview(UploadedFile $file, int $tenantId): array
    {
        $rows = $this->parseCsv($file);

        $this->assertWithinLimit(count($rows));

        [$categoriesByName, $typesByName, $skusSeen] = $this->preloadState($tenantId);

        $results = [];
        $validCount = 0;

        foreach ($rows as $row) {
            $resolved = $this->resolveRow($row['data'], $row['line'], $categoriesByName, $typesByName, $skusSeen);

            $status = empty($resolved['errors']) ? 'valid' : 'error';

            if ($status === 'valid') {
                $validCount++;
            }

            $results[] = $this->toPreviewRow($resolved, $status);
        }

        return [
            'total' => count($rows),
            'valid_count' => $validCount,
            'error_count' => count($rows) - $validCount,
            'max_rows' => self::MAX_ROWS,
            'rows' => $results,
        ];
    }

    public function commit(array $rows, int $tenantId, int $actorId): array
    {
        $this->assertWithinLimit(count($rows));

        return DB::transaction(function () use ($rows, $tenantId, $actorId) {
            [$categoriesByName, $typesByName, $skusSeen] = $this->preloadState($tenantId);

            $results = [];
            $createdCount = 0;
            $skippedCount = 0;
            $categoriesCreated = 0;
            $typesCreated = 0;
            $createdUuids = [];

            foreach ($rows as $index => $raw) {
                $line = (int) ($raw['line'] ?? ($index + 2));
                $data = $this->normalizeCommitRow($raw);

                $resolved = $this->resolveRow($data, $line, $categoriesByName, $typesByName, $skusSeen);

                if (!empty($resolved['errors'])) {
                    $skippedCount++;
                    $results[] = [
                        'line' => $line,
                        'status' => 'skipped',
                        'errors' => $resolved['errors'],
                        'nome' => $resolved['name'],
                    ];
                    continue;
                }

                $typeKey = mb_strtolower($resolved['type_name']);

                if (!$typesByName[$typeKey]['exists']) {
                    $categoryKey = mb_strtolower($resolved['category_name']);

                    if (!$categoriesByName[$categoryKey]['exists']) {
                        $category = $this->createCategory($resolved['category_name'], $tenantId);
                        $categoriesByName[$categoryKey] = [
                            'exists' => true,
                            'id' => $category->id,
                            'uuid' => $category->uuid,
                        ];
                        $categoriesCreated++;
                    }

                    $type = $this->createType(
                        $resolved['type_name'],
                        $categoriesByName[$categoryKey]['uuid'],
                        $tenantId
                    );

                    $typesByName[$typeKey] = [
                        'exists' => true,
                        'id' => $type->id,
                        'uuid' => $type->uuid,
                        'category_name' => $resolved['category_name'],
                        'created_in_batch' => true,
                    ];
                    $typesCreated++;
                }

                $product = $this->productRepository->create([
                    'tenant_id' => $tenantId,
                    'product_type_id' => $typesByName[$typeKey]['id'],
                    'name' => $resolved['name'],
                    'price' => $resolved['price'],
                    'description' => $resolved['description'],
                    'sku' => $resolved['sku'],
                    'is_available' => $resolved['is_available'],
                    'unit' => 'un',
                ]);

                $createdCount++;
                $createdUuids[] = $product->uuid;

                $results[] = [
                    'line' => $line,
                    'status' => 'created',
                    'errors' => [],
                    'nome' => $product->name,
                    'product_uuid' => $product->uuid,
                ];
            }

            event(new ProductImportCommitted(
                tenantId: $tenantId,
                actorId: $actorId,
                createdCount: $createdCount,
                skippedCount: $skippedCount,
                categoriesCreatedCount: $categoriesCreated,
                typesCreatedCount: $typesCreated,
                productUuids: $createdUuids,
            ));

            return [
                'total' => count($rows),
                'created_count' => $createdCount,
                'skipped_count' => $skippedCount,
                'categories_created_count' => $categoriesCreated,
                'types_created_count' => $typesCreated,
                'rows' => $results,
            ];
        });
    }

    private function assertWithinLimit(int $rowCount): void
    {
        if ($rowCount > self::MAX_ROWS) {
            throw new ProductImportLimitExceededException($rowCount, self::MAX_ROWS);
        }
    }

    private function normalizeCommitRow(array $raw): array
    {
        return [
            'nome' => $raw['nome'] ?? null,
            'categoria' => $raw['categoria'] ?? null,
            'tipo' => $raw['tipo'] ?? null,
            'preco' => $raw['preco'] ?? null,
            'descricao' => $raw['descricao'] ?? null,
            'sku' => $raw['sku'] ?? null,
            'disponivel' => $raw['disponivel'] ?? null,
        ];
    }

    private function toPreviewRow(array $resolved, string $status): array
    {
        return [
            'line' => $resolved['line'],
            'status' => $status,
            'errors' => $resolved['errors'],
            'nome' => $resolved['name'],
            'categoria' => $resolved['category_name'],
            'tipo' => $resolved['type_name'],
            'preco' => $resolved['price'],
            'descricao' => $resolved['description'],
            'sku' => $resolved['sku'],
            'disponivel' => $resolved['is_available'],
            'category_will_be_created' => $resolved['category_will_be_created'],
            'type_will_be_created' => $resolved['type_will_be_created'],
        ];
    }

    /**
     * Pré-carrega o estado atual do tenant em memória (nome em minúsculo
     * como chave) — evita 1 query por linha pra checar existência de
     * categoria/tipo/sku. Usado por preview() (leitura pura) e commit()
     * (mutado conforme categorias/tipos novos são criados na própria
     * transação).
     */
    private function preloadState(int $tenantId): array
    {
        $categoriesByName = [];

        foreach (ProductCategory::where('tenant_id', $tenantId)->whereNull('deleted_at')->get(['id', 'uuid', 'name']) as $category) {
            $categoriesByName[mb_strtolower($category->name)] = [
                'exists' => true,
                'id' => $category->id,
                'uuid' => $category->uuid,
            ];
        }

        $typesByName = [];

        foreach (ProductType::where('tenant_id', $tenantId)->whereNull('deleted_at')->with('productCategory')->get(['id', 'uuid', 'name', 'product_category_id']) as $type) {
            $typesByName[mb_strtolower($type->name)] = [
                'exists' => true,
                'id' => $type->id,
                'uuid' => $type->uuid,
                'category_name' => $type->productCategory->name ?? '',
            ];
        }

        $skusSeen = [];

        foreach (Product::where('tenant_id', $tenantId)->whereNull('deleted_at')->whereNotNull('sku')->pluck('sku') as $sku) {
            $skusSeen[mb_strtolower($sku)] = true;
        }

        return [$categoriesByName, $typesByName, $skusSeen];
    }

    /**
     * Validação pura de 1 linha (sem side-effect de banco) — usada IGUAL
     * por preview() e commit(). $categoriesByName/$typesByName/$skusSeen
     * são mutados só com marcações em memória (tipo/categoria "pendente de
     * criação nesta importação", sku "já visto nesta importação"), nunca
     * gravação real — commit() é quem decide criar de verdade.
     */
    private function resolveRow(
        array $row,
        int $line,
        array &$categoriesByName,
        array &$typesByName,
        array &$skusSeen
    ): array {
        $errors = [];

        $name = trim((string) ($row['nome'] ?? ''));

        if ($name === '') {
            $errors[] = __('messages.product_import.row_name_required');
        }

        $priceRaw = trim((string) ($row['preco'] ?? ''));
        $price = null;

        if ($priceRaw === '') {
            $errors[] = __('messages.product_import.row_price_required');
        } else {
            $normalizedPrice = str_replace(',', '.', $priceRaw);

            if (!is_numeric($normalizedPrice) || (float) $normalizedPrice < 0) {
                $errors[] = __('messages.product_import.row_price_invalid');
            } else {
                $price = round((float) $normalizedPrice, 2);
            }
        }

        $typeName = trim((string) ($row['tipo'] ?? ''));
        $categoryName = trim((string) ($row['categoria'] ?? ''));
        $typeExists = false;

        if ($typeName === '') {
            $errors[] = __('messages.product_import.row_type_required');
        } else {
            $typeKey = mb_strtolower($typeName);

            if (isset($typesByName[$typeKey])) {
                if ($typesByName[$typeKey]['exists']) {
                    $typeExists = true;
                    $pendingCategory = $typesByName[$typeKey]['category_name'];

                    // Conflito de categoria só é erro pra tipo criado
                    // NESTA MESMA importação (created_in_batch) — pra tipo
                    // que já existia antes do commit começar, `categoria`
                    // é sempre ignorada silenciosamente (documentado na
                    // classe), o tipo já tem dono fixo há mais tempo.
                    if (($typesByName[$typeKey]['created_in_batch'] ?? false)
                        && $categoryName !== ''
                        && mb_strtolower($categoryName) !== mb_strtolower($pendingCategory)) {
                        $errors[] = __('messages.product_import.row_type_category_conflict', [
                            'type' => $typeName,
                            'pending_category' => $pendingCategory,
                            'given_category' => $categoryName,
                        ]);
                    }

                    $categoryName = $pendingCategory;
                } else {
                    $pendingCategory = $typesByName[$typeKey]['category_name'];

                    if ($categoryName !== '' && mb_strtolower($categoryName) !== mb_strtolower($pendingCategory)) {
                        $errors[] = __('messages.product_import.row_type_category_conflict', [
                            'type' => $typeName,
                            'pending_category' => $pendingCategory,
                            'given_category' => $categoryName,
                        ]);
                    }

                    $categoryName = $pendingCategory;
                }
            } elseif ($categoryName === '') {
                $errors[] = __('messages.product_import.row_category_required_for_new_type', ['type' => $typeName]);
            } else {
                $typesByName[$typeKey] = [
                    'exists' => false,
                    'id' => null,
                    'category_name' => $categoryName,
                ];
            }
        }

        $categoryWillBeCreated = false;

        if ($typeName !== '' && !$typeExists && $categoryName !== '') {
            $categoryKey = mb_strtolower($categoryName);

            if (!isset($categoriesByName[$categoryKey])) {
                $categoriesByName[$categoryKey] = ['exists' => false, 'id' => null, 'uuid' => null];
            }

            $categoryWillBeCreated = !$categoriesByName[$categoryKey]['exists'];
        }

        $sku = trim((string) ($row['sku'] ?? ''));
        $sku = $sku === '' ? null : $sku;

        if ($sku !== null) {
            $skuKey = mb_strtolower($sku);

            if (isset($skusSeen[$skuKey])) {
                $errors[] = __('messages.product_import.row_sku_duplicate', ['sku' => $sku]);
            } else {
                $skusSeen[$skuKey] = true;
            }
        }

        // JSON pode reenviar `disponivel` já tipado (bool) em vez de string
        // ("sim"/"não") — (string) true vira "1", mas (string) false vira
        // "" (indistinguível de "não veio"), por isso o bool é tratado
        // antes do cast pra string.
        $availableValue = $row['disponivel'] ?? null;

        if (is_bool($availableValue)) {
            $availableRaw = $availableValue ? '1' : '0';
        } else {
            $availableRaw = trim((string) ($availableValue ?? ''));
        }

        $isAvailable = $this->parseBoolean($availableRaw);

        if ($availableRaw !== '' && $isAvailable === null) {
            $errors[] = __('messages.product_import.row_available_invalid');
        }

        return [
            'line' => $line,
            'errors' => $errors,
            'name' => $name,
            'price' => $price,
            'description' => trim((string) ($row['descricao'] ?? '')) ?: null,
            'sku' => $sku,
            'is_available' => $isAvailable ?? true,
            'type_name' => $typeName,
            'category_name' => $categoryName !== '' ? $categoryName : null,
            'category_will_be_created' => $categoryWillBeCreated,
            'type_will_be_created' => $typeName !== '' && !$typeExists,
        ];
    }

    /**
     * sim/não, verdadeiro/falso, true/false, 1/0 — vazio retorna `null`
     * (chamador decide o default true); valor não reconhecido também
     * retorna `null`, mas o chamador distingue os dois casos pelo
     * $availableRaw original.
     */
    private function parseBoolean(string $raw): ?bool
    {
        if ($raw === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($raw));

        return match ($normalized) {
            'sim', 'verdadeiro', 'true', '1', 'yes', 's' => true,
            'não', 'nao', 'falso', 'false', '0', 'no', 'n' => false,
            default => null,
        };
    }

    private function createCategory(string $name, int $tenantId): ProductCategory
    {
        try {
            return $this->categoryService->create(CreateProductCategoryDTO::fromArray(['name' => $name], $tenantId));
        } catch (DuplicateNameException) {
            return ProductCategory::where('tenant_id', $tenantId)
                ->where('name', $name)
                ->whereNull('deleted_at')
                ->firstOrFail();
        }
    }

    private function createType(string $name, string $categoryUuid, int $tenantId): ProductType
    {
        try {
            return $this->typeService->create(CreateProductTypeDTO::fromArray([
                'name' => $name,
                'product_category_uuid' => $categoryUuid,
            ], $tenantId));
        } catch (DuplicateNameException) {
            return ProductType::where('tenant_id', $tenantId)
                ->where('name', $name)
                ->whereNull('deleted_at')
                ->firstOrFail();
        }
    }

    /**
     * Lê o CSV linha a linha (sem carregar o arquivo inteiro em memória).
     * Delimitador ',' ou ';' detectado pela 1ª linha (planilhas exportadas
     * do Excel pt-BR costumam usar ';'). Cabeçalho case-insensitive, ordem
     * livre; colunas extras são ignoradas.
     */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new InvalidProductImportFileException(__('messages.product_import.unreadable_file'));
        }

        $firstLine = fgets($handle);

        if ($firstLine === false || trim($firstLine) === '') {
            fclose($handle);
            throw new InvalidProductImportFileException(__('messages.product_import.empty_file'));
        }

        // Remove BOM UTF-8, comum em CSV exportado do Excel.
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = array_map(
            fn($h) => mb_strtolower(trim((string) $h)),
            str_getcsv($firstLine, $delimiter)
        );

        $missing = array_diff(self::REQUIRED_HEADERS, $header);

        if (!empty($missing)) {
            fclose($handle);
            throw new InvalidProductImportFileException(__('messages.product_import.missing_columns', [
                'columns' => implode(', ', $missing),
            ]));
        }

        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;

            if (count($data) === 1 && trim((string) $data[0]) === '') {
                continue;
            }

            $assoc = [];

            foreach ($header as $i => $column) {
                $assoc[$column] = $data[$i] ?? null;
            }

            $rows[] = ['line' => $line, 'data' => $assoc];
        }

        fclose($handle);

        return $rows;
    }
}
