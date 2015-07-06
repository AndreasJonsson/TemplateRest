<?php

namespace Test;

use TemplateRest\Model\DOMDocumentArticle;

require_once __DIR__ . '/MockTitle.php';

class TestDOMDocumentArticle extends \PHPUnit_Framework_TestCase
{

	public function test()
	{
		$article = new DOMDocumentArticle();

		$article->setXhtml( \file_get_contents( __DIR__ . '/Test1.xml' ) );

		$this->assertEquals( 1, $article->getNumberOfTransclusions( 'foo' ) );

		$this->assertEquals( array( 'foo' ), $article->getTransclusions() );
	}

	public function test2()
	{
		$article = new DOMDocumentArticle();

		$article->setXhtml( \file_get_contents( __DIR__ . '/Test2.xml' ) );

		$this->assertEquals( array( 'Mall:Bottles_of_beer_-_fulltext', 'Mall:Bottles_of_beer_-_table' ), $article->getTransclusions() );

	}

	public function test3()
	{
		$article = new DOMDocumentArticle();

		$article->setXhtml( \file_get_contents( __DIR__ . '/Test3.xml' ) );

		$this->assertEquals( array( 'Mall:Bottles_of_beer' ), $article->getTransclusions() );

		$this->assertEquals( array( 0, 1, 2, 3 ), $article->getTransclusionIds( 'Mall:Bottles_of_beer' ) );
	}

	public function testModify()
	{
		$article = new DOMDocumentArticle();

		$article->setXhtml( \file_get_contents( __DIR__ . '/Test1.xml' ) );

		$t = $article->getTransclusion( 'foo', 0 );

		$updatedParams = new \stdClass();

		$updatedParams->param1 = new \stdClass();
		$updatedParams->param1->wt = 'Updated';

		$t->patchParameters( $updatedParams, array( 1 ));

		$a2 = new DOMDocumentArticle();
		$a2->setXhtml( $article->getXhtml() );

		$this->assertEquals( array( 'foo' ), $a2->getTransclusions() );

		$t = $a2->getTransclusion( 'foo', 0 );

		$this->assertEquals( array_keys(get_object_vars($t->getParameters())), array( 'paramname', 'param1' ) );
	}

}

