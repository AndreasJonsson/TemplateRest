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

	private $modificationListener;

	private $dirty = false;

	private $originalParameters = null;

	function __construct( ModificationListener $modificationListener, $target, \DOMElement &$domElement, $index, $partIndex )
	{
		$this->target = $target;
		$this->domElement = $domElement;
		$this->index = $index;
		$this->partIndex = $partIndex;
		$this->modificationListener = $modificationListener;
	}

	/**
	 * @return object
	 */
	public function getParameters()
	{
		$dataMw = \json_decode($this->domElement->getAttribute('data-mw'));
		return $dataMw->parts[$this->partIndex]->params;
	}

	/**
	 * Set the parameters.
	 *
	 * @param object $parameterData
	 */
	public function setParameters( $parameterData )
	{
		$dataMw = \json_decode($this->domElement->getAttribute('data-mw'));

		if ($this->originalParameters === null) {
			$this->originalParameters = clone($dataMw->parts[$this->partIndex]->params);
		}

		$dataMw->parts[$this->partIndex]->params = $parameterData;

		if ($this->paramsUpdated($parameterData)) {
			$this->domElement->setAttribute('data-mw', \json_encode($dataMw) );
			$this->dirty();
		} else {
			$this->clean();
		}
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

		if ($this->originalParameters === null) {
			$this->originalParameters = clone($dataMw->parts[$this->partIndex]->params);
		}

		foreach ( get_object_vars( $parameterData ) as $paramName => $paramInfo ) {
			$dataMw->parts[$this->partIndex]->params->{$paramName} = $paramInfo;
		}

		foreach ($removeParameters as $remove) {
			unset($dataMw->parts[$this->partIndex]->params->{$remove});
		}

		if ($this->paramsUpdated($dataMw->parts[$this->partIndex]->params) ) {
			$this->domElement->setAttribute('data-mw', \json_encode($dataMw) );
			$this->dirty();
		} else {
			$this->clean();
		}
	}

	/**
	 * @return string The template title.
	 */
	public function getTarget()
	{
		return $this->target;
	}

	private function paramsUpdated( $parameterData )
	{
		return ! self::objects_equal( $this->originalParameters, $parameterData );
	}

	private function dirty()
	{
		if (!$this->dirty) {
			$this->dirty = true;
			$this->modificationListener->dirty();
		}
	}

	private function clean()
	{
		if ($this->dirty) {
			$this->dirty = true;
			$this->modificationListener->clean();
		}
	}

	private static function objects_equal($a, $b)
	{
		if ( \is_object($a) ) {
			if ( !\is_object($b) ) {
				return false;
			}
			$avars = get_object_vars( $a );
			$bvars = get_object_vars( $b );
			if ( count($avars) != count($bvars) ) {
				return false;
			}
			foreach ( $avars as $var => $aval ) {
				if ( isset( $a->{$var} ) && !isset( $b->{$var} ) ) {
					return false;
				}
				if ( !isset( $b->{$var} ) ) {
					continue;
				}
				if ( !self::objects_equal($aval, $b->{$var}) ) {
					return false;
				}
			}
			return true;
		}  elseif ( \is_array($a) ) {
			if ( \length( $a ) != \length( $b ) ) {
				return false;
			}
			foreach ( $a as $key => $val ) {
				if ( isset( $a[$key] ) && !isset( $b[$key] ) ) {
					return false;
				}
				if ( !isset( $b[$key] ) ) {
					continue;
				}
				if ( !self::objects_equal($val, $b[$key]) ) {
					return false;
				}
			}
			return true;
		}
		return $a === $b;
	}
}