<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
class TextSearch
{
    public static function apply(Builder $query, mixed $term, array $allowedColumns): void
    {
        if (! is_string($term) || trim($term) === '') {
            return;
        }

        $table = $query->getModel()->getTable();
        $schema = $query->getModel()->getConnection()->getSchemaBuilder();
        $availableColumns = $schema->getColumnListing($table);
        $columns = array_values(array_filter($allowedColumns, function (string $column) use ($table, $availableColumns, $schema): bool {
            if ($column === 'id' || str_ends_with($column, '_id') || str_contains($column, '.')) {
                return false;
            }

            if (! in_array($column, $availableColumns, true)) {
                return false;
            }

            $type = strtolower($schema->getColumnType($table, $column));

            return in_array($type, ['char', 'string', 'text', 'enum'], true)
                || str_contains($type, 'char')
                || str_contains($type, 'text');
        }));

        if ($columns === []) {
            return;
        }

        $value = '%'.trim($term).'%';
        $query->where(function (Builder $search) use ($columns, $value): void {
            foreach ($columns as $column) {
                $search->orWhere($column, 'like', $value);
            }
        });
    }
}
