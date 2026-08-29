<?php

declare(strict_types=1);

namespace WHMCS\Database {
    class Capsule
    {
        /**
         * @return object
         */
        public static function table(string $name): object
        {
            return new class {
                public function where(...$args): self { return $this; }
                public function whereIn(string $column, array $values): self { return $this; }
                public function first() { return null; }
                public function exists(): bool { return false; }
                public function update(array $data): int { return 0; }
                public function insert(array $data): bool { return true; }
                public function count(): int { return 0; }
                public function delete(): int { return 0; }
            };
        }

        /**
         * @return object
         */
        public static function schema(): object
        {
            return new class {
                public function hasTable(string $name): bool { return true; }
                public function hasColumn(string $table, string $column): bool { return true; }
                public function create(string $table, callable $callback): void {}
                public function table(string $table, callable $callback): void {}
            };
        }
    }
}

namespace {
    if (!function_exists('logModuleCall')) {
        function logModuleCall(string $module, string $action, array $request, array $response, $ignore = null, $replace = null): void {}
    }
    if (!function_exists('logTransaction')) {
        function logTransaction(string $gateway, array $payload, string $message): void {}
    }
    if (!function_exists('logActivity')) {
        function logActivity(string $message): void {}
    }
    if (!function_exists('checkCbInvoiceID')) {
        function checkCbInvoiceID(int $invoiceId, string $gateway): void {}
    }
    if (!function_exists('addInvoicePayment')) {
        function addInvoicePayment(int $invoiceId, string $transactionId, float $amount, float $fees, string $gateway): void {}
    }
    if (!function_exists('getGatewayVariables')) {
        /** @return array<string,string> */
        function getGatewayVariables(string $gateway): array { return []; }
    }
}


