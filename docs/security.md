# Security Notes

## Applied controls

- JWT authentication for protected routes
- Role middleware for customer/admin separation
- Owner-only order access enforcement
- Validation through FormRequest classes
- Security headers on HTTP responses
- Rate limiting for auth endpoints

## Areas to keep hardening in production

- Move the OTP transport off in-memory or public configuration to a real provider secret management flow
- Add explicit rate limiting for order creation endpoints and brute-force protections for login attempts
- Add audit logging and stronger observability around gRPC failures
- Use a dedicated secret manager for production credentials rather than `.env`-based secrets only
