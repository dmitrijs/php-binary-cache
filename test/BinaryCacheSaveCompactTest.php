<?php

use BinaryCache\BinaryCache;

require_once __DIR__ . '/../src/BinaryCache/BinaryCache.php';

class BinaryCacheSaveCompactTest extends \PHPUnit_Framework_TestCase {

	public function testSaveZipped() {
        file_put_contents( 'cache/default.cache', '' );
        file_put_contents( 'cache/default.keys', '' );
        file_put_contents( 'cache/default.gz.cache', '' );
        file_put_contents( 'cache/default.gz.keys', '' );

        {
            $c = new BinaryCache('default');
            $c->init();

            $c->store( 'a', 'aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc' );
            $c->store( 'b', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' );
            $c->store( 'c', 'ccccccccccccccccccccccc aaaaaaaaaaaaaaaaaaaaaaa ccccccccccccccccccccccc' );

            $c->store( 'b', 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB' );
            $c->erase( 'a' );

            $c->saveCompact();
        }

        {
            $c2 = new BinaryCache('default', true);
            $c2->init();

            $this->assertFalse( $c2->isCached( 'a' ) );
            $this->assertEquals( 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB', $c2->retrieve( 'b' ) );
            $this->assertEquals( 'ccccccccccccccccccccccc aaaaaaaaaaaaaaaaaaaaaaa ccccccccccccccccccccccc', $c2->retrieve( 'c' ) );
        }
	}
}
