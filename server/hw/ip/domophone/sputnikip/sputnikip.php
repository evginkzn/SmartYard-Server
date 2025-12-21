<?php

namespace hw\ip\domophone\sputnikip;

use hw\Interface\CmsLevelsInterface;
use hw\Interface\GateModeInterface;
use hw\ip\domophone\domophone;

/**
 * Class representing a SputnikIP standalone domophone.
 *
 * This class provides integration with SputnikIP devices that use
 * direct REST API communication (as opposed to cloud-based Sputnik devices).
 */
class sputnikip extends domophone implements CmsLevelsInterface, GateModeInterface
{
    use \hw\ip\common\sputnikip\sputnikip;

    /**
     * Mapping of SmartYard CMS models to SputnikIP commutator types.
     */
    protected array $cmsModelMap = [
        'bk-100' => 'VIZIT',
        'com-25u' => 'METACOM',
        'com-100u' => 'METACOM',
        'com-220u' => 'METACOM',
        'km100-7.1' => 'ELTIS',
        'km100-7.2' => 'ELTIS',
        'km100-7.3' => 'ELTIS',
        'km100-7.5' => 'ELTIS',
        'kmg-100' => 'CYFRAL',
        'qad-100' => 'DAXYS',
    ];

    /**
     * Reverse mapping of SputnikIP commutator types to SmartYard CMS models.
     */
    protected array $cmsModelReverseMap = [
        'VIZIT' => 'bk-100',
        'METACOM' => 'com-100u',
        'ELTIS' => 'km100-7.1',
        'CYFRAL' => 'kmg-100',
        'DAXYS' => 'qad-100',
        'BEWARD' => 'bk-100',
    ];

    public function addRfid(string $code, int $apartment = 0): void
    {
        $key = [
            'id' => strtoupper($code),
            'description' => $apartment ? "Flat $apartment" : '',
            'isFrozen' => false,
        ];

        if ($apartment > 0) {
            $key['flatNumber'] = $apartment;
        }

        $this->apiCall('/api/intercom/keys', 'POST', ['keys' => [$key]]);
    }

    public function addRfids(array $rfids): void
    {
        if (empty($rfids)) {
            return;
        }

        $keys = array_map(function ($rfid) {
            return [
                'id' => strtoupper($rfid),
                'description' => '',
                'isFrozen' => false,
            ];
        }, $rfids);

        $this->apiCall('/api/intercom/keys', 'PUT', ['keys' => $keys]);
    }

    public function configureApartment(
        int   $apartment,
        int   $code = 0,
        array $sipNumbers = [],
        bool  $cmsEnabled = true,
        array $cmsLevels = [],
    ): void {
        $sipAddress = '';
        if (!empty($sipNumbers[0])) {
            $sipAddress = $sipNumbers[0];
            if (strpos($sipAddress, '@') === false) {
                // Get SIP config to construct full address
                $sipConfig = $this->apiCall('/api/intercom/sip');
                $server = $sipConfig['sipServer'] ?? '';
                $port = $sipConfig['sipPort'] ?? 5060;
                $sipAddress = "sip:$sipAddress@$server:$port";
            }
        }

        $flat = [
            'num' => $apartment,
            'alias' => (string)$apartment,
            'callVolume' => 80,
            'sipAddress' => $sipAddress,
            'sipEnable' => !empty($sipAddress),
            'analogEnable' => $cmsEnabled,
        ];

        if (!empty($cmsLevels)) {
            $flat['thresholdVoltageCall'] = (float)($cmsLevels[0] ?? 9.99);
            $flat['thresholdVoltageDoor'] = (float)($cmsLevels[1] ?? 9.99);
        }

        $this->apiCall('/api/intercom/flats', 'PUT', ['flats' => [$flat]]);

        // Handle personal code
        if ($code > 0) {
            $codeValue = str_pad((string)$code, 5, '0', STR_PAD_LEFT);
            if (strlen($codeValue) === 4) {
                $codeValue = '0' . $codeValue;
            }

            $this->apiCall('/api/intercom/codes', 'PUT', [
                'codes' => [[
                    'value' => $codeValue,
                    'flatNumber' => $apartment,
                ]],
            ]);
        }
    }

    public function configureEncoding(): void
    {
        // Empty implementation - encoding is handled by device firmware
    }

    public function configureMatrix(array $matrix): void
    {
        if (empty($matrix)) {
            return;
        }

        $flats = [];
        foreach ($matrix as $cell) {
            $hundreds = (int)($cell['hundreds'] ?? 0);
            $tens = (int)($cell['tens'] ?? 0);
            $units = (int)($cell['units'] ?? 0);
            $apartment = (int)($cell['apartment'] ?? 0);

            $alias = $hundreds * 100 + $tens * 10 + $units;

            $flats[] = [
                'num' => $apartment,
                'alias' => (string)$alias,
                'callVolume' => 80,
                'sipAddress' => '',
                'sipEnable' => false,
                'analogEnable' => true,
            ];
        }

        $this->apiCall('/api/intercom/flats', 'PUT', ['flats' => $flats]);
    }

    public function configureSip(
        string $login,
        string $password,
        string $server,
        int    $port = 5060,
        bool   $stunEnabled = false,
        string $stunServer = '',
        int    $stunPort = 3478,
    ): void {
        $this->apiCall('/api/intercom/sip', 'POST', [
            'sipServer' => $server,
            'sipPort' => $port,
            'sipLogin' => $login,
            'sipUsername' => $login,
            'sipPassword' => $password,
            'callTimeout' => 30,
            'dropAnalogCall' => true,
            'dtmfTag' => '1',
            'sosButtonUrl' => '',
        ]);
    }

    public function configureUserAccount(string $password): void
    {
        // User management is handled by setAdminPassword in trait
    }

    public function deleteApartment(int $apartment = 0): void
    {
        if ($apartment === 0) {
            // Delete all - get current flats and delete them
            $response = $this->apiCall('/api/intercom/flats');
            $flats = $response['flats'] ?? [];

            if (!empty($flats)) {
                $toDelete = array_map(fn($flat) => ['num' => $flat['num']], $flats);
                $this->apiCall('/api/intercom/flats', 'DELETE', ['flats' => $toDelete]);
            }
        } else {
            $this->apiCall('/api/intercom/flats', 'DELETE', ['flats' => [['num' => $apartment]]]);
        }
    }

    public function deleteRfid(string $code = ''): void
    {
        if ($code === '') {
            // Delete all keys
            $this->apiCall('/api/intercom/keys?all=true', 'DELETE');
        } else {
            $this->apiCall('/api/intercom/keys', 'DELETE', [
                'keys' => [['id' => strtoupper($code)]],
            ]);
        }
    }

    public function getCmsLevels(): array
    {
        $calls = $this->apiCall('/api/intercom/calls');

        return [
            (float)($calls['defaultThresholdCall'] ?? 5.5),
            (float)($calls['defaultThresholdDoor'] ?? 6.0),
        ];
    }

    public function getLineDiagnostics(int $apartment): string|int|float
    {
        $result = $this->apiCall('/api/intercom/test_line', 'POST', ['flat' => $apartment]);
        return $result['comLineVoltage'] ?? 0;
    }

    public function isGateModeEnabled(): bool
    {
        $result = $this->apiCall('/api/intercom/gate_mode');
        return (bool)($result['gateMode'] ?? false);
    }

    public function openLock(int $lockNumber = 0): void
    {
        $endpoint = $lockNumber === 0
            ? '/api/intercom/open_door'
            : '/api/intercom/open_second_door';

        $this->apiCall($endpoint, 'POST');
    }

    public function setAudioLevels(array $levels): void
    {
        if (count($levels) < 4) {
            return;
        }

        $this->apiCall('/api/intercom/audio', 'POST', [
            'systemVolume' => (int)$levels[0],
            'microphoneVolume' => (int)$levels[1],
            'voiceVolume' => (int)$levels[2],
            'sipVolume' => (int)$levels[3],
        ]);
    }

    public function setCallTimeout(int $timeout): void
    {
        $current = $this->apiCall('/api/intercom/calls');

        $this->apiCall('/api/intercom/calls', 'POST', [
            'flatCallTimeTimeout' => $current['flatCallTimeTimeout'] ?? 60,
            'flatDialingTimeTimeout' => $timeout,
            'defaultThresholdCall' => $current['defaultThresholdCall'] ?? 5.5,
            'defaultThresholdDoor' => $current['defaultThresholdDoor'] ?? 6.0,
            'commutatorType' => $current['commutatorType'] ?? 'VIZIT',
        ]);
    }

    public function setCmsLevels(array $levels): void
    {
        $current = $this->apiCall('/api/intercom/calls');

        $this->apiCall('/api/intercom/calls', 'POST', [
            'flatCallTimeTimeout' => $current['flatCallTimeTimeout'] ?? 60,
            'flatDialingTimeTimeout' => $current['flatDialingTimeTimeout'] ?? 30,
            'defaultThresholdCall' => (float)($levels[0] ?? 5.5),
            'defaultThresholdDoor' => (float)($levels[1] ?? 6.0),
            'commutatorType' => $current['commutatorType'] ?? 'VIZIT',
        ]);
    }

    public function setCmsModel(string $model = ''): void
    {
        $commutatorType = $this->cmsModelMap[$model] ?? 'VIZIT';
        $current = $this->apiCall('/api/intercom/calls');

        $this->apiCall('/api/intercom/calls', 'POST', [
            'flatCallTimeTimeout' => $current['flatCallTimeTimeout'] ?? 60,
            'flatDialingTimeTimeout' => $current['flatDialingTimeTimeout'] ?? 30,
            'defaultThresholdCall' => $current['defaultThresholdCall'] ?? 5.5,
            'defaultThresholdDoor' => $current['defaultThresholdDoor'] ?? 6.0,
            'commutatorType' => $commutatorType,
        ]);
    }

    public function setConciergeNumber(int $sipNumber): void
    {
        // Configure concierge as a special apartment (9999)
        $this->configureApartment($sipNumber, 0, [$sipNumber], false);
    }

    public function setDtmfCodes(
        string $code1 = '1',
        string $code2 = '2',
        string $code3 = '3',
        string $codeCms = '1',
    ): void {
        $current = $this->apiCall('/api/intercom/sip');

        $this->apiCall('/api/intercom/sip', 'POST', [
            'sipServer' => $current['sipServer'] ?? '',
            'sipPort' => $current['sipPort'] ?? 5060,
            'sipLogin' => $current['sipLogin'] ?? '',
            'sipUsername' => $current['sipUsername'] ?? '',
            'sipPassword' => $current['sipPassword'] ?? '',
            'callTimeout' => $current['callTimeout'] ?? 30,
            'dropAnalogCall' => $current['dropAnalogCall'] ?? true,
            'dtmfTag' => $code1,
            'sosButtonUrl' => $current['sosButtonUrl'] ?? '',
        ]);
    }

    public function setGateModeEnabled(bool $enabled): void
    {
        $this->apiCall('/api/intercom/gate_mode', 'POST', ['gateMode' => $enabled]);
    }

    public function setPublicCode(int $code = 0): void
    {
        if ($code === 0) {
            // Disable public code - delete all codes without flatNumber
            $response = $this->apiCall('/api/intercom/codes');
            $codes = $response['codes'] ?? [];

            $toDelete = [];
            foreach ($codes as $c) {
                if (empty($c['flatNumber'])) {
                    $toDelete[] = ['value' => $c['value']];
                }
            }

            if (!empty($toDelete)) {
                $this->apiCall('/api/intercom/codes', 'DELETE', ['codes' => $toDelete]);
            }
        } else {
            $codeValue = str_pad((string)$code, 5, '0', STR_PAD_LEFT);
            if (strlen($codeValue) === 4) {
                $codeValue = '0' . $codeValue;
            }

            $this->apiCall('/api/intercom/codes', 'PUT', [
                'codes' => [[
                    'value' => $codeValue,
                    'flatNumber' => null,
                ]],
            ]);
        }
    }

    public function setSosNumber(int $sipNumber): void
    {
        $current = $this->apiCall('/api/intercom/sip');
        $server = $current['sipServer'] ?? '';
        $port = $current['sipPort'] ?? 5060;

        $this->apiCall('/api/intercom/sip', 'POST', [
            'sipServer' => $server,
            'sipPort' => $port,
            'sipLogin' => $current['sipLogin'] ?? '',
            'sipUsername' => $current['sipUsername'] ?? '',
            'sipPassword' => $current['sipPassword'] ?? '',
            'callTimeout' => $current['callTimeout'] ?? 30,
            'dropAnalogCall' => $current['dropAnalogCall'] ?? true,
            'dtmfTag' => $current['dtmfTag'] ?? '1',
            'sosButtonUrl' => "$sipNumber@$server:$port",
        ]);
    }

    public function setTalkTimeout(int $timeout): void
    {
        $current = $this->apiCall('/api/intercom/calls');

        $this->apiCall('/api/intercom/calls', 'POST', [
            'flatCallTimeTimeout' => $timeout,
            'flatDialingTimeTimeout' => $current['flatDialingTimeTimeout'] ?? 30,
            'defaultThresholdCall' => $current['defaultThresholdCall'] ?? 5.5,
            'defaultThresholdDoor' => $current['defaultThresholdDoor'] ?? 6.0,
            'commutatorType' => $current['commutatorType'] ?? 'VIZIT',
        ]);
    }

    public function setUnlockTime(int $time = 3): void
    {
        $current = $this->apiCall('/api/intercom/door_settings');

        $this->apiCall('/api/intercom/door_settings', 'POST', [
            'doorModeNoNc' => $current['doorModeNoNc'] ?? 0,
            'enableWiegandGeneralDoor' => $current['enableWiegandGeneralDoor'] ?? 1,
            'timeOpenDoor' => $time,
            'timeOpenDoorBle' => $time,
            'timeOpenDoorSocial' => $time,
            'timeOpenDoorSos' => 30,
            'timeOpenAuxDoor' => $time,
        ]);
    }

    protected function getApartments(): array
    {
        $response = $this->apiCall('/api/intercom/flats');
        $flats = $response['flats'] ?? [];
        $codes = $this->getPersonalCodes();

        $apartments = [];
        foreach ($flats as $flat) {
            $num = (int)$flat['num'];

            // Skip empty apartments (no SIP and no analog)
            if (empty($flat['sipAddress']) && empty($flat['analogEnable'])) {
                continue;
            }

            $sipNumber = '';
            if (!empty($flat['sipAddress'])) {
                // Extract SIP number from address (e.g., "sip:100@server:5060" -> "100")
                if (preg_match('/^(?:sip:)?(\d+)@/', $flat['sipAddress'], $matches)) {
                    $sipNumber = $matches[1];
                } else {
                    $sipNumber = $flat['sipAddress'];
                }
            }

            $apartments[$num] = [
                'apartment' => $num,
                'code' => $codes[$num] ?? 0,
                'sipNumbers' => $sipNumber ? [$sipNumber] : [],
                'cmsEnabled' => (bool)($flat['analogEnable'] ?? false),
                'cmsLevels' => [
                    $flat['thresholdVoltageCall'] ?? 9.99,
                    $flat['thresholdVoltageDoor'] ?? 9.99,
                ],
            ];
        }

        return $apartments;
    }

    protected function getAudioLevels(): array
    {
        $audio = $this->apiCall('/api/intercom/audio');

        return [
            (int)($audio['systemVolume'] ?? 80),
            (int)($audio['microphoneVolume'] ?? 70),
            (int)($audio['voiceVolume'] ?? 75),
            (int)($audio['sipVolume'] ?? 80),
        ];
    }

    protected function getCmsModel(): string
    {
        $calls = $this->apiCall('/api/intercom/calls');
        $commutatorType = $calls['commutatorType'] ?? 'VIZIT';

        return $this->cmsModelReverseMap[$commutatorType] ?? 'bk-100';
    }

    protected function getDtmfConfig(): array
    {
        $sip = $this->apiCall('/api/intercom/sip');

        return [
            'code1' => $sip['dtmfTag'] ?? '1',
            'code2' => '2',
            'code3' => '3',
            'codeCms' => '1',
        ];
    }

    protected function getMatrix(): array
    {
        $response = $this->apiCall('/api/intercom/flats');
        $flats = $response['flats'] ?? [];

        $matrix = [];
        foreach ($flats as $flat) {
            $alias = (int)($flat['alias'] ?? 0);
            $num = (int)$flat['num'];

            if ($alias === 0 || $alias === $num) {
                continue;
            }

            $hundreds = (int)floor($alias / 100);
            $tens = (int)floor(($alias % 100) / 10);
            $units = $alias % 10;

            $matrix[] = [
                'hundreds' => $hundreds,
                'tens' => $tens,
                'units' => $units,
                'apartment' => $num,
            ];
        }

        return $matrix;
    }

    /**
     * Get personal codes indexed by apartment number.
     *
     * @return array<int, int>
     */
    protected function getPersonalCodes(): array
    {
        $response = $this->apiCall('/api/intercom/codes');
        $codes = $response['codes'] ?? [];

        $result = [];
        foreach ($codes as $code) {
            $flatNumber = $code['flatNumber'] ?? null;
            if ($flatNumber !== null) {
                $result[(int)$flatNumber] = (int)ltrim($code['value'], '0');
            }
        }

        return $result;
    }

    protected function getRfids(): array
    {
        $response = $this->apiCall('/api/intercom/keys');
        $keys = $response['keys']['edges'] ?? [];

        $rfids = [];
        foreach ($keys as $key) {
            $id = strtoupper($key['id'] ?? '');
            if ($id) {
                // Normalize to 14-character format
                $rfids[$id] = str_pad($id, 14, '0', STR_PAD_LEFT);
            }
        }

        return $rfids;
    }

    protected function getSipConfig(): array
    {
        $sip = $this->apiCall('/api/intercom/sip');

        return [
            'server' => $sip['sipServer'] ?? '',
            'port' => (int)($sip['sipPort'] ?? 5060),
            'login' => $sip['sipLogin'] ?? '',
            'password' => $sip['sipPassword'] ?? '',
            'stunEnabled' => false,
            'stunServer' => '',
            'stunPort' => 3478,
        ];
    }
}
