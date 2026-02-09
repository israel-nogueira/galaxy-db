<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Core;

interface DatabaseInterface
{
    public function select(?string $columns = null): array;
    public function first(?string $columns = null): ?array;
    public function count(): int;
    public function insert(): int|bool;
    public function update(): int;
    public function delete(): int;
    public function where(string $column, mixed $value, string $operator = '='): self;
    public function orWhere(string $column, mixed $value, string $operator = '='): self;
    public function orderBy(string $column, string $direction = 'ASC'): self;
    public function limit(int $limit, ?int $offset = null): self;
    public function groupBy(string $column): self;
    public function table(string $table): self;
    public function beginTransaction(): bool;
    public function commit(): bool;
    public function rollback(): bool;
}
