# User Balance API

A RESTful API for managing user profiles and balance operations, built with Laravel 11.

## Features

- **User Profile Management**: Update user information with proper authorization
- **Balance Management**: Secure balance updates with complete audit trail
- **Balance Transfers**: Transfer balance between users with atomic transactions
- **Authentication**: Token-based authentication using Laravel Sanctum
- **Authorization**: Policy-based access control
- **Rate Limiting**: Protection against abuse with configurable rate limits
- **Audit Logging**: Complete transaction history for all balance operations
- **Deadlock Prevention**: Optimized locking strategy for concurrent operations
- **Comprehensive Testing**: 49 tests covering all functionality

## Requirements

- PHP 8.2 or higher
- PostgreSQL 14.0 or higher
- Composer

## Installation

1. Clone the repository:
```bash
git clone git@github.com:er361/user_api.git
cd user_api
```

2. Install dependencies:
```bash
composer install
```

3. Set up environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Configure database in `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=user_api
DB_USERNAME=postgres
DB_PASSWORD=
```

5. Run migrations:
```bash
php artisan migrate
```

## API Endpoints

All endpoints require authentication via Bearer token.

### Update User Profile
```
PUT /api/v1/users/{id}
```

**Request:**
```json
{
    "name": "John Doe",
    "email": "john@example.com"
}
```

**Response:**
```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "balance": "100.00",
    "created_at": "2025-10-27T10:00:00.000000Z",
    "updated_at": "2025-10-27T10:30:00.000000Z"
}
```

**Notes:**
- Users can only update their own profile
- Balance cannot be updated through this endpoint

### Update User Balance
```
PUT /api/v1/users/{id}/balance
```

**Request:**
```json
{
    "balance": 500.00
}
```

**Response:**
```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "balance": "500.00",
    "created_at": "2025-10-27T10:00:00.000000Z",
    "updated_at": "2025-10-27T10:35:00.000000Z"
}
```

**Notes:**
- Creates audit log entry (credit/debit)
- Users can only update their own balance

### Transfer Balance
```
POST /api/v1/users/{id}/transfer-balance
```

**Request:**
```json
{
    "recipient_id": 2,
    "amount": 100.00
}
```

**Response:**
```json
{
    "sender": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "balance": "400.00",
        "created_at": "2025-10-27T10:00:00.000000Z",
        "updated_at": "2025-10-27T10:40:00.000000Z"
    },
    "recipient": {
        "id": 2,
        "name": "Jane Smith",
        "email": "jane@example.com",
        "balance": "200.00",
        "created_at": "2025-10-27T10:05:00.000000Z",
        "updated_at": "2025-10-27T10:40:00.000000Z"
    }
}
```

**Notes:**
- Amount must be greater than 0
- Sender must have sufficient balance
- Cannot transfer to yourself
- Creates audit log entries for both users
- Rate limited to 10 transfers per minute

## Rate Limits

- **General API**: 60 requests per minute per user
- **Transfers**: 10 requests per minute per user

## Testing

Run all tests:
```bash
php artisan test
```

Run specific test suite:
```bash
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

Run specific test file:
```bash
php artisan test tests/Feature/Api/BalanceTransferTest.php
```

See `tests/README.md` for detailed test documentation.

## Architecture

### Action Pattern
Business logic is extracted into dedicated Action classes:
- `UpdateUserAction`: Handles user profile updates
- `UpdateUserBalanceAction`: Manages balance updates
- `TransferBalanceAction`: Executes balance transfers

### Security Features
- **Authentication**: Laravel Sanctum token-based authentication
- **Authorization**: Policy-based access control (users can only modify their own data)
- **Database Constraints**: CHECK constraint ensures balance >= 0
- **Pessimistic Locking**: Prevents race conditions
- **Deadlock Prevention**: Consistent lock ordering by user ID
- **Audit Logging**: Complete transaction history in `balance_transactions` table
- **Rate Limiting**: Protection against abuse

### Database Structure

**users table:**
- `id`: Primary key
- `name`: User name
- `email`: Unique email
- `balance`: Decimal(15,2), default 0, constraint >= 0
- `password`: Hashed password
- `remember_token`: Remember token
- `created_at`, `updated_at`: Timestamps

**balance_transactions table:**
- `id`: Primary key
- `user_id`: Foreign key to users
- `type`: Enum (credit, debit, transfer_in, transfer_out)
- `amount`: Decimal(15,2)
- `balance_before`: Decimal(15,2)
- `balance_after`: Decimal(15,2)
- `related_user_id`: Foreign key to users (nullable)
- `description`: Text description
- `created_at`, `updated_at`: Timestamps

## Error Handling

The API returns appropriate HTTP status codes:
- `200`: Success
- `400`: Bad request (insufficient balance, invalid transfer)
- `401`: Unauthenticated
- `403`: Forbidden (unauthorized action)
- `404`: Resource not found
- `422`: Validation error
- `429`: Rate limit exceeded

## License

This project is open-sourced software licensed under the MIT license.
