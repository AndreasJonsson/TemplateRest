<?php

namespace TemplateRest\Model;

/**
 * Implementation that directly maintains the DOMElement that represents the transclusion.
 */
class DOMElementTransclusion implements Transclusion
{

	private $target;

	private $domElement;

	private $index;

	private $partIndex;

	private $dirty = false;

	function __construct( $target, \DOMElement &$domElement, $index, $partIndex )
	{
		$this->target = $target;
		$this->domElement = $domElement;
		$this->index = $index;
		$this->partIndex = $partIndex;
	}

	/**
	 * @return object
	 */
	public function getParameters()
	{
		$dataMw = \json_decode($this->domElement->getAttribute('data-mw'));
		return $dataMw->parts[$this->partIndex]->template->params;
	}

	/**
	 * Set the parameters.
	 *
	 * @param object $parameterData
	 */
	public function setParameters( $parameterData )
	{
		$dataMw = \json_decode($this->domElement->getAttribute('data-mw'));

		$dataMw->parts[$this->partIndex]->template->params = $parameterData;

		$this->domElement->setAttribute('data-mw', \json_encode($dataMw) );
	}

	/**
	 * Update the parameters listed, ignore other parameters.
	 *
	 * @param object $parameterData.
	 * @param array $removeParameters.
	 */
	public function patchParameters( $parameterData, $removeParameters = array() )
	{
		$dataMw = \json_decode($this->domElement->getAttribute('data-mw'));

		foreach ( get_object_vars( $parameterData ) as $paramName => $paramInfo ) {
			$dataMw->parts[$this->partIndex]->template->params->{$paramName} = $paramInfo;
		}

		foreach ($removeParameters as $remove) {
			unset($dataMw->parts[$this->partIndex]->template->params->{$remove});
		}

		$this->domElement->setAttribute('data-mw', \json_encode($dataMw) );
	}

	/**
	 * @return string The template title.
	 */
	public function getTarget()
	{
		return $this->target;
	}

	/**
	 * Remove this transclusion.
	 */
	public function remove()
	{
		$dataMw = \json_decode($this->domElement->getAttribute('data-mw'));

		array_splice( $dataMw->parts, $this->partIndex, 1 );

		if ( count($dataMw->parts) === 0 ) {
			$about = $this->domElement->getAttribute( 'about' );
			$this->domElement->parentNode->removeChild( $this->domElement );
			if ( !empty( $about ) ) {
				$xpath = new \DOMXPath( $this->domElement->ownerDocument );
				$nodeList = $xpath->query( '/body//*[@about="' . $about . '"]' );
				for ( $i = 0; $i < $nodeList->length; $i++ ) {
					$node = $nodeList->item( $i );
					$node->parentNode->removeChild( $node );
				}
			}
		} else {
			$this->domElement->setAttribute( 'data-mw', \json_encode($dataMw) );
		}
	}
}