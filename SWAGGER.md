# Swagger API Documentation

## Доступ к документации

После запуска приложения, Swagger UI доступен по адресу:

```
http://localhost:8000/api/documentation
```

## Endpoints

API документация включает следующие группы эндпоинтов:

### User Management
- **PUT /api/user** - Обновление профиля текущего пользователя

### Admin - Balance Management
- **PUT /api/users/{id}/balance** - Обновление баланса пользователя (только для администраторов)

### Balance Transfer
- **POST /api/transfer/initiate** - Инициация перевода (шаг 1)
  - Создает запрос на перевод и возвращает confirmation_token
  - Токен действителен 5 минут

- **POST /api/transfer/confirm** - Подтверждение перевода (шаг 2)
  - Выполняет перевод используя confirmation_token
  - Токен можно использовать только один раз

## Аутентификация

API использует Bearer Token аутентификацию. Для тестирования в Swagger UI:

1. Получите токен через `/api/login` или `/api/register`
2. Нажмите кнопку "Authorize" в Swagger UI
3. Введите токен в формате: `Bearer YOUR_TOKEN_HERE`
4. Теперь все запросы будут автоматически включать токен

## Пример использования двухфакторного перевода

### Шаг 1: Инициация перевода
```bash
curl -X POST http://localhost:8000/api/transfer/initiate \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "recipient_id": 2,
    "amount": "100.00"
  }'
```

Ответ:
```json
{
  "message": "Transfer initiated. Please confirm using the provided token.",
  "confirmation_token": "abc123def456",
  "expires_at": "2025-11-01T10:15:00Z",
  "amount": "100.00",
  "recipient_id": 2
}
```

### Шаг 2: Подтверждение перевода
```bash
curl -X POST http://localhost:8000/api/transfer/confirm \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "confirmation_token": "abc123def456"
  }'
```

## Регенерация документации

После изменения аннотаций в коде, регенерируйте документацию:

```bash
docker-compose exec app php artisan l5-swagger:generate
```

## Особенности безопасности API

API включает следующие меры безопасности:

1. **Rate Limiting** - Ограничение количества запросов (60 в минуту)
2. **Two-Factor Transfer** - Двухэтапное подтверждение переводов
3. **Constant-time lookups** - Защита от user enumeration
4. **Token expiration** - Токены подтверждения истекают через 5 минут
5. **Brute-force protection** - Блокировка после 3 неудачных попыток
6. **Admin-only operations** - Прямое изменение баланса доступно только администраторам
7. **Balance constraints** - Проверка на отрицательный баланс и максимальный лимит

## Структура ответов

### UserResource
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "balance": "1000.50",
  "created_at": "2025-01-01T12:00:00Z",
  "updated_at": "2025-01-01T12:00:00Z"
}
```

### TransferResource
```json
{
  "sender": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "balance": "900.50",
    "created_at": "2025-01-01T12:00:00Z",
    "updated_at": "2025-01-01T12:00:00Z"
  },
  "recipient": {
    "id": 2,
    "name": "Jane Smith",
    "email": "jane@example.com",
    "balance": "1100.50",
    "created_at": "2025-01-01T12:00:00Z",
    "updated_at": "2025-01-01T12:00:00Z"
  }
}
```

## Ошибки

API возвращает стандартные HTTP коды:

- **200** - Успешный запрос
- **201** - Ресурс создан
- **400** - Невалидный запрос
- **401** - Не авторизован
- **403** - Доступ запрещен
- **404** - Ресурс не найден
- **422** - Ошибка валидации
- **429** - Превышен лимит запросов

## Конфигурация

Настройки Swagger находятся в файле `config/l5-swagger.php`:

- Путь к UI: `/api/documentation`
- Путь к JSON спецификации: `/docs/api-docs.json`
- Путь к аннотациям: `app/` директория
