<?php

namespace Test;

use TemplateRest\Model\DOMElementTransclusion;

class TestDOMElementTransclusion extends \PHPUnit_Framework_TestCase
{

	private function getTransclusion( $xml, $index, $partIndex, $isFile=false )
	{
		$domDoc = new \DOMDocument();

		if ($isFile) {
			$domDoc->load( __DIR__ . '/' . $xml );
		} else {
			$domDoc->loadXML( $xml );
		}

		$xpath = new \DOMXPath( $domDoc );

		$transclusionElements = $xpath->query( '/body//span[@typeof="mw:Transclusion"]' );

		$transclusion = $transclusionElements->item($index);

		$data = \json_decode($transclusion->getAttribute('data-mw'));

		$target = $data->parts[$partIndex]->target->wt;
		
		$t = new DOMElementTransclusion( $target, $transclusion, $index, $partIndex );

		return array($t, $domDoc );
	}

	public function test()
	{
		list($t, $domDoc ) = $this->getTransclusion( 'Test1.xml', 0, 0, true );

		$params = $t->getParameters();

		$this->assertEquals( array_keys(get_object_vars($params)), array( '0' => '1', '1' => 'paramname' ) );

		$updatedParams = new \stdClass();

		$updatedParams->param1 = new \stdClass();
		$updatedParams->param1->wt = 'Updated';

		$t->setParameters( $updatedParams );

		list($t2, $domDoc2) = $this->getTransclusion( $domDoc->saveXML(), 0, 0, false );

		$this->assertEquals( array_keys(get_object_vars($t2->getParameters())), array( '0' => 'param1') );
	}

	public function testTarget()
	{
		list($t, $domDoc) = $this->getTransclusion( 'Test1.xml', 0, 0, true );

		$this->assertEquals( $t->getTarget(), "foo" );
	}

	public function testPatch()
	{
		list($t, $domDoc) = $this->getTransclusion( 'Test1.xml', 0, 0, true );

		$updatedParams = new \stdClass();

		$updatedParams->param1 = new \stdClass();
		$updatedParams->param1->wt = 'Updated';

		$t->patchParameters( $updatedParams );

		list($t2, $domDoc2) = $this->getTransclusion( $domDoc->saveXML(), 0, 0, false );

		$this->assertEquals( array_keys(get_object_vars($t2->getParameters())), array( '0' => '1', '1' => 'paramname', '2' => 'param1' ) );
		
	}

	public function testPatchRemove()
	{
		list($t, $domDoc) = $this->getTransclusion( 'Test1.xml', 0, 0, true );

		$updatedParams = new \stdClass();

		$t->patchParameters( $updatedParams, array( 'paramname' ) );

		list($t2, $domDoc2) = $this->getTransclusion( $domDoc->saveXML(), 0, 0, false );

		$this->assertEquals( array_keys(get_object_vars($t2->getParameters())), array( '0' => '1' ) );
	}

	public function testRemove()
	{
		list($t, $domDoc) = $this->getTransclusion( 'Test1.xml', 0, 0, true );
		$t->remove();

		$newDoc = new \DOMDocument();

		$newDoc->loadXML( $domDoc->saveXML() );

		$xpath = new \DOMXPath( $newDoc );

		$transclusionElements = $xpath->query( '/body//span[@typeof="mw:Transclusion"]' );

		$this->assertEquals( $transclusionElements->length, 0 );

		$templateElements = $xpath->query( '/body//span[@about="#mwt1"]' );

		$this->assertEquals( $templateElements->length, 0 );
	}

}