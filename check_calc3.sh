#!/bin/sh
rm -rf /tmp/jx2
mkdir -p /tmp/jx2
curl -s -c /tmp/jx2/cookies.txt -b /tmp/jx2/cookies.txt -o /tmp/jx2/login.html http://localhost/admin/login
echo "Login page size: $(wc -c < /tmp/jx2/login.html)"
T=$(sed -n "s/.*name=\"_token\" value=\"\([^\"]*\)\".*/\1/p" /tmp/jx2/login.html)
echo "Token: $T"
curl -s -c /tmp/jx2/cookies.txt -b /tmp/jx2/cookies.txt -o /tmp/jx2/after_login.html -X POST http://localhost/admin/login --data-urlencode "_token=$T" --data-urlencode "email=admin@mycities.co.za" --data-urlencode "password=admin123"
echo "After login size: $(wc -c < /tmp/jx2/after_login.html)"
curl -s -L -c /tmp/jx2/cookies.txt -b /tmp/jx2/cookies.txt -o /tmp/jx2/calc.html http://localhost/admin/calculator
echo "Calculator page size: $(wc -c < /tmp/jx2/calc.html)"
echo "=== Script src ==="
grep -o 'src="[^"]*"' /tmp/jx2/calc.html | grep -i inertia
echo "=== data-page present? ==="
grep -c "data-page=" /tmp/jx2/calc.html && echo "YES" || echo "NO"
echo "=== component ==="
grep -o '"component":"[^"]*"' /tmp/jx2/calc.html
