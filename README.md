# Email Blocklist

Tell whether an email address belongs to a disposable provider, before you let
it sign up.

## What it does

Throwaway inboxes — mailinator, guerrillamail and thousands like them — exist
to get past a signup form and never be read again. This checks an address
against the [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains)
list, which is maintained by people who track them so you do not have to.

It answers one question fast, with the list held in memory rather than queried,
and lets you keep your own additions and exceptions alongside it.

## Features

* Reject a disposable address at signup, checkout or newsletter opt-in
* Block a domain the shared list has not caught yet
* Allow one the shared list gets wrong, so a real customer is not turned away
* Keep your own blocked and allowed domains between requests
* Pass an object rather than a string, when what you have is a user or an order
* Count what is loaded, to show in an admin screen

## Installation

```bash
composer require arraypress/email-blocklist
```

## Quick start

Refuse a disposable address at registration:

```php
add_filter( 'registration_errors', function ( $errors, $login, $email ) {
	if ( is_disposable_email( $email ) ) {
		$errors->add(
			'disposable_email',
			__( 'Please use a permanent email address.', 'my-plugin' )
		);
	}

	return $errors;
}, 10, 3 );
```

For anything more than a yes or no, take the list itself:

```php
$blocklist = get_email_blocklist();

$blocklist->add_blocked( 'throwaway.example' );
$blocklist->add_allowed( 'legitimate.example' );
```

Additions persist, so they survive the next request.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
