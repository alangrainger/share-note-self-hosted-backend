<?php

class Controller {
	protected \DB\SQL $db;
	protected Base $f3;
	protected object $json_post_data;
	protected object $user;
	protected array $headers;

	function __construct() {
		$this->f3      = Base::instance();
		$this->db      = new DB\SQL(
			"mysql:host={$this->f3->get('db_host')};port=3306;dbname={$this->f3->get('db_name')}",
			$this->f3->get( 'db_user' ),
			$this->f3->get( 'db_pass' )
		);
		$this->headers = $this->f3->get( 'HEADERS' );

		// Populate the post_data object depending on POST method
		if ( str_contains( $this->headers['Content-Type'] ?? '', 'application/json' ) ) {
			try {
				$this->json_post_data = json_decode( file_get_contents( "php://input" ) );
			} catch ( Throwable ) {
				$this->json_post_data = (object) [];
			}
		}
	}

	function getPost( $property ) {
		return $this->json_post_data->{$property} ?? null;
	}

	function shortHash( $data ): string {
		$sha = hash( 'sha256', $data );

		return substr( $sha, 0, 32 );
	}

	function now(): string {
		return date( 'Y-m-d H:i:s' );
	}

	/**
	 * Checks for an authenticated user, and will die() if none found
	 */
	function checkValidUser( $statusCode = 401 ): void {
		$uid   = $this->headers['X-Sharenote-Id'] ?? null;
		$hash  = $this->headers['X-Sharenote-Key'] ?? null;
		$nonce = $this->headers['X-Sharenote-Nonce'] ?? null;

		if ( ! is_string( $uid ) || ! is_string( $hash ) ) {
			$this->errorAndDie( $statusCode ); // Unauthorised
		}
		if ( ! hash_equals( $uid, $this->f3->get( 'uid' ) ) ) {
			$this->errorAndDie( $statusCode ); // Unauthorised
		}

		// Check to see if the stored key matches the provided key
		$valid = false;
		if ( $nonce ) {
			$checkHash = hash( 'sha256', $nonce . $this->f3->get( 'private_key' ) );
			$valid     = hash_equals( $checkHash, $hash );
		}

		if ( $valid ) {
			// Store the valid user for use in other functions
			$this->user = (object) [
				'id'    => $uid,
				'valid' => true
			];
		} else {
			$this->errorAndDie( $statusCode ); // Unauthorised
		}
	}

	/**
	 * Output an HTTP error and immediately die()
	 *
	 * @param  int  $httpResponseCode
	 *
	 * @return void
	 */
	function errorAndDie( int $httpResponseCode ): void {
		$this->f3->error( $httpResponseCode );
		die();
	}

	/**
	 * Output a success JSON object for consumption by the frontend plugin
	 *
	 * @param  array  $data
	 *
	 * @return void
	 */
	function success( array $data = [] ): void {
		$data['success'] = true;
		echo json_encode( $data );
		exit();
	}

	function failure(): void {
		echo json_encode( [
			'success' => false
		] );
		die();
	}
}