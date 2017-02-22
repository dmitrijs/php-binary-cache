<?php

namespace BinaryCache;

/**
 * Class BinaryCache
 *
 * v1.0.0 - First release
 * v1.1.0 - Compact cache files (zipped data, binary keys)
 * v1.1.1 - Misc tweaks
 */
class BinaryCache {

	/** @var string */
	private $cacheName;

	/** @var string */
    public $cacheDir = 'cache/';

	/** @var array */
	private $keys = array(); // sha1(key) -> position

    private $compact;
    private $data_file;
    private $keys_file;

	public function __construct($cacheName = 'default', $compact = false ) {
		$this->cacheName = $cacheName;
		$this->compact = $compact;
    }

    public function init() {
		$dir = $this->cacheDir;
		if ( !@mkdir( $dir ) && !is_dir( $dir ) ) {
			throw new \Exception( 'Could not create directory for cache' );
		}

		$this->data_file = $this->cacheDir . sha1( $this->cacheName ) . ($this->compact ? '.gz' : '') . '.cache';
		$this->keys_file = $this->cacheDir . sha1( $this->cacheName ) . ($this->compact ? '.gz' : '') . '.keys';

		if ( !is_file( $this->data_file ) ) {
			touch( $this->data_file );
		}
		if ( !is_file( $this->keys_file ) ) {
			touch( $this->keys_file );
		}

		$this->initKeysFromFile();
	}

	public function saveCompact() {
        if ($this->compact) {
            // Already zipped
            return;
        }

        $zippedCache = new BinaryCache($this->cacheName, true);
        $zippedCache->init();

        foreach ($this->keys as $hash => list($pos, $size, $pos_key, $timestamp)) {
            $data = $this->retrieve_raw($hash);
            $zippedCache->store_raw($hash, gzdeflate(serialize($data)), $timestamp);
        }
    }

	public function store( $key, $data ) {
        $hash = sha1( $key );
        $data = serialize( $data );
        if ($this->compact) {
            $data = gzdeflate($data);
        }

        $this->store_raw($hash, $data, time());
    }

	public function store_raw( $hash, $data, $timestamp ) {
        $new_size = strlen( $data );

		if ( isset( $this->keys[$hash] ) ) {
			list($pos, $size, $pos_key) = $this->keys[$hash];

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
				fwrite( $fw, $this->packHash($hash) . ' ' . $this->packLong( $pos ) . ' ' . $this->packLong( $new_size ) . ' ' . $this->packLong( $timestamp ) );
				fclose( $fw );

				$this->keys[$hash] = array( $pos, $new_size, $pos_key, $timestamp );
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
				fwrite( $fw, $this->packHash($hash) . ' ' . $this->packLong( $new_pos ) . ' ' . $this->packLong( $new_size ) . ' ' . $this->packLong( $timestamp ) );
				fclose( $fw );

				$this->keys[$hash] = array( $new_pos, $new_size, $pos_key, $timestamp );
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
			fwrite( $fw, $this->packHash($hash) . ' ' . $this->packLong( $pos ) . ' ' . $this->packLong( $new_size ) . ' ' . $this->packLong( time() ) . "\n" );
			fclose( $fw );

			$this->keys[$hash] = array( $pos, $new_size, $pos_key, $timestamp );
		}
	}

	public function retrieve( $key, $maxAgeInSeconds = - 1 ) {
		if ( $this->isCached( $key, $maxAgeInSeconds ) ) {
            $hash = sha1($key);
            return $this->retrieve_raw($hash);
		}
		return null;
	}

	private function retrieve_raw( $hash ) {
        list($pos, $size) = $this->keys[$hash];

        $fr = fopen( $this->data_file, 'rb' );
        fseek( $fr, $pos );
        $data = fread( $fr, $size );
        fclose( $fr );

        if ($this->compact) {
            $data = gzinflate($data);
        }
        return unserialize( $data );
	}

	public function erase( $key ) {
        $hash = sha1( $key );

		if ( isset( $this->keys[$hash] ) ) {
            list($pos, $size, $pos_key) = $this->keys[$hash];

			$fw = fopen( $this->data_file, 'r+b' );
			fseek( $fw, $pos );
			fwrite( $fw, str_repeat( "\0", $size ) );
			fclose( $fw );

			$fw = fopen( $this->keys_file, 'r+b' );
			fseek( $fw, $pos_key );
			if ($this->compact) {
                fwrite( $fw, str_repeat( "\0", 20 + 1 + 4 + 1 + 4 + 1 + 4 ) );
            } else {
                fwrite($fw, str_repeat("\0", 40 + 1 + 10 + 1 + 10 + 1 + 10));
            }
			fclose( $fw );

			unset( $this->keys[$hash] );
		}
	}

	public function isCached( $key, $maxAgeInSeconds = - 1 ) {
        $hash = sha1( $key );

		if ( isset( $this->keys[$hash] ) ) {
			$timePassed = time() - $this->keys[$hash][3];
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
            if ( $maxPos - $minPos === 0 ) {
                echo "Cache file is empty\n";
            } else {
                echo 'Unused space in cache file: ' . round( $gaps / ( $maxPos - $minPos ) * 100, 2 ) . '% (' . round( $gaps / 1024 ) . ' KB' . ")\n";
            }
		}
	}

	/////////////

	private function padded_to_10_chars( $x ) {
		return str_pad( $x, 10, "\0" );
	}

	private function packLong($long) {
	    if ($this->compact) {
            return pack('V', $long);
        }
        return $this->padded_to_10_chars($long);
    }

    private function packHash($hash) {
        if ($this->compact) {
            return hex2bin($hash);
        }
        return $hash;
    }

	private function unpackLong($long_str) {
	    if ($this->compact) {
	        $unpacked = unpack('V', $long_str);
            return reset($unpacked);
        }
        return 0 + (int)trim( $long_str );
    }

	private function initKeysFromFile() {
		$fr = fopen( $this->keys_file, 'rb' );
		while ( !feof( $fr ) ) {
			$key_position = ftell( $fr );
			if ($this->compact) {

			    $line = fread($fr, 20 + 1 + 4 + 1 + 4 + 1 + 4 + 1);
			    if (empty($line) || $line[0] === "\0") {
			        continue;
                }

			    $hash = substr($line, 0, 20);
                $position = substr($line, 20 + 1, 4);
                $size = substr($line, 20 + 1 + 4 + 1, 4);
                $time = substr($line, 20 + 1 + 4 + 1 + 4 + 1, 4);

                $hash = bin2hex($hash);
                $position = $this->unpackLong($position);
                $size = $this->unpackLong($size);
                $time = $this->unpackLong($time);

                $this->keys[$hash] = array( $position, $size, 0 + $key_position, $time );

            } else {
                $line = fgets( $fr );

                if ( empty( $line ) || $line[0] === "\0" ) {
                    continue;
                }
                # do same stuff with the $line
                list( $key, $position, $size, $time ) = explode( ' ', $line );
                $this->keys[$key] = array( 0 + (int)trim( $position ), 0 + (int)trim( $size ), 0 + $key_position, 0 + (int)trim( $time ) );
            }
		}
		fclose( $fr );
	}
}
