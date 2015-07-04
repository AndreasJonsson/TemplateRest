<?php

namespace TemplateRest\Model;

interface Transclusion
{

	/**
	 * @return associative array with parameter names as keys and
	 * associative arrays of parameter information as values.
	 */
	function getParameters();

	/**
	 * Set the value of a parameter.
	 *
	 * @param array $parameterData
	 */
	function setParameters( $parameterData );

	/**
	 * Update the parameters listed, ignore other parameters.
	 *
	 * @param array $parameterData.
	 */
	function patchParameters( $parameterData );

	/**
	 * @return string The template title.
	 */
	function getTarget();

}