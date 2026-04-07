<?php

declare(strict_types=1);

namespace TestSupport;

class FakeSchema
{
    public function hasTable(string $table): bool
    {
        return isset(FakeCapsule::$tables[$table]);
    }

    public function create(string $table, callable $callback): void
    {
        FakeCapsule::$tables[$table] = [];
        $callback(new class {
            public function __call(string $name, array $arguments)
            {
                return $this;
            }
        });
    }
}

class FakeQuery
{
    private string $table;
    private array $filters = [];

    public function __construct(string $table)
    {
        $this->table = $table;
        if (!isset(FakeCapsule::$tables[$table])) {
            FakeCapsule::$tables[$table] = [];
        }
    }

    public function where(string $column, $value): self
    {
        $this->filters[] = [$column, $value];
        return $this;
    }

    private function matches(array $row): bool
    {
        foreach ($this->filters as [$column, $value]) {
            if (!array_key_exists($column, $row) || $row[$column] != $value) {
                return false;
            }
        }
        return true;
    }

    private function findIndex(): ?int
    {
        foreach (FakeCapsule::$tables[$this->table] as $index => $row) {
            if ($this->matches($row)) {
                return $index;
            }
        }
        return null;
    }

    public function first()
    {
        $index = $this->findIndex();
        if ($index === null) {
            return null;
        }
        return (object) FakeCapsule::$tables[$this->table][$index];
    }

    public function exists(): bool
    {
        return $this->findIndex() !== null;
    }

    public function update(array $data): void
    {
        $index = $this->findIndex();
        if ($index === null) {
            return;
        }
        FakeCapsule::$tables[$this->table][$index] = array_merge(FakeCapsule::$tables[$this->table][$index], $data);
    }

    public function insert(array $data): void
    {
        if (!isset($data['id'])) {
            $data['id'] = count(FakeCapsule::$tables[$this->table]) + 1;
        }
        FakeCapsule::$tables[$this->table][] = $data;
    }
}

class FakeCapsule
{
    public static array $tables = [];

    public static function reset(): void
    {
        self::$tables = [];
    }

    public static function schema(): FakeSchema
    {
        return new FakeSchema();
    }

    public static function table(string $table): FakeQuery
    {
        return new FakeQuery($table);
    }
}

namespace WHMCS\Database;

class Capsule extends \TestSupport\FakeCapsule
{
}
