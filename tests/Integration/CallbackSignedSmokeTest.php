<?php

declare(strict_types=1);

final class CallbackSignedSmokeTest extends \PHPUnit\Framework\TestCase
{
    private string $sandboxDir;
    /** @var resource|null */
    private $serverProcess = null;
    /** @var resource[] */
    private array $serverPipes = [];
    private int $serverPort;

    protected function setUp(): void
    {
        $this->sandboxDir = sys_get_temp_dir() . '/webpay-callback-smoke-' . bin2hex(random_bytes(6));
        $this->buildSandbox();
        $this->startPhpServer();
    }

    protected function tearDown(): void
    {
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }

        foreach ($this->serverPipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $this->deleteDir($this->sandboxDir);
    }

    public function testSignedCallbackReturns200WhenSignatureIsValid(): void
    {
        $token = 'token-smoke-200';
        $invoiceId = 25;
        $signature = hash_hmac('sha256', $token . '|' . $invoiceId, 'test-callback-secret');

        [$statusCode, $body] = $this->postCallback($token, $invoiceId, $signature);
        $payload = json_decode($body, true);

        self::assertSame(200, $statusCode, $body);
        self::assertIsArray($payload);
        self::assertTrue($payload['ok'] ?? false);
        self::assertSame($invoiceId, $payload['invoiceId'] ?? null);
        self::assertTrue($payload['authorized'] ?? false);
        self::assertTrue($payload['paymentRecorded'] ?? false);
    }

    public function testSignedCallbackReturns401WhenSignatureIsInvalid(): void
    {
        [$statusCode, $body] = $this->postCallback('token-smoke-401', 77, 'bad-signature');
        $payload = json_decode($body, true);

        self::assertSame(401, $statusCode, $body);
        self::assertIsArray($payload);
        self::assertFalse($payload['ok'] ?? true);
        self::assertSame('Firma inválida', $payload['message'] ?? null);
    }

    private function buildSandbox(): void
    {
        $repoRoot = dirname(__DIR__, 2);
        $callbackDir = $this->sandboxDir . '/modules/gateways/callback';
        $libDir = $this->sandboxDir . '/modules/gateways/webpaydirecto/lib';
        $includesDir = $this->sandboxDir . '/includes';

        mkdir($callbackDir, 0777, true);
        mkdir($libDir, 0777, true);
        mkdir($includesDir, 0777, true);

        copy(
            $repoRoot . '/modules/gateways/callback/webpaydirecto.php',
            $callbackDir . '/webpaydirecto.php'
        );
        copy($repoRoot . '/modules/gateways/webpaydirecto/lib/Config.class.php', $libDir . '/Config.class.php');
        copy($repoRoot . '/modules/gateways/webpaydirecto/lib/TransactionStore.class.php', $libDir . '/TransactionStore.class.php');
        copy($repoRoot . '/modules/gateways/webpaydirecto/lib/PaymentProcessor.class.php', $libDir . '/PaymentProcessor.class.php');

        file_put_contents(
            $libDir . '/TransbankApi.class.php',
            <<<'INNERPHP'
<?php

namespace WebpayDirecto;

class TransbankApi
{
    public function __construct(string $apiKey, string $apiSecret, string $baseUrl)
    {
    }

    public function commitTransaction(string $token): array
    {
        return [
            'status' => Config::STATUS_AUTHORIZED,
            'response_code' => 0,
            'amount' => 1000,
            'currency' => 'CLP',
            'authorization_code' => 'AUTH-SMOKE',
            'session_id' => 'INV-25',
            'buy_order' => 'INV25-ORDER',
        ];
    }
}
INNERPHP
        );

        file_put_contents(
            $this->sandboxDir . '/init.php',
            "<?php\nrequire_once " . var_export($repoRoot . '/tests/bootstrap.php', true) . ";\n"
        );

        file_put_contents(
            $includesDir . '/functions.php',
            <<<'INNERPHP'
<?php

function getGatewayVariables(string $gateway): array
{
    return [
        'type' => 'CC',
        'environment' => 'TEST',
        'apiKey' => 'test-key',
        'apiSecret' => 'test-secret',
        'callbackSecret' => 'test-callback-secret',
    ];
}

function logModuleCall(string $module, string $action, array $request, array $response): void
{
}

function checkCbInvoiceID(int $invoiceId, string $gateway): void
{
}

function addInvoicePayment(int $invoiceId, string $transactionId, float $amount, float $fees, string $gateway): void
{
}

function logTransaction(string $gateway, array $payload, string $message): void
{
}

function logActivity(string $message): void
{
}
INNERPHP
        );

        file_put_contents($includesDir . '/gatewayfunctions.php', "<?php\n");
        file_put_contents($includesDir . '/invoicefunctions.php', "<?php\n");
    }

    private function startPhpServer(): void
    {
        $this->serverPort = $this->findAvailablePort();
        $command = sprintf(
            'php -S 127.0.0.1:%d -t %s',
            $this->serverPort,
            escapeshellarg($this->sandboxDir)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $this->serverProcess = proc_open($command, $descriptors, $this->serverPipes);
        self::assertIsResource($this->serverProcess, 'No se pudo iniciar el servidor PHP embebido para smoke tests.');

        $start = microtime(true);
        while ((microtime(true) - $start) < 4.0) {
            $socket = @fsockopen('127.0.0.1', $this->serverPort);
            if (is_resource($socket)) {
                fclose($socket);
                return;
            }
            usleep(100_000);
        }

        self::fail('El servidor PHP embebido no arrancó a tiempo.');
    }

    private function findAvailablePort(): int
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $port = random_int(20000, 49000);
            $socket = @stream_socket_server("tcp://127.0.0.1:{$port}");
            if ($socket !== false) {
                fclose($socket);
                return $port;
            }
        }

        self::fail('No fue posible encontrar un puerto libre para el smoke test.');
    }

    /** @return array{0:int,1:string} */
    private function postCallback(string $token, int $invoiceId, string $signature): array
    {
        $url = sprintf('http://127.0.0.1:%d/modules/gateways/callback/webpaydirecto.php', $this->serverPort);
        $postBody = http_build_query([
            'token_ws' => $token,
            'invoiceid' => (string) $invoiceId,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'X-Clevers-Signature: ' . $signature,
                ],
                'content' => $postBody,
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);

        $body = file_get_contents($url, false, $context);
        self::assertNotFalse($body, 'No se obtuvo respuesta HTTP del callback.');

        $responseHeaders = $http_response_header ?? [];
        $statusLine = $responseHeaders[0] ?? 'HTTP/1.1 500';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $statusCode = isset($matches[1]) ? (int) $matches[1] : 500;

        return [$statusCode, $body];
    }

    private function deleteDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->deleteDir($itemPath);
            } else {
                @unlink($itemPath);
            }
        }

        @rmdir($path);
    }
}
