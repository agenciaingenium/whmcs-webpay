<?php

namespace WebpayDirecto;

interface TransbankClientInterface
{
    public function createTransaction(array $payload): array;

    public function commitTransaction(string $token): array;
}
