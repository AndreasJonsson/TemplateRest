<?php

namespace TemplateRest;

use TemplateRest\Parsoid\Parsoid;
use TemplateRest\Parsoid\HTTPParsoid;
use TemplateRest\Model\DOMDocumentArticle;

class ApiTemplateRest extends \ApiBase
{

	/**
	 * Communication interface with the parsoid server.
	 */
	private $parsoid;

	public function execute() {

		if ( ! $this->getUser()->isAllowed( 'read' ) ) {
			$this->dieUsageMsg( 'badaccess-groups' );
		}

		$this->init();

		$title = $this->getParameter( 'title' );

		$data = \json_decode(\file_get_contents('php://input'), true );

		switch ( strtoupper($_SERVER['REQUEST_METHOD']) ) {
			case 'GET':
				$this->doGet( $title, $data );
				break;
			case 'PUT':
				$this->doPut( $title, $data );
				break;
			case 'PATCH':
				$this->doPatch( $title, $data );
				break;
			case 'DELETE':
				$this->doDelete( $title, $data );
				break;
			default:
				$this->dieUsage( 'Unsupported method' );
				break;
		}
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

	private function init() {
		global $wgParsoidURL, $wgVisualEditorParsoidURL, $wgParsoidDomain, $wgVisualEditorParsoidPrefix;

		if ( isset( $wgParsoidURL ) ) {
			$url = $wgParsoidURL;
		} else if ( isset( $wgVisualEditorParsoidURL ) ) {
			$url = $wgVisualEditorParsoidURL;
		} else {
			$this->dieUsage( 'Parsoid URL not configured!' );
		}

		if ( isset($wgParsoidDomain) ) {
			$domain = $wgParsoidDomain;
		} else if ( isset( $wgVisualEditorParsoidPrefix ) ) {
			$domain = $wgVisualEditorParsoidPrefix;
		} else {
			$this->dieUsage( 'Wiki domain for parsoid not configured!' );
		}

		$this->parsoid = new HTTPParsoid( '\MWHttpRequest::factory', $url, $domain );
	}

	private function getModel( $title ) {
		$xhtml = $this->parsoid->getPageXhtml( $title );
		$model = new DOMDocumentArticle();
		$model->setXhtml( $xhtml );
		return $model;
	}

	private function doGet( $title, $data ) {
		$model = $this->getModel( $title );

		foreach ( $model->getTransclusions() as $target ) {
			$transclusions = array();
			for ( $i = 0 ; $i < $model->getNumberOfTransclusions( $target ); $i++ ) {
				$transclusions[] = $model->getTransclusion( $target, $i );
			}
			$this->getResult()->addValue( array( 'transclusions' ), $target, $transclusions );
		}
	}

}