<?php

namespace WebpayDirecto;

class TransbankApi
{
    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl;

    public function __construct(string $apiKey, string $apiSecret, string $baseUrl)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function createTransaction(array $payload): array
    {
        return $this->request('POST', Config::API_PATH, $payload);
    }

    public function commitTransaction(string $token): array
    {
        return $this->request('PUT', Config::API_PATH . '/' . rawurlencode($token));
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);

        $headers = [
            'Tbk-Api-Key-Id: ' . $this->apiKey,
            'Tbk-Api-Key-Secret: ' . $this->apiSecret,
            'Content-Type: application/json',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ];

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($ch, $options);
        $responseBody = curl_exec($ch);

        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception('Error cURL Transbank: ' . $error);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            throw new \Exception('Respuesta inválida de Transbank: ' . $responseBody, $httpCode);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $message = $decoded['error_message'] ?? $decoded['message'] ?? 'Error desconocido';
            throw new \Exception('Transbank devolvió HTTP ' . $httpCode . ': ' . $message, $httpCode);
        }

        return $decoded;
    }
}
