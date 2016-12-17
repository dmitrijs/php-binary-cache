<?php

require_once __DIR__ . '/../src/cache.class.php';

class BinaryCacheSaveCompactTest extends \PHPUnit_Framework_TestCase {

	public function testSaveZipped() {
        file_put_contents( 'cache/7505d64a54e061b7acd54ccd58b49dc43500b635.cache', '' );
        file_put_contents( 'cache/7505d64a54e061b7acd54ccd58b49dc43500b635.keys', '' );
        file_put_contents( 'cache/7505d64a54e061b7acd54ccd58b49dc43500b635.gz.cache', '' );
        file_put_contents( 'cache/7505d64a54e061b7acd54ccd58b49dc43500b635.gz.keys', '' );

        {
            $c = new BinaryCache('default');

            $c->store( 'a', 'aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc aaa bbb ccc' );
            $c->store( 'b', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' );
            $c->store( 'c', 'ccccccccccccccccccccccc aaaaaaaaaaaaaaaaaaaaaaa ccccccccccccccccccccccc' );

            $c->store( 'b', 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB' );
            $c->erase( 'a' );

            $c->saveCompact();
        }

        {
            $c2 = new BinaryCache('default', true);

            $this->assertFalse( $c2->isCached( 'a' ) );
            $this->assertEquals( 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB', $c2->retrieve( 'b' ) );
            $this->assertEquals( 'ccccccccccccccccccccccc aaaaaaaaaaaaaaaaaaaaaaa ccccccccccccccccccccccc', $c2->retrieve( 'c' ) );
        }
	}
}
