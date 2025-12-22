#!/bin/bash
# validate_paths.sh
# Скрипт для проверки соответствия путей в коде и OpenAPI документации

echo "==================================="
echo "OpenAPI Paths Validation"
echo "==================================="
echo ""

# Извлечение путей из webserver.cpp
echo "Extracting paths from webserver.cpp..."
grep -E 'server\.(Get|Post|Put|Patch|Delete)\(' ../app/web_server/webserver.cpp | \
  sed -E 's/.*server\.(Get|Post|Put|Patch|Delete)\("([^"]+)".*/\2/' | \
  grep "^/api/" | \
  sort -u > /tmp/code_paths.txt

# Извлечение путей из openapi.yaml
echo "Extracting paths from openapi.yaml..."
grep -E '^\s+/api/' openapi.yaml | \
  sed 's/://g' | \
  tr -d ' ' | \
  sort -u > /tmp/openapi_paths.txt

echo ""
echo "==================================="
echo "Results:"
echo "==================================="
echo ""

# Подсчёт путей
CODE_COUNT=$(wc -l < /tmp/code_paths.txt)
OPENAPI_COUNT=$(wc -l < /tmp/openapi_paths.txt)

echo "Total paths in code: $CODE_COUNT"
echo "Total paths in OpenAPI: $OPENAPI_COUNT"
echo ""

# Сравнение
MISSING_IN_OPENAPI=$(comm -23 /tmp/code_paths.txt /tmp/openapi_paths.txt | wc -l)
MISSING_IN_CODE=$(comm -13 /tmp/code_paths.txt /tmp/openapi_paths.txt | wc -l)

if [ $MISSING_IN_OPENAPI -eq 0 ] && [ $MISSING_IN_CODE -eq 0 ]; then
    echo "✅ SUCCESS: All paths match!"
    echo ""
    rm /tmp/code_paths.txt /tmp/openapi_paths.txt
    exit 0
fi

if [ $MISSING_IN_OPENAPI -gt 0 ]; then
    echo "❌ Paths in CODE but NOT in OpenAPI ($MISSING_IN_OPENAPI):"
    echo "-----------------------------------"
    comm -23 /tmp/code_paths.txt /tmp/openapi_paths.txt
    echo ""
fi

if [ $MISSING_IN_CODE -gt 0 ]; then
    echo "⚠️  Paths in OpenAPI but NOT in CODE ($MISSING_IN_CODE):"
    echo "-----------------------------------"
    comm -13 /tmp/code_paths.txt /tmp/openapi_paths.txt
    echo ""
fi

echo "==================================="

# Очистка
rm /tmp/code_paths.txt /tmp/openapi_paths.txt

if [ $MISSING_IN_OPENAPI -gt 0 ]; then
    exit 1
else
    exit 0
fi
