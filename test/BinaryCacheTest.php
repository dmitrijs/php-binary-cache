<?php

use BinaryCache\BinaryCache;

require_once __DIR__ . '/../src/BinaryCache/BinaryCache.php';

class BinaryCacheTest extends \PHPUnit_Framework_TestCase {

	public function testEverything() {
		file_put_contents( 'cache/default.cache', '' );
		file_put_contents( 'cache/default.keys', '' );

		{
			$c = new BinaryCache();
            $c->init();

			$c->store( 'a', 'aaa' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8
			$c->store( 'b', 'bbb' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8
			$c->store( 'c', 'ccc' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8

			$this->assertEquals( 'aaa', $c->retrieve( 'a' ) );
			$c->store( 'b', 'bbb2' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8

			$this->assertEquals( 'aaa', $c->retrieve( 'a' ) );
			$this->assertEquals( 'bbb2', $c->retrieve( 'b' ) );

			$this->assertTrue( $c->isCached( 'b' ) );
			$c->erase( 'b' );
			$this->assertEquals( null, $c->retrieve( 'b' ) );
			$this->assertFalse( $c->isCached( 'b' ) );

			$this->assertEquals( 'aaa', $c->retrieve( 'a' ) );
			$this->assertEquals( 'ccc', $c->retrieve( 'c' ) );
		}

		{
			$c2 = new BinaryCache();
            $c2->init();

			$this->assertFalse( $c->isCached( 'b' ) );
			$this->assertEquals( 'aaa', $c2->retrieve( 'a' ) );
			$this->assertEquals( 'ccc', $c2->retrieve( 'c' ) );
		}
	}

	public function testMaxAgeInSeconds() {
		file_put_contents( 'cache/default.cache', '' );
		file_put_contents( 'cache/default.keys', '' );

		{
			$c = new BinaryCache();
            $c->init();

			$c->store( 'a', 'aaa' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8

			$this->assertEquals( 'aaa', $c->retrieve( 'a' ) );
			$this->assertTrue( $c->isCached( 'a' ) );

			sleep( 2 );

			$this->assertEquals( 'aaa', $c->retrieve( 'a' ) );
			$this->assertTrue( $c->isCached( 'a' ) );

			$this->assertEquals( null, $c->retrieve( 'a', 1 ) );
			$this->assertFalse( $c->isCached( 'a', 1 ) );

			$this->assertEquals( 'aaa', $c->retrieve( 'a', 10 ) );
			$this->assertTrue( $c->isCached( 'a' ), 10 );
		}

		{
			$c2 = new BinaryCache();
            $c2->init();

			$this->assertEquals( null, $c2->retrieve( 'a', 1 ) );
			$this->assertFalse( $c2->isCached('a', 1) );

			$this->assertEquals( 'aaa', $c2->retrieve( 'a', 10 ) );
			$this->assertTrue( $c2->isCached('a'), 10 );
		}
	}

	public function testOverwriteData() {
		file_put_contents( 'cache/default.cache', '' );
		file_put_contents( 'cache/default.keys', '' );

		$c = new BinaryCache();
        $c->init();
		$c->store( 'a', 'very long data' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8
		$c->store( 'b', 'also long data' ); // e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98
		$c->store( 'c', 'qwertyuiop' ); // e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98

		$this->assertEquals( 'very long data', $c->retrieve( 'a' ) );
		$this->assertEquals( 'also long data', $c->retrieve( 'b' ) );
		$this->assertEquals( 'qwertyuiop', $c->retrieve( 'c' ) );

		$data_size = filesize( 'cache/default.cache' );
		$keys_size = filesize( 'cache/default.keys' );

		$c->store( 'a', 'smaller' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8
		$c->store( 'b', 'small' ); // e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98
		$c->store( 'c', 'same size!' ); // e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98

		$this->assertEquals( $data_size, filesize( 'cache/default.cache' ) );
		$this->assertEquals( $keys_size, filesize( 'cache/default.keys' ) );
	}

	public function testBigMultilineData() {
		file_put_contents( 'cache/default.cache', '' );
		file_put_contents( 'cache/default.keys', '' );

		{
			$c = new BinaryCache();
            $c->init();
			$c->store( 'a', 'утф8 тест' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8
		}
		{
			$c2 = new BinaryCache();
            $c2->init();
			$this->assertEquals('утф8 тест', $c2->retrieve('a'));
		}
	}

	public function testCacheNameEncoding() {
		file_put_contents( 'cache/2e8308cd8340ad30de8150b4c6b4ef4f11ebb2a2.cache', '' );
		file_put_contents( 'cache/2e8308cd8340ad30de8150b4c6b4ef4f11ebb2a2.keys', '' );

		{
			$c = new BinaryCache('~!@#$%^&*(){}:"<>?,./;\'[]`');
            $c->init();
			$c->store( 'weird', 'weird1' );
		}
		{
			$c2 = new BinaryCache('~!@#$%^&*(){}:"<>?,./;\'[]`');
            $c2->init();
			$this->assertEquals('weird1', $c2->retrieve('weird'));
		}
	}

	private function dumpCacheFiles($key = 'default') {
		$key = sha1($key);

		echo "data:\n";
		echo "0         1         2         3         4         5         6         7\n";
		echo "01234567890123456789012345678901234567890123456789012345678901234567890\n";
		echo $this->dump( "cache/{$key}.cache" );

		echo "\nkeys:\n";
		echo $this->dump( "cache/{$key}.keys" );
	}

	private function dump( $file ) {
		$s = file_get_contents( $file );
		$s = str_replace( "\0", '_', $s );
		return $s;
	}
}
