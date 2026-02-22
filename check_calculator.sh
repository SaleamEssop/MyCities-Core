#!/bin/sh
rm -f /tmp/jc
curl -s -c /tmp/jc -b /tmp/jc -o /tmp/lc.html http://localhost/admin/login
T=$(sed -n "s/.*name=\"_token\" value=\"\([^\"]*\)\".*/\1/p" /tmp/lc.html)
curl -s -c /tmp/jc -b /tmp/jc -o /dev/null -X POST http://localhost/admin/login --data-urlencode "_token=$T" --data-urlencode "email=admin@mycities.co.za" --data-urlencode "password=admin123"
echo "--- Fetching calculator page ---"
curl -s -L -c /tmp/jc -b /tmp/jc -o /tmp/calc.html http://localhost/admin/calculator
echo "HTTP done. File size: $(wc -c < /tmp/calc.html)"
echo "--- data-page attribute (first 300 chars) ---"
grep -o 'data-page=[^ >]*' /tmp/calc.html | dd bs=1 count=300 2>/dev/null
echo ""
echo "--- script tags ---"
grep -o '<script[^>]*>' /tmp/calc.html
echo "--- extends/inertia check ---"
grep -c "@inertia\|data-page" /tmp/calc.html || echo "none found"
