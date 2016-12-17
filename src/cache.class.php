<?php

class BinaryCache {

	/** @var string */
	private $cacheName;

	/** @var string */
	private $cacheDir = 'cache/';

	/** @var array */
	private $keys = array(); // sha1(key) -> position

    private $zip;

	public function __construct( $cacheName = 'default', $zip = false ) {
		$this->cacheName = $cacheName;
		$this->zip = $zip;

		$dir = $this->cacheDir;
		if ( !@mkdir( $dir ) && !is_dir( $dir ) ) {
			throw new \Exception( 'Could not create directory for cache' );
		}

		$this->data_file = $this->cacheDir . sha1( $this->cacheName ) . ($this->zip ? '.gz' : '') . '.cache';
		$this->keys_file = $this->cacheDir . sha1( $this->cacheName ) . ($this->zip ? '.gz' : '') . '.keys';

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
			list($pos, $size, $pos_key) = $this->keys[$key];

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
		    list($pos, $size) = $this->keys[$hash];

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
            list($pos, $size, $pos_key) = $this->keys[$key];

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

	public function showFragmentationInfo( ) {
		$keysAsc = array();
		$minPos = 0;
		$maxPos = 0;
		foreach ($this->keys as list($pos, $size)) {
			$keysAsc[$pos] = $pos + $size;
			$maxPos = max($maxPos, $pos + $size);
		}

		$did_something = true;
		while ($did_something) {
			$did_something = false;

			$keys = array_keys($keysAsc);
			foreach ($keys as $key) {
				if (isset($keysAsc[$key])) {
					$value = $keysAsc[$key];
					if ( isset( $keysAsc[$value] ) ) {
						$keysAsc[$key] = $keysAsc[$value];
						unset( $keysAsc[$value] );
						$did_something = true;
					}
				}
			}
		}

		ksort($keysAsc);

		{
			$pos = 0;
			$gaps = 0;
			foreach ( $keysAsc as $key => $val ) {
				if ( $key > $pos ) {
					$gaps += $key - $pos;
				}
				$pos = $val;
			}
			if ( $pos < $maxPos ) {
				$gaps += $maxPos - $pos;
			}
			echo 'Unused space in cache file: ' . round($gaps / ($maxPos - $minPos) * 100, 2) . '% (' . round( $gaps / 1024) . ' KB' . ")\n";
		}
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
