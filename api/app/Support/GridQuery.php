<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class GridQuery
{
    public static function applyTextFilters(Builder $query, array $filters, array $columns): void
    {
        foreach ($columns as $filterKey => $column) {
            $value = $filters[$filterKey] ?? null;

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $query->where($column, 'like', '%' . trim($value) . '%');
        }
    }

    public static function applyBooleanFilters(Builder $query, array $filters, array $columns): void
    {
        foreach ($columns as $filterKey => $column) {
            if (!array_key_exists($filterKey, $filters) || $filters[$filterKey] === null) {
                continue;
            }

            $query->where($column, filter_var($filters[$filterKey], FILTER_VALIDATE_BOOLEAN));
        }
    }

    public static function normalizeSortDir(string $sortDir): string
    {
        return strtolower($sortDir) === 'desc' ? 'desc' : 'asc';
    }
}
