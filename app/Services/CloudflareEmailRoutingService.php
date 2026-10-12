<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CloudflareEmailRoutingService
{
    protected ?string $apiToken;
    protected ?string $zoneId;
    protected ?string $accountId;
    protected string $domain;
    protected string $baseUrl = 'https://api.cloudflare.com/client/v4';

    public function __construct()
    {
        $this->apiToken = config('services.cloudflare.email_api_token');
        $this->zoneId = config('services.cloudflare.zone_id');
        $this->accountId = config('services.cloudflare.account_id');
        $this->domain = (string) (config('services.cloudflare.domain') ?: 'soundmix.live');
    }

    public function isConfigured(): bool
    {
        return filled($this->apiToken) && filled($this->zoneId) && filled($this->accountId);
    }

    public function domain(): string
    {
        return $this->domain;
    }

    /**
     * Get routing status for the zone.
     *
     * @return array{success: bool, enabled: bool, status: string, raw: array}
     */
    public function getRoutingStatus(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'enabled' => false,
                'status' => 'not_configured',
                'raw' => [],
            ];
        }

        try {
            $response = $this->http()->get("{$this->baseUrl}/zones/{$this->zoneId}/email/routing");

            if ($response->successful()) {
                $result = $response->json('result') ?? [];
                $enabled = (bool) ($result['enabled'] ?? false);
                $status = (string) ($result['status'] ?? ($enabled ? 'ready' : 'disabled'));

                return [
                    'success' => true,
                    'enabled' => $enabled,
                    'status' => $status,
                    'raw' => $result,
                ];
            }

            Log::warning('Cloudflare routing status check failed', [
                'status' => $response->status(),
                'error' => $this->sanitizeMessage($response->body()),
            ]);

            return [
                'success' => false,
                'enabled' => false,
                'status' => 'attention_required',
                'raw' => [],
            ];
        } catch (\Throwable $e) {
            Log::warning('Cloudflare routing status request exception', [
                'error' => $this->sanitizeMessage($e->getMessage()),
            ]);

            return [
                'success' => false,
                'enabled' => false,
                'status' => 'attention_required',
                'raw' => [],
            ];
        }
    }

    /**
     * List destination addresses for the account.
     *
     * @return array<int, array{id: string, email: string, verified: ?string}>
     */
    public function listDestinationAddresses(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->http()->get("{$this->baseUrl}/accounts/{$this->accountId}/email/routing/addresses");

            if (! $response->successful()) {
                Log::warning('Cloudflare list destination addresses failed', [
                    'status' => $response->status(),
                    'error' => $this->sanitizeMessage($response->body()),
                ]);

                return [];
            }

            $results = $response->json('result') ?? [];

            return array_map(function ($item) {
                return [
                    'id' => (string) ($item['id'] ?? $item['tag'] ?? ''),
                    'email' => strtolower((string) ($item['email'] ?? '')),
                    'verified' => $item['verified'] ?? null,
                ];
            }, $results);
        } catch (\Throwable $e) {
            Log::warning('Cloudflare list destinations exception', [
                'error' => $this->sanitizeMessage($e->getMessage()),
            ]);

            return [];
        }
    }

    /**
     * Find a specific destination address by email.
     */
    public function findDestinationAddress(string $email): ?array
    {
        $normalized = strtolower(trim($email));
        $addresses = $this->listDestinationAddresses();

        foreach ($addresses as $address) {
            if (strtolower($address['email']) === $normalized) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Create/request a destination address through Cloudflare.
     * Cloudflare sends a verification email upon creation.
     */
    public function createDestinationAddress(string $email): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudflare Email Routing API is not configured.');
        }

        $email = strtolower(trim($email));

        // Check if destination already exists
        $existing = $this->findDestinationAddress($email);
        if ($existing !== null) {
            return $existing;
        }

        try {
            $response = $this->http()->post("{$this->baseUrl}/accounts/{$this->accountId}/email/routing/addresses", [
                'email' => $email,
            ]);

            if (! $response->successful()) {
                $errorMsg = $this->extractErrorMessage($response);
                throw new RuntimeException("Cloudflare destination creation failed: {$errorMsg}");
            }

            $result = $response->json('result') ?? [];

            return [
                'id' => (string) ($result['id'] ?? $result['tag'] ?? ''),
                'email' => strtolower((string) ($result['email'] ?? $email)),
                'verified' => $result['verified'] ?? null,
            ];
        } catch (\Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException('Could not connect to Cloudflare to create destination: '.$this->sanitizeMessage($e->getMessage()));
        }
    }

    /**
     * Inspect verification state of a destination address.
     * Returns: verified, pending, failed, or not_configured.
     */
    public function checkDestinationVerification(string $email): string
    {
        if (! $this->isConfigured()) {
            return 'not_configured';
        }

        $address = $this->findDestinationAddress($email);

        if ($address === null) {
            return 'failed';
        }

        return ! empty($address['verified']) ? 'verified' : 'pending';
    }

    /**
     * List custom routing rules for the zone.
     *
     * @return array<int, array>
     */
    public function listRoutingRules(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->http()->get("{$this->baseUrl}/zones/{$this->zoneId}/email/routing/rules");

            if (! $response->successful()) {
                Log::warning('Cloudflare list routing rules failed', [
                    'status' => $response->status(),
                    'error' => $this->sanitizeMessage($response->body()),
                ]);

                return [];
            }

            return $response->json('result') ?? [];
        } catch (\Throwable $e) {
            Log::warning('Cloudflare list routing rules exception', [
                'error' => $this->sanitizeMessage($e->getMessage()),
            ]);

            return [];
        }
    }

    /**
     * Find the routing rule for a specific alias.
     */
    public function findRuleForAlias(string $alias, ?array $rules = null): ?array
    {
        $normalized = strtolower(trim($alias));
        $rules = $rules ?? $this->listRoutingRules();

        foreach ($rules as $rule) {
            $matchers = $rule['matchers'] ?? [];
            foreach ($matchers as $matcher) {
                if (
                    ($matcher['field'] ?? '') === 'to' &&
                    strtolower((string) ($matcher['value'] ?? '')) === $normalized
                ) {
                    return $rule;
                }
            }
        }

        return null;
    }

    /**
     * Inspect custom routing rules to find the current active destination if configured.
     */
    public function getActiveDestinationFromRules(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $rules = $this->listRoutingRules();
        foreach ($rules as $rule) {
            $actions = $rule['actions'] ?? [];
            foreach ($actions as $action) {
                if (($action['type'] ?? '') === 'forward' && ! empty($action['value'][0])) {
                    return strtolower((string) $action['value'][0]);
                }
            }
        }

        return null;
    }

    /**
     * Update routing rules for specified aliases to a new destination.
     * Atomic/Failure Safety: If any alias update fails mid-flight, rolls back all
     * previously modified rules in this batch back to $oldDestination and aborts!
     *
     * @param  string  $newDestination
     * @param  array<string>  $aliases
     * @param  string|null  $oldDestination
     * @return array<string, bool>
     * @throws RuntimeException
     */
    public function updateAliasRules(string $newDestination, array $aliases, ?string $oldDestination = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudflare Email Routing API is not configured.');
        }

        $rules = $this->listRoutingRules();
        $updatedRules = [];
        $results = [];

        try {
            foreach ($aliases as $alias) {
                $alias = strtolower(trim($alias));
                $rule = $this->findRuleForAlias($alias, $rules);

                if ($rule !== null) {
                    $ruleId = $rule['id'] ?? $rule['tag'];
                    $previousActions = $rule['actions'] ?? [];

                    $payload = [
                        'actions' => [
                            [
                                'type' => 'forward',
                                'value' => [$newDestination],
                            ],
                        ],
                        'matchers' => $rule['matchers'] ?? [
                            [
                                'type' => 'literal',
                                'field' => 'to',
                                'value' => $alias,
                            ],
                        ],
                        'enabled' => true,
                        'name' => $rule['name'] ?? "{$alias} forward",
                        'priority' => (int) ($rule['priority'] ?? 0),
                    ];

                    $response = $this->http()->put(
                        "{$this->baseUrl}/zones/{$this->zoneId}/email/routing/rules/{$ruleId}",
                        $payload
                    );

                    if (! $response->successful()) {
                        throw new RuntimeException("Failed to update rule for {$alias}: ".$this->extractErrorMessage($response));
                    }

                    // Track previous state for rollback
                    $updatedRules[$ruleId] = [
                        'alias' => $alias,
                        'previous_actions' => $previousActions,
                        'rule' => $rule,
                    ];
                } else {
                    // Create new rule for alias if it doesn't exist yet
                    $payload = [
                        'actions' => [
                            [
                                'type' => 'forward',
                                'value' => [$newDestination],
                            ],
                        ],
                        'matchers' => [
                            [
                                'type' => 'literal',
                                'field' => 'to',
                                'value' => $alias,
                            ],
                        ],
                        'enabled' => true,
                        'name' => "{$alias} forward",
                        'priority' => 0,
                    ];

                    $response = $this->http()->post(
                        "{$this->baseUrl}/zones/{$this->zoneId}/email/routing/rules",
                        $payload
                    );

                    if (! $response->successful()) {
                        throw new RuntimeException("Failed to create rule for {$alias}: ".$this->extractErrorMessage($response));
                    }

                    $createdResult = $response->json('result') ?? [];
                    $createdId = $createdResult['id'] ?? $createdResult['tag'] ?? null;
                    if ($createdId) {
                        $updatedRules[$createdId] = [
                            'alias' => $alias,
                            'is_created' => true,
                        ];
                    }
                }

                $results[$alias] = true;
            }

            return $results;
        } catch (\Throwable $e) {
            // ATOMIC ROLLBACK (Requirement 11)
            Log::error('Cloudflare alias rule update failed. Initiating rollback of updated rules.', [
                'error' => $this->sanitizeMessage($e->getMessage()),
                'updated_count' => count($updatedRules),
            ]);

            if ($oldDestination !== null && ! empty($updatedRules)) {
                $this->rollbackUpdatedRules($updatedRules, $oldDestination);
            }

            throw new RuntimeException('Cloudflare routing sync aborted: '.$this->sanitizeMessage($e->getMessage()));
        }
    }

    /**
     * Rollback previously updated rules to preserve the previous destination.
     */
    protected function rollbackUpdatedRules(array $updatedRules, string $oldDestination): void
    {
        foreach ($updatedRules as $ruleId => $info) {
            try {
                if (! empty($info['is_created'])) {
                    // Delete rule that was created during this failed transaction
                    $this->http()->delete("{$this->baseUrl}/zones/{$this->zoneId}/email/routing/rules/{$ruleId}");
                } else {
                    // Revert rule actions back to original
                    $rule = $info['rule'] ?? [];
                    $payload = [
                        'actions' => ! empty($info['previous_actions']) ? $info['previous_actions'] : [
                            [
                                'type' => 'forward',
                                'value' => [$oldDestination],
                            ],
                        ],
                        'matchers' => $rule['matchers'] ?? [
                            [
                                'type' => 'literal',
                                'field' => 'to',
                                'value' => $info['alias'],
                            ],
                        ],
                        'enabled' => true,
                        'name' => $rule['name'] ?? "{$info['alias']} forward",
                        'priority' => (int) ($rule['priority'] ?? 0),
                    ];

                    $this->http()->put("{$this->baseUrl}/zones/{$this->zoneId}/email/routing/rules/{$ruleId}", $payload);
                }
            } catch (\Throwable $rollbackError) {
                Log::emergency('Failed to rollback Cloudflare routing rule during failure recovery', [
                    'rule_id' => $ruleId,
                    'error' => $this->sanitizeMessage($rollbackError->getMessage()),
                ]);
            }
        }
    }

    protected function http(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$this->apiToken}",
            'Content-Type' => 'application/json',
        ])->timeout(10);
    }

    protected function extractErrorMessage($response): string
    {
        $json = $response->json();
        if (is_array($json) && ! empty($json['errors'][0]['message'])) {
            return (string) $json['errors'][0]['message'];
        }

        return 'HTTP '.$response->status();
    }

    protected function sanitizeMessage(string $message): string
    {
        if ($this->apiToken && filled($this->apiToken)) {
            $message = str_replace($this->apiToken, '[REDACTED_TOKEN]', $message);
        }

        return preg_replace('/Bearer\s+[A-Za-z0-9_\-\.]+/i', 'Bearer [REDACTED]', $message) ?? $message;
    }
}
