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

		$new_size = mb_strlen( $data );

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
					fwrite( $fw, str_repeat( "\1", $size - $new_size ) );
				}
				fclose( $fw );

				$fw = fopen( $this->keys_file, 'r+b' );
				fseek( $fw, $pos_key );
				fwrite( $fw, $key . ' ' . $this->padded_to_10_chars( $pos ) . ' ' . $this->padded_to_10_chars( $new_size ) );
				fclose( $fw );

				$this->keys[$key] = array( $pos, $new_size, $pos_key );
			} else {
				// overwrite old key
				// empty old data
				// add new data
				// rebuild cache file if too many keys were removed

				$fw = fopen( $this->data_file, 'r+b' );
				fseek( $fw, $pos );
				fwrite( $fw, str_repeat( "\1", $size ) );
				fclose( $fw );

				$fw = fopen( $this->data_file, 'r+b' );
				fseek( $fw, 0, SEEK_END );
				$new_pos = ftell( $fw );
				fwrite( $fw, $data );
				fclose( $fw );

				$fw = fopen( $this->keys_file, 'r+b' );
				fseek( $fw, $pos_key );
				fwrite( $fw, $key . ' ' . $this->padded_to_10_chars( $new_pos ) . ' ' . $this->padded_to_10_chars( $new_size ) );
				fclose( $fw );

				$this->keys[$key] = array( $new_pos, $new_size, $pos_key );
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
			fwrite( $fw, $key . ' ' . $this->padded_to_10_chars( $pos ) . ' ' . $this->padded_to_10_chars( $new_size ) . "\n" );
			fclose( $fw );

			$this->keys[$key] = array( $pos, $new_size, $pos_key );
		}
	}

	public function retrieve($key) {
		$key = sha1( $key );

		if ( isset( $this->keys[$key] ) ) {
			$pos = $this->keys[$key][0];
			$size = $this->keys[$key][1];

			$fr = fopen( $this->data_file, 'rb' );
			fseek( $fr, $pos );
			$data = fread( $fr, $size );
			fclose( $fr );

			return unserialize( $data );
		}
		return null;
	}

	public function erase() {

	}

	public function debug_dumpKeys() {
		print_r( $this->keys );
	}

	/////////////

	private function padded_to_10_chars( $x ) {
		return str_pad( $x, 10 );
	}

	private function initKeysFromFile() {
		$fr = fopen( $this->keys_file, 'rb' );
		while ( !feof( $fr ) ) {
			$key_position = ftell( $fr );
			$line = fgets( $fr );

			if ( empty( $line ) ) {
				break;
			}
			# do same stuff with the $line
			list( $key, $position, $size ) = explode( ' ', $line );
			$this->keys[$key] = array( 0 + $position, 0 + $size, 0 + $key_position );
		}
		fclose( $fr );
	}
}
