<?php

namespace WebpayDirecto;

use WHMCS\Database\Capsule;

class TransactionStore
{
    public const TABLE = 'mod_clevers_webpay_tx';

    public static function ensureTable(): void
    {
        if (Capsule::schema()->hasTable(self::TABLE)) {
            return;
        }

        Capsule::schema()->create(self::TABLE, function ($table) {
            $table->increments('id');
            $table->integer('invoice_id')->nullable()->index();
            $table->string('buy_order', 64)->nullable()->index();
            $table->string('token_ws', 128)->unique();
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

    public static function recordCreate(int $invoiceId, string $buyOrder, string $token, float $amount, string $currency): void
    {
        self::ensureTable();

        $now = date('Y-m-d H:i:s');
        $existing = Capsule::table(self::TABLE)->where('token_ws', $token)->first();

        $data = [
            'invoice_id' => $invoiceId,
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
        return $row ? (bool) $row->payment_recorded : false;
    }
}
