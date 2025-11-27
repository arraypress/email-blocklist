<?php
/**
 * Email Blocklist Helper Functions
 *
 * Global convenience functions for email blocklist operations.
 *
 * @package     ArrayPress\EmailBlocklist
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\EmailBlocklist\Blocklist;

if ( ! function_exists( 'is_disposable_email' ) ) {
	/**
	 * Check if an email address is from a disposable provider.
	 *
	 * @param string $email Email address to check.
	 *
	 * @return bool True if the email is from a disposable provider.
	 */
	function is_disposable_email( string $email ): bool {
		static $blocklist = null;

		if ( $blocklist === null ) {
			$blocklist = new Blocklist();
		}

		return $blocklist->is_disposable( $email );
	}
}

if ( ! function_exists( 'get_email_blocklist' ) ) {
	/**
	 * Get a Blocklist instance with optional custom configuration.
	 *
	 * @param array $blocked Custom domains to block.
	 * @param array $allowed Custom domains to allow.
	 *
	 * @return Blocklist Configured blocklist instance.
	 */
	function get_email_blocklist( array $blocked = [], array $allowed = [] ): Blocklist {
		return new Blocklist( $blocked, $allowed );
	}
}