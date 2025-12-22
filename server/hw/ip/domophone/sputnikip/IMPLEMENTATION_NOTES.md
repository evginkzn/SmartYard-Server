# SputnikIP Integration Notes

## Обзор

SputnikIP — standalone IP-домофон с прямым REST API управлением (без облака).

## Отличия от Sputnik Cloud

| Аспект | Sputnik (облако) | SputnikIP (standalone) |
|--------|------------------|------------------------|
| API | GraphQL через облако | REST API на устройстве |
| URL | `https://cloud.sputnik.ru/{id}` | `http://192.168.x.x` |
| Авторизация | Bearer token | Basic Auth |
| События | Webhooks | Syslog UDP |
| Управление | Через облачную платформу | Напрямую |

## Структура файлов

```
server/hw/ip/
├── common/sputnikip/
│   └── sputnikip.php          # Trait с базовыми HTTP методами
├── domophone/
│   ├── models/sputnikip.json  # JSON конфиг модели
│   └── sputnikip/
│       └── sputnikip.php      # Основной класс устройства
```

## API Endpoints (OpenAPI документация в /openapi/)

### Device
| Endpoint | Method | Описание |
|----------|--------|----------|
| `/api/device/status` | GET | Статус устройства (sysinfo) |
| `/api/device/reboot` | POST | Перезагрузка |
| `/api/device/reset` | POST | Сброс на заводские (retainNetwork) |
| `/api/device/users` | GET/PUT/PATCH/DELETE | Управление пользователями |
| `/api/device/time/ntp` | GET/POST | Настройки NTP |
| `/api/device/time/timezone` | GET/POST | Часовой пояс |
| `/api/device/service` | GET/POST | Сервисные настройки (syslog!) |

### Intercom
| Endpoint | Method | Описание |
|----------|--------|----------|
| `/api/intercom/flats` | GET/POST/PUT/PATCH/DELETE | Квартиры (PUT = upsert) |
| `/api/intercom/keys` | GET/POST/PUT/PATCH/DELETE | RFID ключи |
| `/api/intercom/codes` | GET/POST/PUT/DELETE | Цифровые коды открытия |
| `/api/intercom/sip` | GET/POST | SIP настройки + DTMF |
| `/api/intercom/audio` | GET/POST | Уровни громкости (4 канала) |
| `/api/intercom/calls` | GET/POST | Таймауты, CMS тип, пороги |
| `/api/intercom/door_settings` | GET/POST | Настройки двери/замка |
| `/api/intercom/open_door` | POST | Открыть основную дверь |
| `/api/intercom/open_second_door` | POST | Открыть вторую дверь |
| `/api/intercom/gate_mode` | GET/POST | Режим калитки |
| `/api/intercom/test_line` | POST | Диагностика аналоговой линии |
| `/api/intercom/sos` | GET/POST | Настройки SOS кнопки |

## Маппинг методов SmartYard -> API

### Trait sputnikip (common)

```php
trait sputnikip {
    // Инициализация
    protected function initializeProperties(): void {
        $this->login = 'admin';
        $this->defaultPassword = 'adminpass';
    }

    // Парсинг credentials (login:password или просто password)
    protected function parseCredentials(): void {
        if (str_contains($this->password, ':')) {
            [$this->login, $this->password] = explode(':', $this->password, 2);
        }
    }

    // HTTP клиент
    protected function apiCall(string $endpoint, string $method = 'GET', array $data = []): array

    // Device
    public function ping(): bool                    // GET /api/device/status
    public function reboot(): void                  // POST /api/device/reboot
    public function reset(): void                   // POST /api/device/reset
    public function getSysinfo(): array             // GET /api/device/status
    public function setAdminPassword(): void        // PATCH /api/device/users

    // Event Server (syslog)
    public function configureEventServer(): void    // POST /api/device/service
    protected function getEventServer(): string     // GET /api/device/service

    // NTP
    public function configureNtp(): void            // POST /api/device/time/ntp + /timezone
    protected function getNtpConfig(): array        // GET /api/device/time/ntp + /timezone
}
```

### Class sputnikip (domophone)

```php
class sputnikip extends domophone {
    use \hw\ip\common\sputnikip\sputnikip;

    // Квартиры
    protected function getApartments(): array       // GET /api/intercom/flats
    public function configureApartment(): void      // PUT /api/intercom/flats (upsert)
    public function deleteApartment(): void         // DELETE /api/intercom/flats

    // RFID ключи
    protected function getRfids(): array            // GET /api/intercom/keys
    public function addRfid(): void                 // POST /api/intercom/keys
    public function addRfids(): void                // PUT /api/intercom/keys (bulk upsert)
    public function deleteRfid(): void              // DELETE /api/intercom/keys

    // SIP
    protected function getSipConfig(): array        // GET /api/intercom/sip
    public function configureSip(): void            // POST /api/intercom/sip

    // Замки
    public function openLock(): void                // POST /api/intercom/open_door
    public function setUnlockTime(): void           // POST /api/intercom/door_settings

    // Аудио
    protected function getAudioLevels(): array      // GET /api/intercom/audio
    public function setAudioLevels(): void          // POST /api/intercom/audio

    // Звонки
    public function setCallTimeout(): void          // POST /api/intercom/calls (flatDialingTimeTimeout)
    public function setTalkTimeout(): void          // POST /api/intercom/calls (flatCallTimeTimeout)

    // CMS
    protected function getCmsModel(): string        // GET /api/intercom/calls (commutatorType)
    public function setCmsModel(): void             // POST /api/intercom/calls (commutatorType)
    protected function getMatrix(): array           // GET /api/intercom/flats (alias поле)
    public function configureMatrix(): void         // PUT /api/intercom/flats (alias поле)

    // DTMF
    protected function getDtmfConfig(): array       // GET /api/intercom/sip (dtmfTag)
    public function setDtmfCodes(): void            // POST /api/intercom/sip (dtmfTag)

    // SOS
    public function setSosNumber(): void            // POST /api/intercom/sip (sosButtonUrl)

    // Коды открытия
    public function setPublicCode(): void           // PUT /api/intercom/codes

    // Диагностика
    public function getLineDiagnostics(): float     // POST /api/intercom/test_line

    // Опциональные интерфейсы
    // GateModeInterface
    public function isGateModeEnabled(): bool       // GET /api/intercom/gate_mode
    public function setGateModeEnabled(): void      // POST /api/intercom/gate_mode

    // CmsLevelsInterface
    public function getCmsLevels(): array           // GET /api/intercom/calls (thresholds)
    public function setCmsLevels(): void            // POST /api/intercom/calls (thresholds)
}
```

## Формат данных API

### Квартиры (flats)
```json
{
  "flats": [{
    "num": 1,
    "alias": "101",              // CMS матрица (аналоговый номер)
    "callVolume": 80,
    "sipAddress": "sip:100@pbx.local:5060",
    "sipEnable": true,
    "analogEnable": true,
    "thresholdVoltageCall": 9.99,
    "thresholdVoltageDoor": 9.99
  }]
}
```

### RFID ключи (keys)
```json
{
  "keys": [{
    "id": "0A40D8E4",            // HEX формат
    "description": "Ключ кв.1",
    "flatNumber": 1,             // Привязка к квартире
    "isFrozen": false
  }]
}
```

### SIP настройки
```json
{
  "sipServer": "pbx.local",
  "sipPort": 5060,
  "sipLogin": "100",
  "sipUsername": "100",
  "sipPassword": "secret",
  "callTimeout": 30,
  "dtmfTag": "1",
  "sosButtonUrl": "sos@pbx.local",
  "dropAnalogCall": true
}
```

### Аудио уровни
```json
{
  "systemVolume": 80,
  "microphoneVolume": 70,
  "voiceVolume": 75,
  "sipVolume": 80
}
```

### CMS и таймауты (calls)
```json
{
  "flatCallTimeTimeout": 60,      // Talk timeout (сек)
  "flatDialingTimeTimeout": 30,   // Call timeout (сек)
  "defaultThresholdCall": 5.5,
  "defaultThresholdDoor": 6.0,
  "commutatorType": "VIZIT"       // VIZIT, CYFRAL, METACOM, ELTIS, DAXYS, BEWARD
}
```

### Event Server (syslog)
```json
{
  "syslogServer": "192.168.1.100",
  "syslogPort": 514,
  "syslogLevel": "info"
}
```

## JSON модель

```json
// server/hw/ip/domophone/models/sputnikip.json
{
    "title": "Sputnik IP",
    "vendor": "SPUTNIKIP",
    "model": "SputnikIP",
    "class": "sputnikip",
    "eventServer": "sputnikip",
    "outputs": 2,
    "camera": "sputnikip",
    "useSmartConfigurator": true,
    "cmses": [
        "bk-100",
        "com-25u",
        "com-100u",
        "com-220u",
        "km100-7.1",
        "km100-7.2",
        "km100-7.3",
        "km100-7.5",
        "kmg-100",
        "qad-100"
    ]
}
```

## Особенности реализации

### 1. Авторизация
- По умолчанию: логин `admin`, пароль `adminpass`
- Basic Auth: `Authorization: Basic base64(login:password)`
- **Формат поля "Авторизация" в SmartYard:**
  - `password` — использует дефолтный логин `admin`
  - `login:password` — использует кастомные логин и пароль
- Примеры:
  - `adminpass` → login=admin, password=adminpass
  - `admin:adminpass` → login=admin, password=adminpass
  - `apiuser:secret123` → login=apiuser, password=secret123

### 2. Формат ключей RFID
- Формат: HEX строка (например: `0A40D8E4`)
- Привязка к квартире через `flatNumber`
- Поддержка заморозки ключа (`isFrozen`)

### 3. CMS матрица
- Реализуется через поле `alias` в квартирах
- `alias` = аналоговый номер для CMS коммутатора

### 4. Event Server
- Только syslog (как и большинство standalone устройств)
- Настраивается через `/api/device/service`
- Формат URL: `syslog.udp:192.168.1.100:514`

### 5. PUT операции (upsert)
- API поддерживает PUT для flats, keys, codes
- Создаёт новые записи или обновляет существующие
- Упрощает реализацию syncData()

## Прогресс реализации

### Этап 1: Common Trait ✅ ВЫПОЛНЕН

**Файл:** `server/hw/ip/common/sputnikip/sputnikip.php`

Реализованные методы:
- [x] `initializeProperties()` — логин `admin`, пароль `adminpass`
- [x] `parseCredentials()` — парсинг формата `login:password`
- [x] `apiCall()` — HTTP клиент с Basic Auth (cURL)
- [x] `getSysinfo()` — GET /api/device/status
- [x] `reboot()` — POST /api/device/reboot
- [x] `reset()` — POST /api/device/reset (с retainNetwork=true)
- [x] `setAdminPassword()` — PATCH /api/device/users
- [x] `configureEventServer()` — POST /api/device/service (syslog)
- [x] `getEventServer()` — GET /api/device/service
- [x] `configureNtp()` — POST /api/device/time/ntp + /timezone
- [x] `getNtpConfig()` — GET /api/device/time/ntp + /timezone
- [x] `syncData()` — пустая реализация (REST API не требует батчинга)
- [x] `transformDbConfig()` — базовая реализация

**Примечание:** Метод `ping()` наследуется от базового класса `ip` и использует `getSysinfo()`.

### Этап 2: Domophone Class ✅ ВЫПОЛНЕН

**Файл:** `server/hw/ip/domophone/sputnikip/sputnikip.php`

Реализованные методы:

**Квартиры (flats):**
- [x] `getApartments()` — GET /api/intercom/flats + /codes
- [x] `configureApartment()` — PUT /api/intercom/flats (upsert)
- [x] `deleteApartment()` — DELETE /api/intercom/flats

**RFID ключи:**
- [x] `getRfids()` — GET /api/intercom/keys
- [x] `addRfid()` — POST /api/intercom/keys
- [x] `addRfids()` — PUT /api/intercom/keys (bulk upsert)
- [x] `deleteRfid()` — DELETE /api/intercom/keys (?all=true для всех)

**SIP:**
- [x] `getSipConfig()` — GET /api/intercom/sip
- [x] `configureSip()` — POST /api/intercom/sip

**Замки:**
- [x] `openLock(0)` — POST /api/intercom/open_door
- [x] `openLock(1)` — POST /api/intercom/open_second_door
- [x] `setUnlockTime()` — POST /api/intercom/door_settings

**Аудио:**
- [x] `getAudioLevels()` — GET /api/intercom/audio
- [x] `setAudioLevels()` — POST /api/intercom/audio

**CMS:**
- [x] `getCmsModel()` — GET /api/intercom/calls → commutatorType
- [x] `setCmsModel()` — POST /api/intercom/calls → commutatorType
- [x] `getMatrix()` — GET /api/intercom/flats → alias
- [x] `configureMatrix()` — PUT /api/intercom/flats → alias

**Таймауты и DTMF:**
- [x] `setCallTimeout()` — POST /api/intercom/calls → flatDialingTimeTimeout
- [x] `setTalkTimeout()` — POST /api/intercom/calls → flatCallTimeTimeout
- [x] `getDtmfConfig()` — GET /api/intercom/sip → dtmfTag
- [x] `setDtmfCodes()` — POST /api/intercom/sip → dtmfTag

**GateModeInterface:**
- [x] `isGateModeEnabled()` — GET /api/intercom/gate_mode
- [x] `setGateModeEnabled()` — POST /api/intercom/gate_mode

**CmsLevelsInterface:**
- [x] `getCmsLevels()` — GET /api/intercom/calls → thresholds
- [x] `setCmsLevels()` — POST /api/intercom/calls → thresholds

**Прочее:**
- [x] `setSosNumber()` — POST /api/intercom/sip → sosButtonUrl
- [x] `setConciergeNumber()` — через configureApartment()
- [x] `setPublicCode()` — PUT/DELETE /api/intercom/codes
- [x] `getLineDiagnostics()` — POST /api/intercom/test_line
- [x] `configureEncoding()` — пустая реализация
- [x] `configureUserAccount()` — пустая реализация

### Этап 3: JSON Model ✅ ВЫПОЛНЕН

**Файл:** `server/hw/ip/domophone/models/sputnikip.json`

```json
{
    "title": "Sputnik IP",
    "vendor": "SPUTNIK",
    "model": "SputnikIP",
    "class": "sputnikip",
    "eventServer": "sputnikip",
    "outputs": 2,
    "camera": "sputnikip",
    "useSmartConfigurator": true,
    "cmses": ["bk-100", "com-25u", "com-100u", ...]
}
```

- [x] Создать файл конфигурации модели

### Этап 4: Syslog Event Handler — В ОЖИДАНИИ

**Файлы:**
- [ ] `server/services/event/services/SputnikIpService.js`
- [ ] Обновить `server/services/event/index.js`
- [ ] Обновить `server/services/event/constants.js`
- [ ] Добавить конфигурацию в `config.json`

### Этап 5: Тестирование — В ОЖИДАНИИ

- [ ] Тестирование на реальном устройстве
