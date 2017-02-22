<?php

use BinaryCache\BinaryCache;

require_once __DIR__ . '/../src/BinaryCache/BinaryCache.php';

class BinaryCacheCompactTest extends \PHPUnit_Framework_TestCase {

	public function testZipped() {
        file_put_contents( 'cache/default.gz.cache', '' );
        file_put_contents( 'cache/default.gz.keys', '' );

		{
			$c = new BinaryCache('default', true);
			$c->init();

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
            $c2->init();

			$this->assertFalse( $c->isCached( 'b' ) );
			$this->assertEquals( 'aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc', $c2->retrieve( 'a' ) );
			$this->assertEquals( 'ccccccccccccccccccccccc aaaaaaaaaaaaaaaaaaaaaaa ccccccccccccccccccccccc', $c2->retrieve( 'c' ) );
		}
	}
}
