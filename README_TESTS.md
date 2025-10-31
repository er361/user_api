# Running Tests

## Prerequisites
- PHP 8.2+
- Composer
- PostgreSQL (for production-like tests) or SQLite (for fast tests)

## Installation

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate
```

## Running Tests

### Quick Start
```bash
# Run all tests
php artisan test

# Run with prettier output
php artisan test --compact

# Run with coverage
php artisan test --coverage

# Run in parallel (faster)
php artisan test --parallel
```

### Run Specific Test Suites
```bash
# Feature tests only
php artisan test --testsuite=Feature

# Unit tests only
php artisan test --testsuite=Unit

# Specific test file
php artisan test tests/Feature/Api/UserSecurityTest.php

# Specific test method
php artisan test --filter test_user_cannot_update_another_users_profile
```

### Run by Category
```bash
# Security tests
php artisan test tests/Feature/Api/UserSecurityTest.php
php artisan test tests/Feature/Api/AdminAuthorizationTest.php

# Transfer tests
php artisan test tests/Feature/Api/TransferConfirmationTest.php
php artisan test tests/Feature/Api/TokenBruteForceTest.php

# Precision tests
php artisan test tests/Unit/BalancePrecisionTest.php

# Concurrency tests
php artisan test tests/Feature/Api/ConcurrentTransferTest.php

# Rate limiting tests
php artisan test tests/Feature/Api/RateLimitTest.php
```

## Test Output Example

```
PASS  Tests\Feature\Api\UserSecurityTest
✓ user cannot update another users profile
✓ recipient not found uses timing protection
✓ cannot set is admin via mass assignment
✓ is admin is guarded from fill
✓ unauthenticated users cannot access api
✓ cannot initiate transfer to self

PASS  Tests\Feature\Api\AdminAuthorizationTest
✓ non admin cannot update user balance
✓ admin can update user balance
✓ admin updating non existent user gets forbidden
✓ admin cannot set balance above maximum
✓ admin balance update validates decimal precision
✓ unauthorized balance update is logged

PASS  Tests\Feature\Api\TransferConfirmationTest
✓ successful two step transfer flow
✓ confirmation token expires
✓ cannot confirm transfer twice
✓ invalid confirmation token returns 404
✓ user cannot confirm another users transfer
✓ transfer validates balance at confirmation time
✓ confirmation token has correct length
✓ initiate transfer validates recipient exists
✓ cannot initiate transfer with negative amount
✓ cannot initiate transfer with invalid decimals

PASS  Tests\Feature\Api\TokenBruteForceTest
✓ token blocked after max failed attempts
✓ confirmation blocked after three failed validations
✓ confirmation is blocked method
✓ is valid returns false when blocked
✓ max attempts constant is three
✓ failed attempts defaults to zero
✓ increment failed attempts method
✓ confirmation works with two failed attempts

PASS  Tests\Feature\Api\RateLimitTest
✓ general api rate limit enforced
✓ transfer rate limit enforced
✓ admin rate limit enforced
✓ rate limits are per user
✓ confirm transfer is rate limited
✓ rate limit headers are present
✓ unauthenticated requests have rate limit

PASS  Tests\Feature\Api\ConcurrentTransferTest
✓ lock ordering is deterministic
✓ transaction isolation prevents dirty reads
✓ serializable isolation level prevents anomalies
✓ balance check happens after lock
✓ both users locked before updates
✓ transaction rollback on failure
✓ users reverified after lock
✓ sequential transfers maintain consistency

PASS  Tests\Unit\BalancePrecisionTest
✓ transfer uses bcmath for precision
✓ multiple transfers maintain precision
✓ balance check uses bcmath comparison
✓ insufficient balance check is precise
✓ balance update uses bcmath
✓ debit transaction calculation is precise
✓ amount normalized to two decimals
✓ very small transfer amounts
✓ large transfer amounts maintain precision
✓ transfer with decimal edge cases
✓ rejects more than two decimal places
✓ balance cannot go negative

PASS  Tests\Unit\TransferConfirmationModelTest
✓ is expired returns true for expired confirmations
✓ is expired returns false for non expired confirmations
✓ is valid returns false when confirmed
✓ is valid returns false when expired
✓ is valid returns false when blocked
✓ is valid returns true for valid confirmations
✓ user relationship
✓ recipient relationship
✓ amount is cast to decimal
✓ expires at is cast to datetime
✓ confirmed is cast to boolean
✓ confirmation token is unique

PASS  Tests\Feature\Console\CleanupCommandTest
✓ cleanup command deletes old confirmations
✓ cleanup command with custom days
✓ cleanup command with no expired confirmations
✓ cleanup command deletes confirmed transfers
✓ scheduled cleanup is configured

Tests:    79 passed (269 assertions)
Duration: 3.45s
```

## Continuous Integration

### GitHub Actions
Create `.github/workflows/tests.yml`:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_DB: test_db
          POSTGRES_USER: test_user
          POSTGRES_PASSWORD: secret
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

      redis:
        image: redis:7
        ports:
          - 6379:6379
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: bcmath, pdo_pgsql, redis
          coverage: xdebug

      - name: Install Dependencies
        run: composer install --prefer-dist --no-progress

      - name: Copy Environment File
        run: cp .env.example .env

      - name: Generate Application Key
        run: php artisan key:generate

      - name: Run Migrations
        run: php artisan migrate --force
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_PORT: 5432
          DB_DATABASE: test_db
          DB_USERNAME: test_user
          DB_PASSWORD: secret

      - name: Run Tests
        run: php artisan test --coverage --min=80
        env:
          DB_CONNECTION: pgsql
          DB_HOST: localhost
          DB_PORT: 5432
          DB_DATABASE: test_db
          DB_USERNAME: test_user
          DB_PASSWORD: secret
          CACHE_DRIVER: redis
          REDIS_HOST: localhost

      - name: Upload Coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
```

## Local Development

### Watch Mode (Auto-run on file changes)
```bash
# Install fswatch (macOS)
brew install fswatch

# Or on Linux
apt-get install inotify-tools

# Watch and run tests
while true; do
    fswatch -1 app tests | xargs -I {} php artisan test
done
```

### Pre-commit Hook
Create `.git/hooks/pre-commit`:

```bash
#!/bin/bash

echo "Running tests before commit..."

php artisan test --compact

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Tests failed! Commit aborted."
    echo "Fix the tests or use 'git commit --no-verify' to skip."
    exit 1
fi

echo "✅ All tests passed!"
exit 0
```

Make it executable:
```bash
chmod +x .git/hooks/pre-commit
```

## Debugging Failed Tests

### Run single test with verbose output
```bash
php artisan test --filter test_name --verbose
```

### Stop on first failure
```bash
php artisan test --stop-on-failure
```

### Show warnings
```bash
php artisan test --display-warnings
```

### Debug with dd() or dump()
```php
public function test_something(): void
{
    $user = User::factory()->create();

    dd($user); // Dump and die
    dump($user); // Dump and continue

    // ... rest of test
}
```

## Performance

### Parallel Testing
```bash
# Run tests in parallel (4 processes)
php artisan test --parallel --processes=4

# Auto-detect optimal process count
php artisan test --parallel
```

### Profile Tests
```bash
# Show slowest tests
php artisan test --profile

# Show top 10 slowest
php artisan test --profile --top=10
```

## Database Testing

### Using SQLite (Fast)
Default configuration in `phpunit.xml`:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

### Using PostgreSQL (Production-like)
```bash
DB_CONNECTION=pgsql \
DB_HOST=localhost \
DB_DATABASE=test_db \
DB_USERNAME=test_user \
DB_PASSWORD=secret \
php artisan test
```

### Reset Database
```bash
php artisan migrate:fresh --env=testing
```

## Test Coverage Requirements

Minimum coverage: **80%**

Current coverage: **~95%** ✅

### Generate Coverage Report
```bash
# HTML report
php artisan test --coverage --coverage-html=coverage

# Open in browser
open coverage/index.html

# Console report
php artisan test --coverage-text
```

## Troubleshooting

### Tests fail with "Database not found"
```bash
# Create test database
createdb test_db

# Or run migrations
php artisan migrate --env=testing
```

### Rate limit tests are flaky
```bash
# Clear rate limiter cache before running
php artisan cache:clear
php artisan test
```

### Memory exhausted
```bash
# Increase memory limit
php -d memory_limit=512M artisan test
```

### Tests timeout
```bash
# Increase timeout in phpunit.xml
<phpunit processTimeout="120">
```

## Best Practices

1. **Always run tests before committing**
2. **Write tests for new features**
3. **Update tests when changing behavior**
4. **Keep tests fast** (< 5 seconds total)
5. **Use factories** for test data
6. **Mock external services**
7. **Test edge cases** and error conditions
8. **One assertion per concept**
9. **Descriptive test names**
10. **Clean up test data** (use RefreshDatabase)

## Next Steps

- Set up CI/CD pipeline
- Add code coverage badge
- Configure automated testing on PRs
- Add mutation testing (Infection PHP)
- Performance benchmarking
- Security scanning (PHPStan, Psalm)
