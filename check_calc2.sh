#!/bin/sh
rm -f /tmp/jx /tmp/lx.html
curl -s -c /tmp/jx -b /tmp/jx -o /tmp/lx.html http://localhost/admin/login
T=$(sed -n "s/.*name=\"_token\" value=\"\([^\"]*\)\".*/\1/p" /tmp/lx.html)
curl -s -c /tmp/jx -b /tmp/jx -o /dev/null -X POST http://localhost/admin/login --data-urlencode "_token=$T" --data-urlencode "email=admin@mycities.co.za" --data-urlencode "password=admin123"
curl -s -L -c /tmp/jx -b /tmp/jx -o /tmp/calc.html http://localhost/admin/calculator
echo "=== Script src ==="
grep -o 'src="[^"]*inertia[^"]*"' /tmp/calc.html
echo "=== data-page present? ==="
grep -c "data-page=" /tmp/calc.html
echo "=== component name ==="
grep -o '"component":"[^"]*"' /tmp/calc.html
