# OpenAPI Discrepancies Analysis

## Issues Found

### 1. Certificate Endpoint Path Inconsistency

**In webserver.cpp:**
- `POST /api/device/certificates/webserver` (line 361)
- `GET /api/device/webserver/certificates` (line 362)
- `DELETE /api/device/certificates/webserver` (line 363)

**In openapi.yaml:**
- `/api/device/certificates` (line 89) → references `./paths/device/certificates.yaml`

**Problem:**
- The actual routes have `/webserver` subpath which is not reflected in openapi.yaml
- The GET route has reversed path order (`webserver/certificates` vs `certificates/webserver`)

**Solution:**
- Create two separate path entries in openapi.yaml:
  - `/api/device/certificates/webserver` for POST and DELETE
  - `/api/device/webserver/certificates` for GET
- OR document as-is with both path variations

### 2. Obsolete Camera Time Endpoint

**In openapi.yaml:**
- Lines 193-194: References `/api/camera/time` → `./paths/camera/time.yaml`

**In webserver.cpp:**
- No route registered for `/api/camera/time`
- Comment at line 413-414 shows this was moved to `/api/device/time/*`

**Problem:**
- Dead endpoint documented in OpenAPI that doesn't exist in implementation

**Solution:**
- Remove lines 193-194 from openapi.yaml
- Remove file `openapi/paths/camera/time.yaml`

### 3. Camera Mode Setting Endpoints

**In webserver.cpp (lines 426-429):**
- `POST /api/camera/set_day_mode`
- `POST /api/camera/set_night_mode`
- `POST /api/camera/set_auto_mode`
- `POST /api/camera/set_ircut_auto_mode`

**In openapi.yaml (lines 181-188):**
- All appear to be documented ✓

**Status:** OK

## Summary

**Critical Issues:**
1. Certificate endpoints path mismatch (affects API usability)
2. Dead camera/time endpoint (misleading documentation)

**Total Issues:** 2 critical

**Files to Modify:**
1. `openapi/openapi.yaml` - Fix certificate paths, remove camera/time reference
2. `openapi/paths/device/certificates.yaml` - Update or split to match actual paths
3. `openapi/paths/camera/time.yaml` - DELETE (obsolete)

**Optional:** Create separate path files for better organization:
- `openapi/paths/device/certificates_webserver.yaml` (POST/DELETE)
- `openapi/paths/device/webserver_certificates.yaml` (GET)
