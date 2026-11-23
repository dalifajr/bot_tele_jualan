<?php

namespace App\Services\Vpn;

interface VpnProviderInterface
{
    /**
     * @return array{ok: bool, latency_ms?: float, message: string, data?: array}
     */
    public function healthCheck(): array;

    /**
     * @return array{success: bool, available?: bool, exists?: bool, message: string}
     */
    public function checkUsername(string $username, ?string $protocol = null): array;

    /**
     * @return array{success: bool, accounts: array, message: string}
     */
    public function listAccounts(?string $protocol = null, ?string $status = null, ?string $search = null, bool $includeConfig = false): array;

    /**
     * @return array{success: bool, data?: array, message: string}
     */
    public function getAccountDetail(string $protocol, string $username, string $sniMode = '3'): array;

    /**
     * @return array{success: bool, data?: array, output?: string, message: string}
     */
    public function createAccount(array $payload, ?string $idempotencyKey = null): array;

    /**
     * @return array{success: bool, data?: array, message: string}
     */
    public function renewAccount(string $protocol, string $username, int $days, ?int $quotaGb = null, ?int $ipLimit = null, ?string $idempotencyKey = null): array;

    /**
     * @return array{success: bool, data?: array, message: string}
     */
    public function suspendAccount(string $protocol, string $username, ?string $reason = null, bool $adminSuspended = true): array;

    /**
     * @return array{success: bool, data?: array, message: string}
     */
    public function unsuspendAccount(string $protocol, string $username): array;

    /**
     * @return array{success: bool, data?: array, message: string}
     */
    public function deleteAccount(string $protocol, string $username): array;
}
