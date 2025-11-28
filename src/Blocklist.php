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

/**
 * Class Blocklist
 *
 * Provides efficient email domain blocking and disposable email detection.
 * Uses array flipping for O(1) lookups against large domain lists.
 */
class Blocklist {

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
	 * Custom blocked domains.
	 *
	 * @var array<string, int>
	 */
	private array $custom_blocked = [];

	/**
	 * Custom allowed domains.
	 *
	 * @var array<string, int>
	 */
	private array $custom_allowed = [];

	/**
	 * Path to the data directory.
	 *
	 * @var string
	 */
	private string $data_path;

	/**
	 * Path to custom blocked domains file.
	 *
	 * @var string|null
	 */
	private ?string $custom_blocked_file = null;

	/**
	 * Path to custom allowed domains file.
	 *
	 * @var string|null
	 */
	private ?string $custom_allowed_file = null;

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
	 * Load custom blocked domains from a JSON file.
	 *
	 * @param string $file Path to JSON file.
	 *
	 * @return self
	 */
	public function load_custom_blocked( string $file ): self {
		$this->custom_blocked_file = $file;

		if ( file_exists( $file ) ) {
			$content = file_get_contents( $file );
			$domains = json_decode( $content, true ) ?? [];

			foreach ( $domains as $domain ) {
				$domain = $this->normalize_domain( $domain );
				if ( $domain !== '' ) {
					$this->custom_blocked[ $domain ] = 1;
				}
			}
		}

		return $this;
	}

	/**
	 * Load custom allowed domains from a JSON file.
	 *
	 * @param string $file Path to JSON file.
	 *
	 * @return self
	 */
	public function load_custom_allowed( string $file ): self {
		$this->custom_allowed_file = $file;

		if ( file_exists( $file ) ) {
			$content = file_get_contents( $file );
			$domains = json_decode( $content, true ) ?? [];

			foreach ( $domains as $domain ) {
				$domain = $this->normalize_domain( $domain );
				if ( $domain !== '' ) {
					$this->custom_allowed[ $domain ] = 1;
				}
			}
		}

		return $this;
	}

	/**
	 * Save custom blocked domains to file.
	 *
	 * @param string|null $file Optional file path (uses loaded path if not provided).
	 *
	 * @return bool True on success.
	 */
	public function save_custom_blocked( ?string $file = null ): bool {
		$file = $file ?? $this->custom_blocked_file;

		if ( $file === null ) {
			return false;
		}

		$domains = array_keys( $this->custom_blocked );
		sort( $domains );

		$content = json_encode( $domains, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		return file_put_contents( $file, $content, LOCK_EX ) !== false;
	}

	/**
	 * Save custom allowed domains to file.
	 *
	 * @param string|null $file Optional file path (uses loaded path if not provided).
	 *
	 * @return bool True on success.
	 */
	public function save_custom_allowed( ?string $file = null ): bool {
		$file = $file ?? $this->custom_allowed_file;

		if ( $file === null ) {
			return false;
		}

		$domains = array_keys( $this->custom_allowed );
		sort( $domains );

		$content = json_encode( $domains, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		return file_put_contents( $file, $content, LOCK_EX ) !== false;
	}

	/**
	 * Add a domain to the custom blocklist and optionally save.
	 *
	 * @param string $domain  Domain to block.
	 * @param bool   $persist Whether to save to file immediately.
	 *
	 * @return bool True on success.
	 */
	public function add_blocked( string $domain, bool $persist = true ): bool {
		$domain = $this->normalize_domain( $domain );

		if ( $domain === '' ) {
			return false;
		}

		$this->custom_blocked[ $domain ] = 1;

		if ( $persist && $this->custom_blocked_file !== null ) {
			return $this->save_custom_blocked();
		}

		return true;
	}

	/**
	 * Remove a domain from the custom blocklist and optionally save.
	 *
	 * @param string $domain  Domain to unblock.
	 * @param bool   $persist Whether to save to file immediately.
	 *
	 * @return bool True if domain was found and removed.
	 */
	public function remove_blocked( string $domain, bool $persist = true ): bool {
		$domain = $this->normalize_domain( $domain );

		if ( ! isset( $this->custom_blocked[ $domain ] ) ) {
			return false;
		}

		unset( $this->custom_blocked[ $domain ] );

		if ( $persist && $this->custom_blocked_file !== null ) {
			return $this->save_custom_blocked();
		}

		return true;
	}

	/**
	 * Add a domain to the custom allowlist and optionally save.
	 *
	 * @param string $domain  Domain to allow.
	 * @param bool   $persist Whether to save to file immediately.
	 *
	 * @return bool True on success.
	 */
	public function add_allowed( string $domain, bool $persist = true ): bool {
		$domain = $this->normalize_domain( $domain );

		if ( $domain === '' ) {
			return false;
		}

		$this->custom_allowed[ $domain ] = 1;

		if ( $persist && $this->custom_allowed_file !== null ) {
			return $this->save_custom_allowed();
		}

		return true;
	}

	/**
	 * Remove a domain from the custom allowlist and optionally save.
	 *
	 * @param string $domain  Domain to disallow.
	 * @param bool   $persist Whether to save to file immediately.
	 *
	 * @return bool True if domain was found and removed.
	 */
	public function remove_allowed( string $domain, bool $persist = true ): bool {
		$domain = $this->normalize_domain( $domain );

		if ( ! isset( $this->custom_allowed[ $domain ] ) ) {
			return false;
		}

		unset( $this->custom_allowed[ $domain ] );

		if ( $persist && $this->custom_allowed_file !== null ) {
			return $this->save_custom_allowed();
		}

		return true;
	}

	/**
	 * Check if a domain is in the custom blocked list.
	 *
	 * @param string $domain Domain to check.
	 *
	 * @return bool True if in custom blocked list.
	 */
	public function is_custom_blocked( string $domain ): bool {
		$domain = $this->normalize_domain( $domain );

		return isset( $this->custom_blocked[ $domain ] );
	}

	/**
	 * Check if a domain is in the custom allowed list.
	 *
	 * @param string $domain Domain to check.
	 *
	 * @return bool True if in custom allowed list.
	 */
	public function is_custom_allowed( string $domain ): bool {
		$domain = $this->normalize_domain( $domain );

		return isset( $this->custom_allowed[ $domain ] );
	}

	/**
	 * Get all custom blocked domains.
	 *
	 * @return array List of blocked domains.
	 */
	public function get_custom_blocked(): array {
		return array_keys( $this->custom_blocked );
	}

	/**
	 * Get count of custom blocked domains.
	 *
	 * @return int Number of custom blocked domains.
	 */
	public function get_custom_blocked_count(): int {
		return count( $this->custom_blocked );
	}

	/**
	 * Get all custom allowed domains.
	 *
	 * @return array List of allowed domains.
	 */
	public function get_custom_allowed(): array {
		return array_keys( $this->custom_allowed );
	}

	/**
	 * Get count of custom allowed domains.
	 *
	 * @return int Number of custom allowed domains.
	 */
	public function get_custom_allowed_count(): int {
		return count( $this->custom_allowed );
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
	 * Add domains to the custom blocklist (runtime only, no persistence).
	 *
	 * @param array|string $domains Domain or array of domains to block.
	 *
	 * @return self
	 */
	public function block( array|string $domains ): self {
		$domains = (array) $domains;

		foreach ( $domains as $domain ) {
			$domain = $this->normalize_domain( $domain );
			if ( $domain !== '' ) {
				$this->custom_blocked[ $domain ] = 1;
			}
		}

		return $this;
	}

	/**
	 * Add domains to the custom allowlist (runtime only, no persistence).
	 *
	 * @param array|string $domains Domain or array of domains to allow.
	 *
	 * @return self
	 */
	public function allow( array|string $domains ): self {
		$domains = (array) $domains;

		foreach ( $domains as $domain ) {
			$domain = $this->normalize_domain( $domain );
			if ( $domain !== '' ) {
				$this->custom_allowed[ $domain ] = 1;
			}
		}

		return $this;
	}

	/**
	 * Remove a domain from the custom blocklist (runtime only, no persistence).
	 *
	 * @param string $domain Domain to unblock.
	 *
	 * @return self
	 */
	public function unblock( string $domain ): self {
		$domain = $this->normalize_domain( $domain );
		unset( $this->custom_blocked[ $domain ] );

		return $this;
	}

	/**
	 * Remove a domain from the custom allowlist (runtime only, no persistence).
	 *
	 * @param string $domain Domain to disallow.
	 *
	 * @return self
	 */
	public function disallow( string $domain ): self {
		$domain = $this->normalize_domain( $domain );
		unset( $this->custom_allowed[ $domain ] );

		return $this;
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
	 * Get the count of disposable domains in the list.
	 *
	 * @return int Number of disposable domains.
	 */
	public function count(): int {
		return count( $this->get_disposable() );
	}

	/**
	 * Clear all custom blocked and allowed domains.
	 *
	 * @param bool $persist Whether to save empty lists to files.
	 *
	 * @return self
	 */
	public function clear( bool $persist = false ): self {
		$this->custom_blocked = [];
		$this->custom_allowed = [];

		if ( $persist ) {
			$this->save_custom_blocked();
			$this->save_custom_allowed();
		}

		return $this;
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

		// Filter comments and normalize
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

		// Check each level: sub.temp.com -> temp.com -> com
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
		// Handle Email object
		if ( is_object( $email ) && method_exists( $email, 'domain' ) ) {
			$domain = $email->domain();

			return $domain !== '' ? strtolower( $domain ) : null;
		}

		// Handle string
		if ( is_string( $email ) ) {
			$email = trim( $email );

			if ( str_contains( $email, '@' ) ) {
				$parts = explode( '@', $email );

				return strtolower( end( $parts ) );
			}

			// Assume it's already a domain
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

		// Remove @ prefix if present
		if ( str_starts_with( $domain, '@' ) ) {
			$domain = substr( $domain, 1 );
		}

		// Extract domain from email if full email passed
		if ( str_contains( $domain, '@' ) ) {
			$parts  = explode( '@', $domain );
			$domain = end( $parts );
		}

		return $domain;
	}

}