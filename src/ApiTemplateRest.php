<?php

namespace TemplateRest;

use TemplateRest\Parsoid\Parsoid;
use TemplateRest\Parsoid\HTTPParsoid;

class ApiTemplateRest extends \ApiBase
{

	/**
	 * Communication interface with the parsoid server.
	 */
	private $parsoid;

	public function execute() {
		$this->parsoid = new HTTPParsoid( '\MWHttpRequest::factory', 
	}

	public function getAllowedParameters() {
		return array( 'title' =>
			array(
				ApiBase::PARAM_TYPE =>  'string',
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_ISMULTI => false
			)
		);
	}

}