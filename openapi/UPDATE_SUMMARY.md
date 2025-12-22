# OpenAPI Documentation Update Summary

## Changes Made

### 1. ✅ Removed Obsolete Camera Time Endpoint

**Issue:** The `/api/camera/time` endpoint was documented in OpenAPI but no longer exists in the implementation.

**Action:**
- Removed reference from `openapi.yaml` (lines 193-194)
- Note: The `camera/time.yaml` file can be deleted separately if needed

**Reason:** Time endpoints were moved to `/api/device/time/*` (current, ntp, timezone)

---

### 2. ✅ Fixed Certificate Endpoint Path Inconsistencies

**Issue:** Certificate endpoints had inconsistent paths between implementation and documentation.

**Implementation Routes (webserver.cpp):**
- `POST /api/device/certificates/webserver` - Upload certificate
- `GET /api/device/webserver/certificates` - Get certificate info
- `DELETE /api/device/certificates/webserver` - Delete certificate

**Old OpenAPI Path:**
- `/api/device/certificates` → `paths/device/certificates.yaml`

**Actions Taken:**
1. Removed old `/api/device/certificates` reference from `openapi.yaml`
2. Created two new path references:
   - `/api/device/certificates/webserver` → `paths/device/certificates_webserver.yaml`
   - `/api/device/webserver/certificates` → `paths/device/webserver_certificates.yaml`
3. Created new path files:
   - `paths/device/certificates_webserver.yaml` - Documents POST and DELETE operations
   - `paths/device/webserver_certificates.yaml` - Documents GET operation

**Note:** The old `certificates.yaml` file can be kept as backup or deleted.

---

### 3. ✅ Removed Obsolete HTTPS/Metrics Endpoints

**Issue:** Three endpoints were documented in OpenAPI but don't exist in webserver.cpp

**Removed References:**
- `/api/device/https` - Obsolete/stub endpoint
- `/api/device/https/toggle` - Replaced by service settings
- `/api/device/metrics` - Obsolete/stub endpoint

**Action:**
- Removed all three references from `openapi.yaml`

**Current HTTPS Control:**
HTTPS is now managed through `/api/device/service` endpoint with `https_enabled` and `https_port` fields.

---

## Verification Results

### Routes Coverage

**Total API routes in webserver.cpp:** 57
**Total API paths in openapi.yaml:** 49

### Routes NOT in OpenAPI (Intentionally Excluded)

**1. `/api/camera/config` (1 route)**
- Status: Commented out in webserver.cpp (lines 413-414)
- Reason: Inactive/deprecated endpoint

**2. Sputnik MCU Endpoints (7 routes)**
- `/api/sputnik/v1/register`
- `/api/sputnik/v1/config`
- `/api/sputnik/v1/flats_config`
- `/api/sputnik/v1/keys`
- `/api/sputnik/v1/digital_keys`
- `/api/sputnik/v1/sound_configs`
- `/api/sputnik/v1/keys_assembly`

**Reason:** These are internal MCU communication endpoints:
- Restricted to localhost only (127.0.0.1)
- Not meant for public API documentation
- Used by on-device microcontroller only

---

## Files Modified

### Updated Files
1. `openapi/openapi.yaml` - Main spec file
   - Removed camera/time reference
   - Fixed certificate endpoint paths
   - Removed obsolete HTTPS/metrics endpoints

### New Files Created
1. `openapi/paths/device/certificates_webserver.yaml` - POST/DELETE operations
2. `openapi/paths/device/webserver_certificates.yaml` - GET operation

### Files to Clean Up (Optional)
1. `openapi/paths/camera/time.yaml` - Can be deleted (obsolete)
2. `openapi/paths/device/certificates.yaml` - Can be deleted (replaced)
3. `openapi/paths/device/https.yaml` - Can be deleted (obsolete)
4. `openapi/paths/device/https_toggle.yaml` - Can be deleted (obsolete)
5. `openapi/paths/device/metrics.yaml` - Can be deleted (obsolete)

---

## Validation

### ✅ All Public API Endpoints Documented

Every publicly accessible endpoint in webserver.cpp is now documented in openapi.yaml:

**Device Endpoints (19):**
- ✓ /config-agent.json
- ✓ /api/device/status
- ✓ /api/device/reboot
- ✓ /api/device/reset
- ✓ /api/device/standalone
- ✓ /api/device/network
- ✓ /api/device/users (GET, PUT, PATCH, DELETE)
- ✓ /api/device/upgrade
- ✓ /api/device/upgrade_ota
- ✓ /api/device/upgrade_mcu_fw
- ✓ /api/device/upgrade_mcu_bl
- ✓ /api/device/logs
- ✓ /api/device/service
- ✓ /api/device/certificates/webserver (POST, DELETE)
- ✓ /api/device/webserver/certificates (GET)
- ✓ /api/device/time/ntp
- ✓ /api/device/time/timezone
- ✓ /api/device/time/current

**Intercom Endpoints (20):**
- ✓ /api/intercom/config
- ✓ /api/intercom/audio
- ✓ /api/intercom/open_door
- ✓ /api/intercom/open_second_door
- ✓ /api/intercom/door_settings
- ✓ /api/intercom/sip
- ✓ /api/intercom/sip/security
- ✓ /api/intercom/sip/certificates
- ✓ /api/intercom/sip/certificates/upload
- ✓ /api/intercom/calls
- ✓ /api/intercom/sos
- ✓ /api/intercom/flats (GET, POST, PATCH, DELETE)
- ✓ /api/intercom/test_call_sip
- ✓ /api/intercom/test_call_analog
- ✓ /api/intercom/test_line
- ✓ /api/intercom/keys (GET, POST, PATCH, DELETE)
- ✓ /api/intercom/keys/config
- ✓ /api/intercom/keys/collect
- ✓ /api/intercom/codes (GET, POST, DELETE)
- ✓ /api/intercom/gate_mode
- ✓ /api/intercom/gate

**Camera Endpoints (10):**
- ✓ /api/camera/encode
- ✓ /api/camera/image
- ✓ /api/camera/audio
- ✓ /api/camera/osd
- ✓ /api/camera/motion
- ✓ /api/camera/set_day_mode
- ✓ /api/camera/set_night_mode
- ✓ /api/camera/set_auto_mode
- ✓ /api/camera/set_ircut_auto_mode
- ✓ /api/camera/mode_config
- ✓ /api/camera/snapshot.jpg

**Total Documented: 49 public API endpoints**

---

## Recommendations

### 1. Clean Up Obsolete Files
Remove the following unused YAML files from `openapi/paths/`:
```bash
rm openapi/paths/camera/time.yaml
rm openapi/paths/device/certificates.yaml
rm openapi/paths/device/https.yaml
rm openapi/paths/device/https_toggle.yaml
rm openapi/paths/device/metrics.yaml
```

### 2. Rebuild OpenAPI Bundle
If you use a bundled/compiled OpenAPI spec, regenerate it:
```bash
# Example with swagger-cli or openapi-generator
swagger-cli bundle openapi/openapi.yaml -o openapi/openapi-bundled.yaml
```

### 3. Update API Documentation Website
Redeploy documentation if hosted externally (Swagger UI, ReDoc, etc.)

---

## Next Steps

1. **Validate OpenAPI Spec:**
   ```bash
   npx @redocly/cli lint openapi/openapi.yaml
   # or
   swagger-cli validate openapi/openapi.yaml
   ```

2. **Test Documentation:**
   - Open in Swagger UI or ReDoc
   - Verify all endpoints render correctly
   - Check examples and schemas

3. **Update CHANGELOG:**
   Document these API documentation updates in project changelog

---

## Summary

✅ **All public API endpoints are now accurately documented in OpenAPI spec**
✅ **Removed 4 obsolete/stub endpoints**
✅ **Fixed certificate endpoint path inconsistencies**
✅ **Internal MCU endpoints intentionally excluded from public docs**

The OpenAPI specification now matches the actual web server implementation in `webserver.cpp`.
