# Security Policy

## Reporting a Vulnerability

**Please do not create a public GitHub issue for security vulnerabilities.**

Instead, email security concerns to: `safe@mindsafe.co.ke`

Include:
- Type of vulnerability
- Location (file, line number)
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

## Security Best Practices

### API Keys & Secrets
- Never commit API keys, secrets, or tokens
- Use environment variables for sensitive data
- Rotate credentials regularly
- Use separate credentials for sandbox and production

### Database
- All queries use prepared statements
- Input sanitization on all user-facing data
- Output escaping for display

### Authentication
- HTTPS required for all API calls
- Webhook signature verification
- Nonce verification for AJAX requests
- OAuth 2.0 for Vodacom API

## Dependency Updates

- Regular security audits
- Automatic updates for critical vulnerabilities
- Security notices in release notes

## Supported Versions

| Version | Status | Support Until |
|---------|--------|---------------|
| 1.0.x   | Current | 2027-01-01   |
| < 1.0   | Unsupported | - |

## Security Advisories

Security advisories will be published via:
- GitHub Security Advisories
- Release notes
- Email notifications (if subscribed)
