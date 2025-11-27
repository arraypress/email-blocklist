# Email Blocklist

A simple, efficient library for checking email addresses against disposable email provider lists. Uses data from the [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains) project.

## Installation

```bash
composer require arraypress/email-blocklist
```

## Usage

### Quick Check

```php
// Using the helper function
if ( is_disposable_email( 'user@tempmail.com' ) ) {
    // Reject registration
}
```

### Using the Class

```php
use ArrayPress\EmailBlocklist\Blocklist;

$blocklist = new Blocklist();

// Check if disposable
$blocklist->is_disposable( 'user@tempmail.com' );     // true
$blocklist->is_disposable( 'user@gmail.com' );        // false

// Check if blocked (includes custom blocked domains)
$blocklist->is_blocked( 'user@tempmail.com' );        // true

// Check if explicitly allowed
$blocklist->is_allowed( 'user@company.com' );         // false
```

### Custom Blocked/Allowed Domains

```php
// Via constructor
$blocklist = new Blocklist(
    blocked: ['competitor.com', 'banned.org'],
    allowed: ['partner.com', 'trusted.org']
);

// Or fluent methods
$blocklist = new Blocklist();
$blocklist->block( ['competitor.com', 'banned.org'] );
$blocklist->allow( ['partner.com', 'trusted.org'] );

// Single domain
$blocklist->block( 'spammer.com' );
$blocklist->allow( 'friend.com' );

// Remove from custom lists
$blocklist->unblock( 'competitor.com' );
$blocklist->disallow( 'partner.com' );

// Clear all custom entries
$blocklist->clear();
```

### With Email Objects

Works with any object that has a `domain()` method:

```php
use ArrayPress\EmailUtils\Email;
use ArrayPress\EmailBlocklist\Blocklist;

$email = Email::parse( 'user@tempmail.com' );
$blocklist = new Blocklist();

if ( $email && $blocklist->is_disposable( $email ) ) {
    // Reject
}
```

### Custom Data Path

```php
// Use a custom directory for data files
$blocklist = new Blocklist(
    data_path: '/path/to/your/data'
);
```

## How It Works

The library uses array flipping for O(1) lookups:

```php
// Instead of O(n) search through 170k domains
in_array( $domain, $huge_list );  // Slow

// We use O(1) hash lookup
isset( $flipped_list[ $domain ] );  // Instant
```

Subdomain matching is supported — if `tempmail.com` is blocked, `sub.tempmail.com` is also blocked.

## Priority Order

When checking an email:

1. **Custom allowlist** — If domain is in custom allowlist, allowed
2. **Built-in allowlist** — If domain is in source allowlist, allowed
3. **Custom blocklist** — If domain is in custom blocklist, blocked
4. **Disposable list** — If domain matches disposable list, blocked
5. **Default** — Allow

## Available Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `is_disposable($email)` | `bool` | Check if email is from disposable provider |
| `is_blocked($email)` | `bool` | Check if email is blocked (disposable + custom) |
| `is_allowed($email)` | `bool` | Check if email is in allowlist |
| `block($domains)` | `self` | Add domain(s) to custom blocklist |
| `allow($domains)` | `self` | Add domain(s) to custom allowlist |
| `unblock($domain)` | `self` | Remove domain from custom blocklist |
| `disallow($domain)` | `self` | Remove domain from custom allowlist |
| `get_blocked()` | `array` | Get custom blocked domains |
| `get_custom_allowed()` | `array` | Get custom allowed domains |
| `clear()` | `self` | Clear all custom entries |
| `count()` | `int` | Count of disposable domains |

## Data Source

This library uses data from:
- [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains)

The list contains ~170,000 disposable email domains and is actively maintained.

## Requirements

- PHP 8.0+

## License

GPL-2.0-or-later