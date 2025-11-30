<?php
/**
 * Custom Lists Trait
 *
 * Handles custom blocked and allowed domain lists with optional file persistence.
 *
 * @package     ArrayPress\EmailBlocklist
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\EmailBlocklist\Traits;

/**
 * Trait CustomLists
 *
 * Provides custom blocked/allowed domain management with file persistence.
 */
trait CustomLists {

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
	 * Load custom blocked domains from a JSON file.
	 *
	 * @param string $file Path to JSON file.
	 *
	 * @return self
	 */
	public function load_custom_blocked( string $file ): self {
		$this->custom_blocked_file = $file;
		$this->custom_blocked      = $this->load_json_list( $file );

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
		$this->custom_allowed      = $this->load_json_list( $file );

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
		return $this->save_json_list(
			$this->custom_blocked,
			$file ?? $this->custom_blocked_file
		);
	}

	/**
	 * Save custom allowed domains to file.
	 *
	 * @param string|null $file Optional file path (uses loaded path if not provided).
	 *
	 * @return bool True on success.
	 */
	public function save_custom_allowed( ?string $file = null ): bool {
		return $this->save_json_list(
			$this->custom_allowed,
			$file ?? $this->custom_allowed_file
		);
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
		return isset( $this->custom_blocked[ $this->normalize_domain( $domain ) ] );
	}

	/**
	 * Check if a domain is in the custom allowed list.
	 *
	 * @param string $domain Domain to check.
	 *
	 * @return bool True if in custom allowed list.
	 */
	public function is_custom_allowed( string $domain ): bool {
		return isset( $this->custom_allowed[ $this->normalize_domain( $domain ) ] );
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
	 * Add domains to the custom blocklist (runtime only, no persistence).
	 *
	 * @param array|string $domains Domain or array of domains to block.
	 *
	 * @return self
	 */
	public function block( array|string $domains ): self {
		foreach ( (array) $domains as $domain ) {
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
		foreach ( (array) $domains as $domain ) {
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
		unset( $this->custom_blocked[ $this->normalize_domain( $domain ) ] );

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
		unset( $this->custom_allowed[ $this->normalize_domain( $domain ) ] );

		return $this;
	}

	/**
	 * Clear all custom blocked and allowed domains.
	 *
	 * @param bool $persist Whether to save empty lists to files.
	 *
	 * @return self
	 */
	public function clear_custom( bool $persist = false ): self {
		$this->custom_blocked = [];
		$this->custom_allowed = [];

		if ( $persist ) {
			$this->save_custom_blocked();
			$this->save_custom_allowed();
		}

		return $this;
	}

	/**
	 * Load a JSON domain list file.
	 *
	 * @param string $file Path to JSON file.
	 *
	 * @return array<string, int> Flipped array of domains.
	 */
	private function load_json_list( string $file ): array {
		if ( ! file_exists( $file ) ) {
			return [];
		}

		$content = file_get_contents( $file );
		$domains = json_decode( $content, true ) ?? [];
		$result  = [];

		foreach ( $domains as $domain ) {
			$domain = $this->normalize_domain( $domain );
			if ( $domain !== '' ) {
				$result[ $domain ] = 1;
			}
		}

		return $result;
	}

	/**
	 * Save a domain list to a JSON file.
	 *
	 * @param array<string, int> $list Flipped domain array.
	 * @param string|null        $file Path to save to.
	 *
	 * @return bool True on success.
	 */
	private function save_json_list( array $list, ?string $file ): bool {
		if ( $file === null ) {
			return false;
		}

		$domains = array_keys( $list );
		sort( $domains );

		$content = json_encode( $domains, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		return file_put_contents( $file, $content, LOCK_EX ) !== false;
	}

	/**
	 * Normalize a domain string.
	 *
	 * Must be implemented by the using class.
	 *
	 * @param string $domain Domain to normalize.
	 *
	 * @return string Normalized lowercase domain.
	 */
	abstract private function normalize_domain( string $domain ): string;

}