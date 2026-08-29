<?php

declare(strict_types=1);

namespace TestSupport {

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

    public function table(string $table, callable $callback)
    {
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

    public function where(...$args): self
    {
        $column = $args[0] ?? null;
        $operator = '=';
        $value = $args[1] ?? null;

        if (isset($args[2])) {
            $operator = $args[1];
            $value = $args[2];
        }

        if ($column !== null) {
            $this->filters[] = [$column, $operator, $value];
        }
        return $this;
    }

    private function matches(array $row): bool
    {
        foreach ($this->filters as [$column, $operator, $value]) {
            if (!array_key_exists($column, $row)) {
                return false;
            }
            $rowValue = $row[$column];
            switch ($operator) {
                case '=':
                    if ($rowValue != $value) {
                        return false;
                    }
                    break;
                case '>=':
                    if (!($rowValue >= $value)) {
                        return false;
                    }
                    break;
                case '<=':
                    if (!($rowValue <= $value)) {
                        return false;
                    }
                    break;
                case '>':
                    if (!($rowValue > $value)) {
                        return false;
                    }
                    break;
                case '<':
                    if (!($rowValue < $value)) {
                        return false;
                    }
                    break;
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

    public function count(): int
    {
        $count = 0;
        foreach (FakeCapsule::$tables[$this->table] ?? [] as $row) {
            if ($this->matches($row)) {
                $count++;
            }
        }
        return $count;
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

}

namespace WHMCS\Database {

class Capsule extends \TestSupport\FakeCapsule
{
}

}

namespace {

if (!function_exists('getGatewayVariables')) {
    function getGatewayVariables(string $gateway): array
    {
        return [
            'type' => 'CC',
            'environment' => 'TEST',
            'apiKey' => 'test-key',
            'apiSecret' => 'test-secret',
        ];
    }
}

if (!function_exists('logModuleCall')) {
    function logModuleCall(string $module, string $action, array $request, array $response): void
    {
    }
}

if (!function_exists('checkCbInvoiceID')) {
    function checkCbInvoiceID(int $invoiceId, string $gateway): void
    {
    }
}

if (!function_exists('addInvoicePayment')) {
    function addInvoicePayment(int $invoiceId, string $transactionId, float $amount, float $fees, string $gateway): void
    {
        if (isset($GLOBALS['test_invoice_payments'])) {
            $GLOBALS['test_invoice_payments'][] = compact('invoiceId', 'transactionId', 'amount', 'fees', 'gateway');
        }
    }
}

if (!function_exists('logTransaction')) {
    function logTransaction(string $gateway, array $payload, string $message): void
    {
    }
}

if (!function_exists('logActivity')) {
    function logActivity(string $message): void
    {
    }
}

}
