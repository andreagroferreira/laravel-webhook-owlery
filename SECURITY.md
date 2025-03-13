# Security Policy

## Supported Versions

The following versions are currently being supported with security updates.

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability within Laravel Webhook Owlery, please send an e-mail to André Ferreira via [andre.ferreira@wizardingcode.io](mailto:andre.ferreira@wizardingcode.io). All security vulnerabilities will be promptly addressed.

### Public PGP Key

You can encrypt your vulnerability report using the following PGP key:

```
// Add your PGP key here if you have one
```

## Security Best Practices

When using Laravel Webhook Owlery, please follow these security best practices:

1. Always use HTTPS for both sending and receiving webhooks
2. Store webhook secrets in your environment variables, not in your code
3. Rotate webhook secrets periodically
4. Use signature validation for all incoming webhooks
5. Enable rate limiting for webhook endpoints
6. Validate all incoming webhook data before processing
7. Keep the package up to date with the latest security patches
8. Use the circuit breaker to prevent cascading failures
9. Monitor webhook activities for unusual patterns that might indicate abuse
10. Consider using Laravel's built-in encryption features for storing sensitive data

Thank you for helping keep Laravel Webhook Owlery secure!