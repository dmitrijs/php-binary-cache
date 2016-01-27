<?php

require_once __DIR__ . '/../src/cache.class.php';

class BinaryCacheTest extends \PHPUnit_Framework_TestCase {

	public function testEverything() {
		file_put_contents( __DIR__ . '/../src/cache/7505d64a54e061b7acd54ccd58b49dc43500b635.cache', '' );
		file_put_contents( __DIR__ . '/../src/cache/7505d64a54e061b7acd54ccd58b49dc43500b635.keys', '' );

		$c = new BinaryCache();

		$c->store( 'a', 'aaa' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8
		$c->store( 'b', 'bbb' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8
		$c->store( 'c', 'ccc' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8

		$this->assertEquals( 'aaa', $c->retrieve( 'a' ) );
		$c->store( 'b', 'bbb2' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8

		$this->assertEquals( 'aaa', $c->retrieve( 'a' ) );
		$this->assertEquals( 'bbb2', $c->retrieve( 'b' ) );

		$this->assertTrue( $c->isCached( 'b' ) );
		$c->erase('b');
		$this->assertEquals( null, $c->retrieve( 'b' ) );
		$this->assertFalse( $c->isCached( 'b' ) );

		$this->assertEquals( 'aaa', $c->retrieve( 'a' ) );
		$this->assertEquals( 'ccc', $c->retrieve( 'c' ) );
	}

	public function testOverwriteData() {
		file_put_contents( __DIR__ . '/../src/cache/7505d64a54e061b7acd54ccd58b49dc43500b635.cache', '' );
		file_put_contents( __DIR__ . '/../src/cache/7505d64a54e061b7acd54ccd58b49dc43500b635.keys', '' );

		$c = new BinaryCache();
		$c->store( 'a', 'very long data' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8
		$c->store( 'b', 'also long data' ); // e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98
		$c->store( 'c', 'qwertyuiop' ); // e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98

		$this->assertEquals( 'very long data', $c->retrieve( 'a' ) );
		$this->assertEquals( 'also long data', $c->retrieve( 'b' ) );
		$this->assertEquals( 'qwertyuiop', $c->retrieve( 'c' ) );

		$data_size = filesize( __DIR__ . '/../src/cache/7505d64a54e061b7acd54ccd58b49dc43500b635.cache' );
		$keys_size = filesize( __DIR__ . '/../src/cache/7505d64a54e061b7acd54ccd58b49dc43500b635.keys' );

		$c->store( 'a', 'smaller' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8
		$c->store( 'b', 'small' ); // e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98
		$c->store( 'c', 'same size!' ); // e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98

		$this->assertEquals( $data_size, filesize( __DIR__ . '/../src/cache/7505d64a54e061b7acd54ccd58b49dc43500b635.cache' ) );
		$this->assertEquals( $keys_size, filesize( __DIR__ . '/../src/cache/7505d64a54e061b7acd54ccd58b49dc43500b635.keys' ) );
	}


	private function dumpCacheFiles($key = 'default') {
		$key = sha1($key);

		echo "data:\n";
		echo "0         1         2         3         4         5         6         7\n";
		echo "01234567890123456789012345678901234567890123456789012345678901234567890\n";
		echo $this->dump( __DIR__ . "/../src/cache/{$key}.cache" );

		echo "\nkeys:\n";
		echo $this->dump( __DIR__ . "/../src/cache/{$key}.keys" );
	}

	private function dump( $file ) {
		$s = file_get_contents( $file );
		$s = str_replace( "\0", '_', $s );
		return $s;
	}
}
