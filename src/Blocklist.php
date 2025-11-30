<?php
/**
 * Email Blocklist
 *
 * A simple, efficient library for checking email addresses against disposable
 * email provider lists. Supports custom blocked/allowed domains with persistence.
 *
 * @package     ArrayPress\EmailBlocklist
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EmailBlocklist;

use ArrayPress\EmailBlocklist\Traits\CustomLists;

/**
 * Class Blocklist
 *
 * Provides efficient email domain blocking and disposable email detection.
 * Uses array flipping for O(1) lookups against large domain lists.
 */
class Blocklist {
	use CustomLists;

	/**
	 * Loaded disposable domains (flipped for O(1) lookup).
	 *
	 * @var array<string, int>|null
	 */
	private ?array $disposable = null;

	/**
	 * Loaded allowed domains (flipped for O(1) lookup).
	 *
	 * @var array<string, int>|null
	 */
	private ?array $allowed = null;

	/**
	 * Path to the data directory.
	 *
	 * @var string
	 */
	private string $data_path;

	/**
	 * Create a new Blocklist instance.
	 *
	 * @param array       $blocked   Custom domains to block.
	 * @param array       $allowed   Custom domains to allow.
	 * @param string|null $data_path Custom path to data directory.
	 */
	public function __construct(
		array $blocked = [],
		array $allowed = [],
		?string $data_path = null
	) {
		$this->data_path = $data_path ?? dirname( __DIR__ ) . '/data';

		if ( ! empty( $blocked ) ) {
			$this->block( $blocked );
		}

		if ( ! empty( $allowed ) ) {
			$this->allow( $allowed );
		}
	}

	/**
	 * Check if an email address is from a disposable provider.
	 *
	 * @param string|object $email Email address or Email object.
	 *
	 * @return bool True if the email is from a disposable provider.
	 */
	public function is_disposable( string|object $email ): bool {
		$domain = $this->extract_domain( $email );

		if ( $domain === null ) {
			return false;
		}

		// Check custom allowlist first
		if ( isset( $this->custom_allowed[ $domain ] ) ) {
			return false;
		}

		// Check built-in allowlist
		if ( isset( $this->get_allowed()[ $domain ] ) ) {
			return false;
		}

		// Check custom blocked list
		if ( isset( $this->custom_blocked[ $domain ] ) ) {
			return true;
		}

		// Check disposable list (handles subdomains)
		return $this->domain_in_list( $domain, $this->get_disposable() );
	}

	/**
	 * Check if an email address is blocked.
	 *
	 * Combines disposable detection with custom blocked domains.
	 *
	 * @param string|object $email Email address or Email object.
	 *
	 * @return bool True if the email is blocked.
	 */
	public function is_blocked( string|object $email ): bool {
		$domain = $this->extract_domain( $email );

		if ( $domain === null ) {
			return false;
		}

		// Check custom allowlist first (takes priority)
		if ( isset( $this->custom_allowed[ $domain ] ) ) {
			return false;
		}

		// Check custom blocked
		if ( isset( $this->custom_blocked[ $domain ] ) ) {
			return true;
		}

		// Fall back to disposable check
		return $this->is_disposable( $email );
	}

	/**
	 * Check if an email address is explicitly allowed.
	 *
	 * @param string|object $email Email address or Email object.
	 *
	 * @return bool True if the email is in the allowlist.
	 */
	public function is_allowed( string|object $email ): bool {
		$domain = $this->extract_domain( $email );

		if ( $domain === null ) {
			return false;
		}

		// Check custom allowlist
		if ( isset( $this->custom_allowed[ $domain ] ) ) {
			return true;
		}

		// Check built-in allowlist
		return isset( $this->get_allowed()[ $domain ] );
	}

	/**
	 * Get the count of disposable domains in the list.
	 *
	 * @return int Number of disposable domains.
	 */
	public function count(): int {
		return count( $this->get_disposable() );
	}

	/**
	 * Get all custom blocked domains.
	 *
	 * @return array List of blocked domains.
	 *
	 * @deprecated Use get_custom_blocked() instead.
	 */
	public function get_blocked(): array {
		return $this->get_custom_blocked();
	}

	/**
	 * Clear all custom blocked and allowed domains.
	 *
	 * @param bool $persist Whether to save empty lists to files.
	 *
	 * @return self
	 *
	 * @deprecated Use clear_custom() instead.
	 */
	public function clear( bool $persist = false ): self {
		return $this->clear_custom( $persist );
	}

	/**
	 * Get the loaded disposable domains list.
	 *
	 * @return array<string, int> Flipped array of disposable domains.
	 */
	private function get_disposable(): array {
		if ( $this->disposable === null ) {
			$this->disposable = $this->load_list( 'disposable.txt' );
		}

		return $this->disposable;
	}

	/**
	 * Get the loaded allowed domains list.
	 *
	 * @return array<string, int> Flipped array of allowed domains.
	 */
	private function get_allowed(): array {
		if ( $this->allowed === null ) {
			$this->allowed = $this->load_list( 'allowlist.txt' );
		}

		return $this->allowed;
	}

	/**
	 * Load a domain list file and flip it for O(1) lookups.
	 *
	 * @param string $filename Name of the file in the data directory.
	 *
	 * @return array<string, int> Flipped array of domains.
	 */
	private function load_list( string $filename ): array {
		$path = $this->data_path . '/' . $filename;

		if ( ! file_exists( $path ) ) {
			return [];
		}

		$lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		if ( $lines === false ) {
			return [];
		}

		$domains = [];
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line !== '' && $line[0] !== '#' ) {
				$domains[] = strtolower( $line );
			}
		}

		return array_flip( $domains );
	}

	/**
	 * Check if a domain (or any parent domain) exists in a list.
	 *
	 * Handles subdomain matching: sub.tempmail.com matches tempmail.com
	 *
	 * @param string             $domain Domain to check.
	 * @param array<string, int> $list   Flipped domain list.
	 *
	 * @return bool True if domain or parent is in list.
	 */
	private function domain_in_list( string $domain, array $list ): bool {
		$parts = explode( '.', $domain );
		$count = count( $parts );

		for ( $i = 0; $i < $count - 1; $i ++ ) {
			$check = implode( '.', array_slice( $parts, $i ) );
			if ( isset( $list[ $check ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract domain from an email address or Email object.
	 *
	 * @param string|object $email Email address or object with domain() method.
	 *
	 * @return string|null Lowercase domain or null if invalid.
	 */
	private function extract_domain( string|object $email ): ?string {
		if ( is_object( $email ) && method_exists( $email, 'domain' ) ) {
			$domain = $email->domain();

			return $domain !== '' ? strtolower( $domain ) : null;
		}

		if ( is_string( $email ) ) {
			$email = trim( $email );

			if ( str_contains( $email, '@' ) ) {
				$parts = explode( '@', $email );

				return strtolower( end( $parts ) );
			}

			return strtolower( $email );
		}

		return null;
	}

	/**
	 * Normalize a domain string.
	 *
	 * @param string $domain Domain to normalize.
	 *
	 * @return string Normalized lowercase domain.
	 */
	private function normalize_domain( string $domain ): string {
		$domain = trim( strtolower( $domain ) );

		if ( str_starts_with( $domain, '@' ) ) {
			$domain = substr( $domain, 1 );
		}

		if ( str_contains( $domain, '@' ) ) {
			$parts  = explode( '@', $domain );
			$domain = end( $parts );
		}

		return $domain;
	}

}