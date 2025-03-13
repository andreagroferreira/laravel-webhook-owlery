# Changelog

All notable changes to `laravel-webhook-owlery` will be documented in this file.

## 1.0.0 - 2025-03-13

- Initial release
- Complete webhook management system for both sending and receiving webhooks
- Support for multiple signature validation methods: HMAC, JWT (optional), API Key, Basic Auth
- Circuit breaker pattern implementation for reliable webhook delivery
- Comprehensive logging and monitoring capabilities
- Rate limiting protection
- Async webhook processing using Laravel queues
- Administrative commands (generate secrets, list endpoints, cleanup)
- Extensive documentation and examples
- Comprehensive unit test coverage with 33 passing tests (feature tests planned for future updates)