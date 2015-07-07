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

		$contents = \file_get_contents('php://input');

		$data = \json_decode( $contents, true );

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

	public function getAllowedParams() {
		return array( 'title' =>
			array(
				\ApiBase::PARAM_TYPE =>  'string',
				\ApiBase::PARAM_REQUIRED => true,
				\ApiBase::PARAM_ISMULTI => false
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

	private function getModel( $title, $revision = null ) {
		if ( $revision == null ) {
			$wikiPage = \WikiPage::factory( \Title::newFromText( $title ) );
			$revision = $wikiPage->getRevision()->getId();
		}
		$xhtml = $this->parsoid->getPageXhtml( $title, $revision );
		$model = new DOMDocumentArticle();
		$model->setXhtml( $xhtml, $revision );
		return $model;
	}

	private function addModelToResult( $model ) {
		foreach ( $model->getTransclusions() as $target ) {

			$transclusions = array();

			foreach ( $model->getTransclusionIds( $target ) as  $i) {
				$transclusion = $model->getTransclusion( $target, $i );
				$transclusions[] = array(
					'index' => $i,
					'params' => $transclusion->getParameters()
				);
			}
			$this->getResult()->addValue( array( 'transclusions' ), $target, $transclusions );
		}
	}

	private function doGet( $title, $data ) {
		$model = $this->getModel( $title );

		$this->getResult()->addValue( null, 'revision', $model->getRevision() );

		$this->addModelToResult( $model );
	}

	private function doSomething( $title, $data, $saveMessage, $what ) {
		
		$model = $this->getModelCheckEditPossible( $title, $data );

		$updatedTransclusions = array();

		foreach ( $data['transclusions'] as $target => $instances ) {
			if ( !is_array( $instances ) ) {
				$this->dieUsage( 'Transclusion parameter must be a map.', 'transclusions-parameter-must-be-array' );
			}
			foreach ( $instances as $parameters ) {

				if (isset($parameters['index'])) {
					$index = $parameters['index'];
				} else {
					$index = null;
				}

				call_user_func_array( $what, array($model, $target, $index, $parameters, &$updatedTransclusions) );
			}
		}

		if ( count( $updatedTransclusions ) > 0 ) {
			$this->save( $title, $model, $data, $saveMessage, $updatedTransclusions );
		}

		$this->addModelToResult( $model );

	}

	private function doPut( $title, $data ) {

		$this->doSomething( $title, $data, 'templaterest-put-templates', function( $model, $target, $index, $parameters, &$updatedTransclusions ) {
				$updated = false;

				$this->validateTransclusion( $target, $parameters );

				if ( $model->getNumberOfTransclusions( $target ) == $index ) {
					$updated = true;
				}

				$transclusion = $model->getTransclusion( $target, $index );

				if ( $updated || $this->checkUpdated( $transclusion, $parameters ) ) {
					$updatedTransclusions[] = $target . '-' . $index;
					$transclusion->setParameters( $parameters['params'] );
				}

			});
	}

	private function doDelete( $title, $data ) {

		$this->doSomething( $title, $data, 'templaterest-delete-templates', function( $model, $target, $index, $parameters, &$updatedTransclusions ) {

				if ( $index === null || ! \is_int( $index ) ) {
					$this->dieUsageMessage( 'templaterest-index-parameter-missing-or-invalid' );
				}

				if ( $model->removeTransclusion( $target, $index ) ) {
					$updatedTransclusions[] = $target . '-' . $index;
				}

			});

	}

	private function doPatch( $title, $data ) {

		$this->doSomething( $title, $data, 'templaterest-patch-templates', function( $model, $target, $index, $parameters, &$updatedTransclusions ) {
				$updated = false;

				$this->validateTransclusion( $target, $parameters );

				if ( $model->getNumberOfTransclusions( $target ) == $index ) {
					$updated = true;
				}

				$transclusion = $model->getTransclusion( $target, $index );

				$oldParameters = $transclusion->getParameters();

				foreach ( $parameters['params'] as $key => $value ) {
					if ( !isset( $oldParameters[$key] ) || $oldParameters[$key] !== $value ) {
						$updated = true;
						$oldParameters[$key] = $value;
					}
				}

				if ( $updated ) {
					$updatedTransclusions[] = $target . '-' . $index;
					$transclusion->setParameters( $oldParameters );
				}

			});

	}

	private function getModelCheckEditPossible( $title, $data ) {
		if ( ! $this->getUser()->isAllowed( 'edit' ) ) {
			$this->dieUsageMsg( 'badaccess-groups' );
		}
		$wikiPage = \WikiPage::factory( \Title::newFromText( $title ) );
		$revision = $wikiPage->getRevision()->getId();
		if ( $data['revision'] !== $revision && ! (isset( $data['force'] ) && $data['force']) ) {
			$this->dieUsage( "Revision mismatch.", 'revision-mismatch' );
		}
		return $this->getModel( $title, $revision );
	}

	private function save( $pageName, $model, $data, $saveMessage, $updatedTransclusions ) {

		$title = \Title::newFromText( $pageName );

		$wikiPage = \WikiPage::factory( $title );
		$wt = $this->parsoid->getPageWikitext( $pageName, $model->getXhtml() );

		if (isset($data['summary']) ) {
			$summary = $data['summary'];
		} else {
			$summary = $this->msg( $saveMessage, implode(', ', $updatedTransclusions) );
		}

		$content = \ContentHandler::makeContent( $wt, $title );

		$status = $wikiPage->doEditContent( $content, $summary, 0, $model->getRevision() );

		if ( ! $status->isOK() ) {
			$this->getResult()->addValue( null, 'error', $status->getHTML() );
			$this->dieUsage( "Failed to save modified article.", 'save-failed' );
		}
	}

	private function validateTransclusion( $target, $parameters ) {
		$target = \Title::newFromText( $target, NS_TEMPLATE )->getText();

		if ( isset($parameters['index']) ) {
			if (!is_int( $parameters['index'] ) && $parameters['index'] >= 0 ) {
				$this->dieUsage( "Invalid index.", 'invalid-index' );
			}
		} else {
			$parameters['index'] = 0;
		}

		if ( !isset($parameters['params']) ) {
			$this->dieUsage( "The transclusion parameters are not set on transclusion $target.", 'transclusion-params-not-set' );
		}

		if ( !is_array($parameters['params']) ) {
			$this->dieUsage( "The transclusion parameters must be a map.", 'invalid-parameters-not-array' );
		}

		if ( count($parameters) > 2 ) {
			$unknown = array();
			foreach ($param as $parameter) {
				switch ($param) {
					case 'index':
					case 'params':
						break;
					default:
						$unknown[] = $param;
				}
			}
			$this->dieUsageMsg( 'Unknown parameters in transclusion data: ' . implode( ', ', $unknown ), 'unknown-parameters' );
		}

		foreach ( $parameters['params'] as $key => $value ) {
			if ( !isset( $value['wt'] ) ) {
				$this->dieUsage( "The parameter value is not set on parameter $key of transclusion $target-{$parameters['index']}.", 'parameter-value-not-set');
			}
		}

		return array( $target, $parameters );
	}

	private function checkUpdated( $transclusion, $parameters ) {
		$oldParams = $transclusion->getParameters();
		$newParams = $parameters['params'];
		if (count($oldParams) != count($newParams)) {
			return true;
		}

		foreach( $oldParams as $key => $value ) {
			if (!isset($newParams[$key])) {
				return true;
			}
			if ( $oldParams[$key] != $newParams[$key] ) {
				return true;
			}
		}

		return false;
	}

}