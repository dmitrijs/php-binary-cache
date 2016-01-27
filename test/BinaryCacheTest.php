<?php

require_once __DIR__ . '/../src/cache.class.php';

class BinaryCacheTest extends \PHPUnit_Framework_TestCase {

	public function testSomething() {

//		file_put_contents( __DIR__ . '/../src/cache/7505d64a54e061b7acd54ccd58b49dc43500b635.cache', '' );
//		file_put_contents( __DIR__ . '/../src/cache/7505d64a54e061b7acd54ccd58b49dc43500b635.keys', '' );

		$c = new BinaryCache();
		$c->store( 'a', 'asd' ); // 86f7e437faa5a7fce15d1ddcb9eaeaea377667b8
		$c->store( 'a', 'zxcv' );
		$c->store( 'b', 'fgh' ); // e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98
		$c->store( 'c', 'bnmnbn' ); // e9d71f5ee7c92d6dc9e92ffdad17b8bd49418f98

		var_dump( $c->retrieve( 'a' ) );
		var_dump( $c->retrieve( 'b' ) );
		var_dump( $c->retrieve( 'c' ) );

		$c->erase( 'b' );

		echo "data:\n";
		echo "0         1         2         3         4         5         6         7\n";
		echo "01234567890123456789012345678901234567890123456789012345678901234567890\n";
		echo $this->dump( __DIR__ . '/../src/cache/7505d64a54e061b7acd54ccd58b49dc43500b635.cache' );

		echo "\nkeys:\n";
		echo $this->dump( __DIR__ . '/../src/cache/7505d64a54e061b7acd54ccd58b49dc43500b635.keys' );
	}

	private function dump( $file ) {
		$s = file_get_contents( $file );
		$s = str_replace( "\1", '_', $s );
		return $s;
	}
}
