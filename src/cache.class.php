<?php

class BinaryCache {

	/** @var string */
	private $cacheName;

	/** @var string */
	private $cacheDir = '/cache/';

	/** @var string */
	private $keys = array(); // sha1(key) -> position

	public function __construct( $cacheName = 'default' ) {
		$this->cacheName = $cacheName;

		$dir = __DIR__ . $this->cacheDir;
		if ( !@mkdir( $dir ) && !is_dir( $dir ) ) {
			throw new \Exception( 'Could not create directory for cache' );
		}

		$this->data_file = __DIR__ . $this->cacheDir . sha1( $this->cacheName ) . '.cache';
		$this->keys_file = __DIR__ . $this->cacheDir . sha1( $this->cacheName ) . '.keys';

		if ( !is_file( $this->data_file ) ) {
			touch( $this->data_file );
		}
		if ( !is_file( $this->keys_file ) ) {
			touch( $this->keys_file );
		}

		$this->initKeysFromFile();
	}

	public function store( $key, $data ) {
		$key = sha1( $key );
		$data = serialize( $data );

		$new_size = strlen( $data );
		$new_time = time();

		if ( isset( $this->keys[$key] ) ) {
			$pos = $this->keys[$key][0];
			$size = $this->keys[$key][1];
			$pos_key = $this->keys[$key][2];

			if ( $size >= $new_size ) {
				// just overwrite

				$fw = fopen( $this->data_file, 'r+b' );
				fseek( $fw, $pos );
				fwrite( $fw, $data );
				if ( $size > $new_size ) {
					fwrite( $fw, str_repeat( "\0", $size - $new_size ) );
				}
				fclose( $fw );

				$fw = fopen( $this->keys_file, 'r+b' );
				fseek( $fw, $pos_key );
				fwrite( $fw, $key . ' ' . $this->padded_to_10_chars( $pos ) . ' ' . $this->padded_to_10_chars( $new_size ) . ' ' . $this->padded_to_10_chars( $new_time ) );
				fclose( $fw );

				$this->keys[$key] = array( $pos, $new_size, $pos_key, $new_time );
			} else {
				// overwrite old key
				// empty old data
				// add new data
				// rebuild cache file if too many keys were removed

				$fw = fopen( $this->data_file, 'r+b' );
				fseek( $fw, $pos );
				fwrite( $fw, str_repeat( "\0", $size ) );
				fclose( $fw );

				$fw = fopen( $this->data_file, 'r+b' );
				fseek( $fw, 0, SEEK_END );
				$new_pos = ftell( $fw );
				fwrite( $fw, $data );
				fclose( $fw );

				$fw = fopen( $this->keys_file, 'r+b' );
				fseek( $fw, $pos_key );
				fwrite( $fw, $key . ' ' . $this->padded_to_10_chars( $new_pos ) . ' ' . $this->padded_to_10_chars( $new_size ) . ' ' . $this->padded_to_10_chars( $new_time ) );
				fclose( $fw );

				$this->keys[$key] = array( $new_pos, $new_size, $pos_key, $new_time );
			}
		} else {
			$fw = fopen( $this->data_file, 'r+b' );
			fseek( $fw, 0, SEEK_END );
			$pos = ftell( $fw );
			fwrite( $fw, $data );
			fclose( $fw );

			$fw = fopen( $this->keys_file, 'r+b' );
			fseek( $fw, 0, SEEK_END );
			$pos_key = ftell( $fw );
			fwrite( $fw, $key . ' ' . $this->padded_to_10_chars( $pos ) . ' ' . $this->padded_to_10_chars( $new_size ) . ' ' . $this->padded_to_10_chars( time() ) . "\n" );
			fclose( $fw );

			$this->keys[$key] = array( $pos, $new_size, $pos_key, $new_time );
		}
	}

	public function retrieve( $key, $maxAgeInSeconds = - 1 ) {
		$hash = sha1( $key );

		if ( $this->isCached( $key, $maxAgeInSeconds ) ) {
			$pos = $this->keys[$hash][0];
			$size = $this->keys[$hash][1];

			$fr = fopen( $this->data_file, 'rb' );
			fseek( $fr, $pos );
			$data = fread( $fr, $size );
			fclose( $fr );

			return unserialize( $data );
		}
		return null;
	}

	public function erase( $key ) {
		$key = sha1( $key );

		if ( isset( $this->keys[$key] ) ) {
			$pos = $this->keys[$key][0];
			$size = $this->keys[$key][1];
			$pos_key = $this->keys[$key][2];

			$fw = fopen( $this->data_file, 'r+b' );
			fseek( $fw, $pos );
			fwrite( $fw, str_repeat( "\0", $size ) );
			fclose( $fw );

			$fw = fopen( $this->keys_file, 'r+b' );
			fseek( $fw, $pos_key );
			fwrite( $fw, str_repeat( "\0", 40 + 1 + 10 + 1 + 10 + 1 + 10 ) );
			fclose( $fw );

			unset( $this->keys[$key] );
		}
	}

	public function isCached( $key, $maxAgeInSeconds = - 1 ) {
		$key = sha1( $key );

		if ( isset( $this->keys[$key] ) ) {
			$timePassed = time() - $this->keys[$key][3];
			if ( $maxAgeInSeconds >= 0 && $timePassed > $maxAgeInSeconds ) {
				return false;
			}
			return true;
		}

		return false;
	}

	/////////////

	private function padded_to_10_chars( $x ) {
		return str_pad( $x, 10, "\0" );
	}

	private function initKeysFromFile() {
		$fr = fopen( $this->keys_file, 'rb' );
		while ( !feof( $fr ) ) {
			$key_position = ftell( $fr );
			$line = fgets( $fr );

			if ( empty( $line ) || $line[0] === "\0" ) {
				continue;
			}
			# do same stuff with the $line
			list( $key, $position, $size, $time ) = explode( ' ', $line );
			$this->keys[$key] = array( 0 + (int)trim( $position ), 0 + (int)trim( $size ), 0 + $key_position, 0 + (int)trim( $time ) );
		}
		fclose( $fr );
	}
}
