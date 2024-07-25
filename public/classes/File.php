<?php

class File extends Controller {
	const WHITELIST = [
		// HTML
		'html',
		'css',
		// Images
		'jpg',
		'jpeg',
		'png',
		'webp',
		'svg',
		'gif',
		// Video
		'webm',
		// Fonts
		'ttf',
		'otf',
		'woff',
		'woff2'
	];
	const HASH_LENGTH = 20;

	private \DB\SQL\Mapper $file;
	private string $hash;
	private string $filename = '';
	private string $extension = '';
	private bool $initialised = false;

	function __construct() {
		parent::__construct();
		// All file calls need a valid user. Will die() if not authenticated.
		$this->checkValidUser();
	}

	function initFile(): void {
		if ( $this->initialised ) {
			// Already initialised
			return;
		}

		// All requests must include the SHA1 (40 chars)
		$this->hash = $this->headers['X-Sharenote-Hash'] ?? $this->getPost( 'hash' ) ?? '';
		if ( strlen( $this->hash ) !== 40 || preg_match( "/[^a-f0-9]/", $this->hash ) ) {
			$this->errorAndDie( 400 ); // Bad request
		}

		// File extension must be in our whitelist
		$extension       = $this->headers['X-Sharenote-Filetype'] ?? $this->getPost( 'filetype' ) ?? '';
		$this->extension = strtolower( $extension );
		if ( ! in_array( $this->extension, self::WHITELIST ) ) {
			$this->errorAndDie( 415 ); // Unsupported media type
		}

		// Load the file if exists
		$filename   = $this->getPost( 'filename' );
		if (!$filename || !is_string($filename) || preg_match("/[^a-zA-Z0-9]/", $filename)) {
			$filename = $this->createRandomName();
		}
		$this->filename = $filename;
		$this->file = new DB\SQL\Mapper( $this->db, 'files' );
		if ( $this->extension === 'html' ) {
			$this->file->load( array( 'filename=? AND filetype=?', $this->filename, $this->extension ) );
		} elseif ( $this->extension === 'css' ) {
			$this->filename = $this->user->id;
			$this->file->load( array( 'filename=? AND filetype=?', $this->filename, $this->extension ) );
		} else {
			$this->file->load( array( 'hash=? AND filetype=?', $this->hash, $this->extension ) );
		}

		$this->initialised = true;
	}

	function createNote(): void {
		$this->success( [
			'url' => ''
		] );
	}

	private function saveFile( $contents ): void {
		$filename = $this->getFilePath();
		$folder   = $this->f3->get( 'upload_folder' ) . '/' . $this->getSubfolder( $this->extension );
		if ( ! file_exists( $folder ) ) {
			mkdir( $folder );
		}
		file_put_contents( $filename, $contents );

		// Update the database
		$date = $this->now();
		if ( ! $this->file->valid() ) {
			// This is a new record
			$this->file->users_id = $this->user->id;
			$this->file->filename = $this->filename;
			$this->file->filetype = strtolower( $this->extension );
			$this->file->created  = $date;
		}
		$this->file->updated = $date;
		$this->file->hash = $this->hash;
		$this->file->bytes   = filesize( $filename );

		$this->file->save();
	}

	function upload(): void {
		$this->initFile();

		// Save the file to disk and update the database
		$this->saveFile( $this->getPost( 'content' ) );

		// Output JSON data to return to the plugin
		$this->success( [
			'url' => $this->getUrl()
		] );
	}

	function delete(): void {
		$this->initFile();
		if ( $this->file->valid() ) {
			// Delete the local file
			unlink( $this->getFilePath() );
			// Delete the record from the database
			$this->file->erase();
			$this->success();
		}
		$this->failure();
	}

	/**
	 * Gets the sub-path to the file, excluding domain or local storage location.
	 * Used by getFilePath() and getUrl()
	 *
	 * @param $filename
	 * @param $extension
	 *
	 * @return string
	 */
	function getSubPath( $filename = null, $extension = null ): string {
		$filename  = $filename ?? $this->filename;
		$extension = $extension ?? $this->extension;

		return "/{$this->getSubfolder($extension)}/$filename.$extension";
	}

	/**
	 * Get the full path to the file on disk
	 * @return string
	 */
	function getFilePath(): string {
		return $this->f3->get( 'upload_folder' ) . $this->getSubPath();
	}

	/**
	 * Get the public URL of the file
	 *
	 * @param  null  $filename
	 * @param  null  $extension
	 *
	 * @return string
	 */
	function getUrl( $filename = null, $extension = null ): string {
		return $this->f3->get( 'file_url_base' ) . $this->getSubPath( $filename, $extension );
	}

	/**
	 * Check whether a file already exists in the DB
	 *
	 * @param $hash
	 * @param $extension
	 *
	 * @return string|null
	 */
	function checkFile( $hash, $extension ): ?string {
		$fileDb = new DB\SQL\Mapper( $this->db, 'files' );
		$fileDb->load( array( 'hash=? AND filetype=?', $hash, $extension ) );
		if ( $fileDb->valid() ) {
			return $this->getUrl( $fileDb->filename, $fileDb->filetype );
		}

		return null;
	}

	/**
	 * Pre-check a list of incoming files to see whether they need to be uploaded
	 * @return void
	 */
	function checkFiles(): void {
		$result = [];

		// Check the incoming files to see if they already exist
		foreach ( $this->getPost( 'files' ) as $file ) {
			$file->url = $this->checkFile( $file->hash, $file->filetype );
			$result[]  = $file;
		}

		// Get the info on the user's CSS (if exists)
		$css = new DB\SQL\Mapper( $this->db, 'files' );
		$css->load( array( 'filename=? AND filetype=?', $this->user->id, 'css' ) );

		$this->success( [
			'success' => true,
			'files'   => $result,
			'css'     => ! $css->valid() ? null : (object) [
				'url'  => $this->getUrl(),
				'hash' => $css->hash
			]
		] );
	}

	/**
	 * Provide a hash and get the subfolder name in return.
	 */
	function getSubfolder( $extension ): string {
		return match ( $extension ) {
			'html' => 'notes',
			'css' => 'css',
			default => 'files',
		};
	}

	function createRandomName( $extension = null ) {
		$extension = $extension ?? $this->extension;
		$length    = $extension === 'html' ? 8 : 20;
		$chars     = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$name      = '';
		for ( $i = 0; $i < $length; $i ++ ) {
			$name .= $chars[ rand( 0, strlen( $chars ) - 1 ) ];
		}

		return $name;
	}
}