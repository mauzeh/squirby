# QR Code Connection Feature

## Overview

The QR code connection feature allows users to connect with each other at the gym without requiring a searchable user database. This privacy-focused approach uses temporary 6-digit codes and QR codes for mutual following.

## How It Works

### Connection Token Generation

Each user can generate a temporary connection token that:
- Is a 6-digit numeric code (e.g., `123 456`)
- Expires after 10 minutes
- Can be regenerated at any time
- Is displayed both as text and as a QR code

### Connection Process

1. User A opens their Profile page
2. User A sees their connection code and QR code
3. User B can either:
   - Scan User A's QR code with their device camera
   - Manually enter User A's 6-digit code
4. Upon successful connection, both users automatically follow each other (mutual follow)

## User Interface

### Profile Page Location

The connection interface is located on the Profile page (`/profile`), positioned between:
- Profile Photo section (above)
- Profile Information section (below)

### Components

1. **Connection Code Display**
   - Large, monospace 6-digit code with spaces (e.g., `123 456`)
   - Expiration timer showing minutes remaining
   - "Generate New Code" button to refresh

2. **QR Code**
   - 200x200px QR code encoding the connection URL
   - Scannable by any QR code reader
   - Automatically updates when code is regenerated

3. **Manual Entry Form**
   - Input field for entering a friend's 6-digit code
   - Auto-formats input with space after 3 digits
   - "Connect" button to initiate connection

## Technical Implementation

### Database Schema

```sql
-- users table additions
connection_token VARCHAR(6) NULLABLE INDEXED
connection_token_expires_at TIMESTAMP NULLABLE
```

### Routes

```php
// Generate new connection token
POST /profile/connection-token/generate

// Connect via token
POST /connect/{token}
```

### Models

**User Model Methods:**
- `generateConnectionToken()` - Creates new 6-digit token with 10-minute expiration
- `getValidConnectionToken()` - Returns existing valid token or generates new one
- `hasValidConnectionToken()` - Checks if current token is valid
- `clearConnectionToken()` - Removes token and expiration
- `findByConnectionToken($token)` - Static method to find user by valid token

### Security Features

1. **Token Expiration**: All tokens expire after 10 minutes
2. **Self-Connection Prevention**: Users cannot connect with themselves
3. **Duplicate Prevention**: Multiple connection attempts create only one follow relationship
4. **Privacy**: No searchable user database - connections only via direct code sharing

## Usage Examples

### Generating a Connection Code

```php
$user = auth()->user();
$token = $user->generateConnectionToken();
// Returns: "123456" (6-digit string)
```

### Connecting Two Users

```php
$currentUser = auth()->user();
$token = '123456';

$targetUser = User::findByConnectionToken($token);

if ($targetUser) {
    $currentUser->follow($targetUser);
    $targetUser->follow($currentUser);
}
```

### Checking Token Validity

```php
$user = auth()->user();

if ($user->hasValidConnectionToken()) {
    $token = $user->connection_token;
} else {
    $token = $user->generateConnectionToken();
}
```

## Frontend Behavior

### Auto-Formatting

The manual entry input automatically formats the 6-digit code:
- User types: `123456`
- Display shows: `123 456`

### Form Submission

When submitting the manual entry form:
1. JavaScript removes spaces from input
2. Validates code is exactly 6 digits
3. Redirects to `/connect/{code}`
4. Server processes connection and redirects back to profile

### QR Code Generation

QR codes are generated using the public API:
```
https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={url}
```

The encoded URL format:
```
https://yourdomain.com/connect/123456
```

## Error Handling

### Invalid Token
- Message: "Invalid or expired connection code."
- Occurs when token doesn't exist or has expired

### Self-Connection
- Message: "You cannot connect with yourself."
- Occurs when user tries to use their own token

### Success
- Message: "You're now connected with {Name}!"
- Both users are now following each other

## Testing

Comprehensive test coverage includes:
- Token generation and expiration
- Valid and invalid connection attempts
- Self-connection prevention
- Duplicate connection handling
- Mutual follow creation
- Token reuse and regeneration

Run tests:
```bash
php artisan test --filter=ConnectionTest
```

## Future Enhancements

Potential improvements:
1. Push notifications when someone connects
2. Connection history/log
3. Ability to make connections one-way instead of mutual
4. Custom expiration times
5. Connection requests with approval flow
6. Shareable links via text/email/AirDrop
