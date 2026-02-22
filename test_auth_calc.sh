#!/bin/sh
# Login and get session cookie, then fetch /admin/calculator
rm -rf /tmp/authtest && mkdir /tmp/authtest

# Get login page
curl -s -c /tmp/authtest/c.txt -o /tmp/authtest/login.html http://localhost/admin/login
FSIZE=$(wc -c < /tmp/authtest/login.html 2>/dev/null || echo "0")
echo "Login page size: $FSIZE"

# Extract CSRF token
TOKEN=$(sed -n 's/.*_token.*value="\([^"]*\)".*/\1/p' /tmp/authtest/login.html | head -1)
echo "CSRF token: $TOKEN"

# Post login
curl -s -c /tmp/authtest/c.txt -b /tmp/authtest/c.txt \
  -X POST http://localhost/admin/login \
  -d "_token=$TOKEN&email=admin%40mycities.co.za&password=admin123" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -o /tmp/authtest/after_login.html \
  -w "Login HTTP: %{http_code} -> %{redirect_url}\n"

# Follow redirect to /admin
curl -s -L -c /tmp/authtest/c.txt -b /tmp/authtest/c.txt \
  -o /tmp/authtest/dashboard.html \
  -w "Dashboard HTTP: %{http_code}\n" \
  http://localhost/admin

# Now fetch /admin/calculator (should be authenticated)
curl -s -c /tmp/authtest/c.txt -b /tmp/authtest/c.txt \
  -o /tmp/authtest/calc.html \
  -w "Calculator HTTP: %{http_code}\n" \
  http://localhost/admin/calculator

CSIZE=$(wc -c < /tmp/authtest/calc.html 2>/dev/null || echo "0")
echo "Calculator page size: $CSIZE"
echo "=== data-page present? ==="
grep -c "data-page=" /tmp/authtest/calc.html && echo "YES" || echo "NO (missing!)"
echo "=== URL in page JSON ==="
grep -o '"url":"[^"]*"' /tmp/authtest/calc.html | head -2
echo "=== Component ==="
grep -o '"component":"[^"]*"' /tmp/authtest/calc.html
echo "=== Script src ==="
grep -o 'src="[^"]*inertia[^"]*"' /tmp/authtest/calc.html
