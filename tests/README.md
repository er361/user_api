# Test Suite Documentation

This project includes comprehensive test coverage with 49 tests across Feature and Unit test suites.

## Test Statistics

- **Total Tests**: 49
- **Feature Tests**: 30 tests across 4 files
- **Unit Tests**: 19 tests across 3 files
- **Code Coverage**: All critical paths tested

## Running Tests

### Run all tests
```bash
php artisan test
```

### Run specific test suite
```bash
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

### Run specific test file
```bash
php artisan test tests/Feature/Api/UserUpdateTest.php
php artisan test tests/Unit/Actions/TransferBalanceActionTest.php
```

### Run with coverage (requires Xdebug)
```bash
php artisan test --coverage
```

## Feature Tests

### UserUpdateTest (7 tests)
Tests for user profile update functionality.

**Tests:**
1. `test_user_can_update_own_profile` - Successful profile update
2. `test_user_cannot_update_another_users_profile` - Authorization check
3. `test_user_cannot_update_balance_through_user_update` - Balance protection
4. `test_unauthenticated_user_cannot_update_profile` - Authentication check
5. `test_email_must_be_unique` - Email uniqueness validation
6. `test_user_can_update_email_to_same_value` - Same email allowed
7. `test_validation_errors_for_invalid_data` - Input validation

**Coverage:**
- Authentication and authorization
- Balance protection in user update
- Validation rules
- Database persistence

### BalanceUpdateTest (7 tests)
Tests for balance update functionality.

**Tests:**
1. `test_user_can_update_own_balance` - Successful balance update
2. `test_balance_update_creates_transaction_log` - Audit logging (credit)
3. `test_decreasing_balance_creates_debit_transaction` - Audit logging (debit)
4. `test_user_cannot_update_another_users_balance` - Authorization check
5. `test_unauthenticated_user_cannot_update_balance` - Authentication check
6. `test_balance_cannot_be_negative` - Validation
7. `test_balance_must_be_numeric` - Type validation

**Coverage:**
- Balance updates
- Transaction logging (credit/debit)
- Authorization
- Validation rules

### BalanceTransferTest (11 tests)
Tests for balance transfer functionality.

**Tests:**
1. `test_user_can_transfer_balance_to_another_user` - Successful transfer
2. `test_transfer_creates_transaction_logs_for_both_users` - Dual audit logging
3. `test_user_cannot_transfer_more_than_available_balance` - Insufficient balance
4. `test_user_cannot_transfer_to_themselves` - Self-transfer prevention
5. `test_transfer_amount_must_be_positive` - Amount validation
6. `test_user_cannot_transfer_from_another_users_account` - Authorization
7. `test_transfer_is_atomic` - Transaction atomicity
8. `test_recipient_must_exist` - Recipient validation
9. `test_unauthenticated_user_cannot_transfer` - Authentication check
10. `test_concurrent_transfers_maintain_balance_integrity` - Concurrency handling
11. `test_transfer_respects_rate_limiting` - Rate limit enforcement

**Coverage:**
- Transfer logic
- Atomic transactions
- Business rules (no self-transfer, positive amount, sufficient balance)
- Authorization
- Concurrency and locking
- Rate limiting

### RateLimitingTest (5 tests)
Tests for API rate limiting.

**Tests:**
1. `test_api_rate_limit_is_enforced` - General API limit (60/min)
2. `test_transfer_rate_limit_is_more_restrictive` - Transfer limit (10/min)
3. `test_rate_limits_are_per_user` - Per-user isolation
4. `test_balance_update_respects_general_api_rate_limit` - Balance update limits
5. `test_rate_limit_headers_are_present` - Response headers

**Coverage:**
- General API rate limiting
- Transfer-specific rate limiting
- Per-user rate limits
- Rate limit headers

## Unit Tests

### UpdateUserActionTest (6 tests)
Tests for UpdateUserAction business logic.

**Tests:**
1. `test_action_updates_user_data` - Basic update functionality
2. `test_action_removes_balance_from_update_data` - Balance protection
3. `test_action_updates_only_provided_fields` - Partial updates
4. `test_action_returns_fresh_user_instance` - Fresh model instance
5. `test_action_persists_changes_to_database` - Database persistence
6. `test_action_is_atomic` - Transaction atomicity

**Coverage:**
- Action execution
- Balance protection
- Data persistence
- Transaction handling

### UpdateUserBalanceActionTest (7 tests)
Tests for UpdateUserBalanceAction business logic.

**Tests:**
1. `test_action_updates_user_balance` - Balance update
2. `test_action_creates_credit_transaction_when_increasing_balance` - Credit logging
3. `test_action_creates_debit_transaction_when_decreasing_balance` - Debit logging
4. `test_action_returns_fresh_user_instance` - Fresh model instance
5. `test_action_persists_balance_change_to_database` - Database persistence
6. `test_action_creates_transaction_with_description` - Audit trail description
7. `test_action_is_atomic` - Transaction atomicity

**Coverage:**
- Balance updates
- Transaction logging (credit/debit)
- Audit trail
- Database persistence

### TransferBalanceActionTest (13 tests)
Tests for TransferBalanceAction business logic.

**Tests:**
1. `test_action_transfers_balance_between_users` - Basic transfer
2. `test_action_creates_transaction_logs_for_both_users` - Dual logging
3. `test_action_throws_exception_for_self_transfer` - Self-transfer prevention
4. `test_action_throws_exception_for_insufficient_balance` - Insufficient funds
5. `test_action_throws_exception_for_zero_amount` - Zero amount validation
6. `test_action_throws_exception_for_negative_amount` - Negative amount validation
7. `test_action_is_atomic` - Transaction atomicity
8. `test_action_locks_users_in_consistent_order_to_prevent_deadlock` - Deadlock prevention
9. `test_action_includes_description_in_transactions` - Audit descriptions
10. `test_action_returns_fresh_user_instances` - Fresh model instances
11. `test_action_persists_changes_to_database` - Database persistence
12. `test_action_handles_exact_balance_transfer` - Edge case (full balance)
13. `test_action_prevents_balance_going_negative` - Negative balance prevention

**Coverage:**
- Transfer logic
- Exception handling
- Deadlock prevention
- Transaction logging
- Edge cases

## Test Coverage by Feature

### Authentication & Authorization
- 8 tests across Feature suite
- Covers: token authentication, policy-based authorization, per-user access

### Balance Operations
- 18 tests (7 Feature + 11 Unit)
- Covers: updates, transfers, audit logging, transaction atomicity

### Validation
- 9 tests across Feature suite
- Covers: input validation, business rules, data integrity

### Rate Limiting
- 5 tests in Feature suite
- Covers: API limits, transfer limits, per-user limits

### Concurrency & Locking
- 4 tests (1 Feature + 3 Unit)
- Covers: deadlock prevention, pessimistic locking, race conditions

### Audit Logging
- 8 tests across Feature and Unit suites
- Covers: transaction history, credit/debit/transfer logs

## Best Practices

1. **Database Transactions**: All tests use `RefreshDatabase` trait for clean state
2. **Factory Usage**: User models created via factories for consistency
3. **Descriptive Names**: Test names clearly describe what is being tested
4. **Arrange-Act-Assert**: Tests follow AAA pattern
5. **Edge Cases**: Tests cover edge cases and error conditions
6. **Integration**: Feature tests verify full request-response cycle
7. **Isolation**: Unit tests verify business logic in isolation

## Continuous Integration

To integrate with CI/CD:

```bash
# Example GitHub Actions workflow
php artisan test --coverage --min=80
```

## Adding New Tests

When adding new functionality:

1. Add Feature tests for API endpoints
2. Add Unit tests for Action classes
3. Update this README with new test descriptions
4. Maintain minimum 80% code coverage
