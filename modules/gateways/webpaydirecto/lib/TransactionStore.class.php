<?php

namespace WebpayDirecto;

use WHMCS\Database\Capsule;

class TransactionStore
{
    public const TABLE = 'mod_clevers_webpay_tx';

    public static function getOrCreateCorrelationId(string $token): string
    {
        self::ensureTable();

        $existing = Capsule::table(self::TABLE)->where('token_ws', $token)->first();
        if ($existing && !empty($existing->correlation_id)) {
            return (string) $existing->correlation_id;
        }

        $correlationId = self::buildCorrelationId($token);
        $now = date('Y-m-d H:i:s');

        if ($existing) {
            Capsule::table(self::TABLE)->where('id', $existing->id)->update([
                'correlation_id' => $correlationId,
                'updated_at' => $now,
            ]);
            return $correlationId;
        }

        Capsule::table(self::TABLE)->insert([
            'token_ws' => $token,
            'correlation_id' => $correlationId,
            'status' => 'RECEIVED',
            'commit_attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $correlationId;
    }

    public static function ensureTable(): void
    {
        if (!Capsule::schema()->hasTable(self::TABLE)) {
            Capsule::schema()->create(self::TABLE, function ($table) {
                $table->increments('id');
                $table->integer('invoice_id')->nullable()->index();
                $table->string('buy_order', 64)->nullable()->index();
                $table->string('token_ws', 128)->unique();
                $table->string('correlation_id', 64)->nullable()->index();
                $table->decimal('amount', 15, 2)->nullable();
                $table->string('currency', 8)->nullable();
                $table->string('status', 40)->nullable();
                $table->integer('response_code')->nullable();
                $table->string('authorization_code', 40)->nullable();
                $table->string('source', 24)->nullable();
                $table->unsignedInteger('commit_attempts')->default(0);
                $table->boolean('payment_recorded')->default(false);
                $table->text('raw_response')->nullable();
                $table->timestamps();
            });
        }

        if (method_exists(Capsule::schema(), 'hasColumn') && !Capsule::schema()->hasColumn(self::TABLE, 'correlation_id')) {
            Capsule::schema()->table(self::TABLE, function ($table) {
                $table->string('correlation_id', 64)->nullable()->index();
            });
        }
    }

    public static function recordCreate(int $invoiceId, string $buyOrder, string $token, float $amount, string $currency): void
    {
        self::ensureTable();

        $now = date('Y-m-d H:i:s');
        $existing = Capsule::table(self::TABLE)->where('token_ws', $token)->first();

        $data = [
            'invoice_id' => $invoiceId,
            'correlation_id' => self::buildCorrelationId($token),
            'buy_order' => $buyOrder,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'status' => 'CREATED',
            'updated_at' => $now,
        ];

        if ($existing) {
            Capsule::table(self::TABLE)->where('id', $existing->id)->update($data);
            return;
        }

        $data['token_ws'] = $token;
        $data['source'] = 'create';
        $data['created_at'] = $now;
        Capsule::table(self::TABLE)->insert($data);
    }

    public static function markCommitAttempt(string $token, string $source): void
    {
        self::ensureTable();

        $now = date('Y-m-d H:i:s');
        $existing = Capsule::table(self::TABLE)->where('token_ws', $token)->first();

        if ($existing) {
            Capsule::table(self::TABLE)
                ->where('id', $existing->id)
                ->update([
                    'source' => $source,
                    'commit_attempts' => (int) $existing->commit_attempts + 1,
                    'updated_at' => $now,
                ]);
            return;
        }

        Capsule::table(self::TABLE)->insert([
            'token_ws' => $token,
            'correlation_id' => self::buildCorrelationId($token),
            'source' => $source,
            'commit_attempts' => 1,
            'status' => 'RECEIVED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function saveCommitResult(string $token, array $payload): void
    {
        self::ensureTable();

        $now = date('Y-m-d H:i:s');
        $existing = Capsule::table(self::TABLE)->where('token_ws', $token)->first();

        $data = [
            'invoice_id' => $payload['invoice_id'] ?? null,
            'correlation_id' => $payload['correlation_id'] ?? null,
            'buy_order' => $payload['buy_order'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'currency' => $payload['currency'] ?? null,
            'status' => $payload['status'] ?? null,
            'response_code' => $payload['response_code'] ?? null,
            'authorization_code' => $payload['authorization_code'] ?? null,
            'payment_recorded' => !empty($payload['payment_recorded']),
            'raw_response' => $payload['raw_response'] ?? null,
            'source' => $payload['source'] ?? null,
            'updated_at' => $now,
        ];

        if ($existing) {
            Capsule::table(self::TABLE)->where('id', $existing->id)->update($data);
            return;
        }

        $data['token_ws'] = $token;
        $data['created_at'] = $now;
        Capsule::table(self::TABLE)->insert($data);
    }

    public static function isPaymentRecorded(string $token): bool
    {
        self::ensureTable();
        $row = Capsule::table(self::TABLE)->where('token_ws', $token)->first();
        return $row ? !empty($row->payment_recorded) : false;
    }

    public static function claimPaymentRecorded(string $token, string $source): bool
    {
        self::ensureTable();

        $now = date('Y-m-d H:i:s');
        $existing = Capsule::table(self::TABLE)->where('token_ws', $token)->first();
        if ($existing) {
            if (!empty($existing->payment_recorded)) {
                return false;
            }

            Capsule::table(self::TABLE)->where('id', $existing->id)->update([
                'payment_recorded' => true,
                'source' => $source,
                'updated_at' => $now,
            ]);
            return true;
        }

        Capsule::table(self::TABLE)->insert([
            'token_ws' => $token,
            'correlation_id' => self::buildCorrelationId($token),
            'source' => $source,
            'status' => 'RECEIVED',
            'commit_attempts' => 0,
            'payment_recorded' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return true;
    }

    private static function buildCorrelationId(string $seed): string
    {
        return 'wpd-' . substr(hash('sha256', $seed), 0, 20);
    }
}
