# User Balance API

RESTful API for user balance management with two-factor transfer confirmation, built with Laravel 11.

## Features

- Two-step balance transfers (initiate → confirm with token)
- Admin-only balance management
- SERIALIZABLE transaction isolation for financial operations
- BCMath for precise decimal calculations
- Brute-force protection and rate limiting
- Complete Swagger/OpenAPI documentation

## Quick Start

```bash
# With Docker
docker-compose up -d
docker-compose exec app php artisan migrate
docker-compose exec app php artisan test

# Access
API: http://localhost:8000
Swagger UI: http://localhost:8000/api/documentation
```

## API Endpoints

### User
- `PUT /api/v1/me` - Update own profile

### Transfers (Two-Step)
- `POST /api/v1/me/transfers/initiate` - Initiate transfer (get token)
- `POST /api/v1/me/transfers/confirm` - Confirm with token

### Admin Only
- `PUT /api/v1/users/{id}/balance` - Update any user balance

## Documentation

- [SWAGGER.md](SWAGGER.md) - Full API documentation and usage
- [SECURITY_FIXES.md](SECURITY_FIXES.md) - Security enhancements
- [TEST_COVERAGE.md](TEST_COVERAGE.md) - Test documentation

## Tech Stack

- Laravel 11
- PostgreSQL 16
- Redis
- Laravel Sanctum (auth)
- BCMath (precision)
- l5-swagger (API docs)

## Security

- SERIALIZABLE isolation level
- Two-factor transfer confirmation
- Token expiration (15 min)
- Brute-force protection (3 attempts)
- Rate limiting (10/min transfers)
- Timing attack protection
- Admin role authorization

## Testing

```bash
docker-compose exec app php artisan test
# 76 tests, 298 assertions
```

## License

MIT
