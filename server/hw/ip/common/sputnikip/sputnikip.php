<?php

namespace hw\ip\common\sputnikip;

/**
 * Trait providing common functionality for SputnikIP standalone devices.
 *
 * Unlike cloud-based Sputnik devices that use GraphQL API,
 * SputnikIP devices use direct REST API with Basic Auth.
 *
 * Credentials format: "login:password" or just "password" (uses default login "admin").
 */
trait sputnikip
{
    /**
     * @var bool Flag indicating if credentials have been parsed.
     */
    protected bool $credentialsParsed = false;

    /**
     * Parse credentials from password field.
     *
     * Supports formats:
     * - "password" - uses default login (admin)
     * - "login:password" - uses custom login and password
     *
     * @return void
     */
    protected function parseCredentials(): void
    {
        if ($this->credentialsParsed) {
            return;
        }

        $this->credentialsParsed = true;

        // Check if password contains login:password format
        if (str_contains($this->password, ':')) {
            $parts = explode(':', $this->password, 2);
            $this->login = $parts[0];
            $this->password = $parts[1];
        }
    }

    /**
     * Make an API call to the device.
     *
     * @param string $endpoint API endpoint (e.g., '/api/device/status').
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE).
     * @param array $data Request body data for POST/PUT/PATCH requests.
     * @param int $timeout Request timeout in seconds.
     *
     * @return array Decoded JSON response or empty array on failure.
     */
    protected function apiCall(
        string $endpoint,
        string $method = 'GET',
        array $data = [],
        int $timeout = 30,
    ): array {
        // Parse credentials on first API call
        $this->parseCredentials();

        $url = rtrim($this->url, '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->login:$this->password");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;

            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;

            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;

            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return [];
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function configureEventServer(string $url): void
    {
        ['host' => $server, 'port' => $port] = parse_url_ext($url);

        // Get current service config to preserve other settings
        $currentConfig = $this->apiCall('/api/device/service');

        $this->apiCall('/api/device/service', 'POST', array_merge($currentConfig, [
            'syslogServer' => $server,
            'syslogPort' => (int)$port,
            'syslogLevel' => 'info',
        ]));
    }

    public function configureNtp(string $server, int $port = 123, string $timezone = 'Europe/Moscow'): void
    {
        // Configure NTP servers
        $this->apiCall('/api/device/time/ntp', 'POST', [
            'enabled' => true,
            'servers' => [$server],
        ]);

        // Configure timezone
        $this->apiCall('/api/device/time/timezone', 'POST', [
            'timezone' => $timezone,
        ]);
    }

    public function getSysinfo(): array
    {
        $status = $this->apiCall('/api/device/status');

        return [
            'DeviceID' => $status['serialIntercom'] ?? $status['macAddr'] ?? '',
            'DeviceModel' => 'SputnikIP',
            'HardwareVersion' => $status['hwVersion'] ?? '',
            'SoftwareVersion' => $status['swVersion'] ?? '',
        ];
    }

    public function reboot(): void
    {
        $this->apiCall('/api/device/reboot', 'POST');
    }

    public function reset(): void
    {
        $this->apiCall('/api/device/reset', 'POST', [
            'retainNetwork' => true,
        ]);
    }

    public function setAdminPassword(string $password): void
    {
        $this->apiCall('/api/device/users', 'PATCH', [
            'username' => $this->login,
            'password' => $password,
        ]);
    }

    public function syncData(): void
    {
        // Empty implementation - batch sync not required for REST API
    }

    public function transformDbConfig(array $dbConfig): array
    {
        return $dbConfig;
    }

    protected function getEventServer(): string
    {
        $config = $this->apiCall('/api/device/service');

        $server = $config['syslogServer'] ?? '127.0.0.1';
        $port = $config['syslogPort'] ?? 514;

        return "syslog.udp:$server:$port";
    }

    protected function getNtpConfig(): array
    {
        $ntp = $this->apiCall('/api/device/time/ntp');
        $tz = $this->apiCall('/api/device/time/timezone');

        $servers = $ntp['servers'] ?? [];

        return [
            'server' => $servers[0] ?? '',
            'port' => 123,
            'timezone' => $tz['timezone'] ?? 'Europe/Moscow',
        ];
    }

    protected function initializeProperties(): void
    {
        $this->login = 'admin';
        $this->defaultPassword = 'adminpass';
    }
}
