<?php

require_once __DIR__ . '/../src/cache.class.php';

class BinaryCacheZippedTest extends \PHPUnit_Framework_TestCase {

	public function testZipped() {
		file_put_contents( 'cache/7505d64a54e061b7acd54ccd58b49dc43500b635.cache', '' );
		file_put_contents( 'cache/7505d64a54e061b7acd54ccd58b49dc43500b635.keys', '' );

		{
			$c = new BinaryCache('default', true);

			$c->store( 'a', 'aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc' );
			$c->store( 'b', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' );
			$c->store( 'c', 'ccccccccccccccccccccccc aaaaaaaaaaaaaaaaaaaaaaa ccccccccccccccccccccccc' );

			$this->assertEquals( 'aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc', $c->retrieve( 'a' ) );
			$c->store( 'b', 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB' );

			$this->assertEquals( 'aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc', $c->retrieve( 'a' ) );
			$this->assertEquals( 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB', $c->retrieve( 'b' ) );

			$this->assertTrue( $c->isCached( 'b' ) );
			$c->erase( 'b' );
			$this->assertEquals( null, $c->retrieve( 'b' ) );
			$this->assertFalse( $c->isCached( 'b' ) );

			$this->assertEquals( 'aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc', $c->retrieve( 'a' ) );
			$this->assertEquals( 'ccccccccccccccccccccccc aaaaaaaaaaaaaaaaaaaaaaa ccccccccccccccccccccccc', $c->retrieve( 'c' ) );
		}

		{
			$c2 = new BinaryCache('default', true);

			$this->assertFalse( $c->isCached( 'b' ) );
			$this->assertEquals( 'aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc', $c2->retrieve( 'a' ) );
			$this->assertEquals( 'ccccccccccccccccccccccc aaaaaaaaaaaaaaaaaaaaaaa ccccccccccccccccccccccc', $c2->retrieve( 'c' ) );
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
