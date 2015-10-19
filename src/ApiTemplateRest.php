<?php
/**
 * Copyright (C) 2015 Andreas Jonsson <andreas.jonsson@kreablo.se>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Extensions
 */

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
			$this->dieUsage( "Permission denied!", 'badaccess-groups', 403 );
		}

		$this->init();

		$title = $this->getParameter( 'title' );
		$flat = !$this->getParameter( 'structured' );

		$contents = \file_get_contents('php://input');

		$data = \json_decode( $contents, true );

		try {
			switch ( strtoupper($_SERVER['REQUEST_METHOD']) ) {
				case 'GET':
					$this->doGet( $title, $data, $flat );
					break;
				case 'PUT':
					$this->doPut( $title, $data, $flat );
					break;
				case 'PATCH':
					$this->doPatch( $title, $data, $flat );
					break;
				case 'DELETE':
					$this->doDelete( $title, $data, $flat );
					break;
				default:
					$this->dieUsage( 'Unsupported method', 'unsupported-method', 501 );
					break;
			}
		}  catch (\UsageException $e) {
			throw $e;
		} catch (\Exception $e) {
			$this->dieUsage( "Caught exception: $e", 'templaterest-caught-exception', 500 );
		}
	}

	public function getAllowedParams() {
		return array( 'title' =>
			array(
				\ApiBase::PARAM_TYPE =>  'string',
				\ApiBase::PARAM_REQUIRED => true,
				\ApiBase::PARAM_ISMULTI => false
			),
			'structured' =>
			array(
				\ApiBase::PARAM_TYPE => 'boolean',
				\ApiBase::PARAM_REQUIRED => false,
				\ApiBase::PARAM_ISMULTI => false,
				\ApiBase::PARAM_DFLT => false
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
			$this->dieUsage( 'Parsoid URL not configured!', 'templaterest-parsoid-url',  500 );
		}

		if ( isset($wgParsoidDomain) ) {
			$domain = $wgParsoidDomain;
		} else if ( isset( $wgVisualEditorParsoidPrefix ) ) {
			$domain = $wgVisualEditorParsoidPrefix;
		} else {
			$this->dieUsage( 'Wiki domain for parsoid not configured!', 'templaterest-parsoid-domain', 500 );
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

	private function flatModel( $title, $model ) {
		$flatModel = array( 'id' => $title );
		foreach ( $model->getTransclusions() as $target ) {
			foreach ( $model->getTransclusionIds( $target ) as $i ) {
				// Mark transclusion as present, in case there are no parameters.
				$flatModel[ urlencode($target) . '/' . urlencode($i) ] = true;
				$transclusion = $model->getTransclusion( $target, $i );
				foreach ( $transclusion->getParameters() as $name => $value  ) {
					$flatModel[ urlencode($target) . '/' . urlencode($i) . '/' . urlencode($name) ] = $value;
				}
			}
		}
		return $flatModel;
	}

	private function structuredModel( $title, $model ) {
		$transclusions = array();
		foreach ( $model->getTransclusions() as $target ) {
			foreach ( $model->getTransclusionIds( $target ) as  $i) {
				$transclusion = $model->getTransclusion( $target, $i );
				if (!isset($transclusions[$target])) {
					$transclusions[$target] = array();
				}
				$transclusions[$target][$i] = array(
					'index' => $i,
					'params' => $transclusion->getParameters()
				);
			}
		}
		return $transclusions;
	}

	private function addModelToResult( $title, $model, $flatten = true ) {
		if ($flatten) {
			$result = $this->flatModel( $title, $model );
			$key = 'attributes';
		} else {
			$result = $this->structuredModel( $title, $model );
			$key = 'transclusions';
		}
		
		$this->getResult()->addValue( null, $key, $result );
	}

	private function doGet( $title, $data, $flat ) {
		$model = $this->getModel( $title );

		$this->getResult()->addValue( null, 'revision', $model->getRevision() );

		$this->addModelToResult( $title, $model, $flat );
	}

	private function doSomething( $title, $data, $summaryMessage, $what, $flat ) {
		
		$model = $this->getModelCheckEditPossible( $title, $data );

		$updatedTransclusions = array();

		 $doIt = function( $what, $model, $target, $instances, &$updatedTransclusions ) {
			if ( !is_array( $instances ) ) {
				$this->dieUsage( 'Transclusion parameter must be a map.', 'transclusions-parameter-must-be-array', 400 );
			}
			foreach ( $instances as $parameters ) {

				if (isset($parameters['index'])) {
					$index = $parameters['index'];
				} else {
					$index = null;
				}

				call_user_func_array( $what, array($model, $target, $index, $parameters, &$updatedTransclusions) );
			}
		 };

		if ( isset($data['transclusions'] ) ) {
			foreach ( $data['transclusions'] as $target => $instances ) {
				$doIt( $what, $model, $target, $instances, $updatedTransclusions );
			}
		}

		if ( isset($data['attributes']) ) {
			$transclusions = array();
			foreach ( $data['attributes'] as $name => $value ) {
				list( $target, $index, $parameter ) = $this->parseFlatParameter( $name );
				if ($target === null) {
					continue;
				}
				if (!isset($transclusions[$target])) {
					$transclusions[$target] = array();
				}
				if (!isset($transclusions[$target][$index])) {
					$transclusions[$target][$index] = array('index' => $index, 'params' => array());
				}
				if ( !empty( $parameter ) ) {
					$transclusions[$target][$index]['params'][$parameter] = $value;
				}
			}

			foreach ( $transclusions as $target => $instances ) {
				$doIt( $what, $model, $target, $instances, $updatedTransclusions );
			}
		}

		if ( count( $updatedTransclusions ) > 0 ) {
			$this->save( $title, $model, $data, $summaryMessage, $updatedTransclusions );
		}

		$this->getResult()->addValue( null, 'revision', $model->getRevision() );
		$this->addModelToResult( $title, $model, $flat );

	}

	private function doPut( $title, $data, $flat ) {

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

			}, $flat);
	}

	private function doDelete( $title, $data, $flat ) {

		$this->doSomething( $title, $data, 'templaterest-delete-templates', function( $model, $target, $index, $parameters, &$updatedTransclusions ) {

				if ( $index === null || ! \is_int( $index ) ) {
					$this->dieUsage( 'Index parameter missing or invalid', 'templaterest-index-parameter-missing-or-invalid', 400 );
				}

				if ( $model->removeTransclusion( $target, $index ) ) {
					$updatedTransclusions[] = $target . '-' . $index;
				}

			}, $flat);

	}

	private function doPatch( $title, $data, $flat ) {

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

			}, $flat);

	}

	private function getModelCheckEditPossible( $title, $data ) {
		if ( ! $this->getUser()->isAllowed( 'edit' ) ) {
			$this->dieUsage( 'Permission denied!', 'badaccess-groups', 403 );
		}
		$wikiPage = \WikiPage::factory( \Title::newFromText( $title ) );
		$revision = $wikiPage->getRevision()->getId();
		if ( $data['revision'] != $revision && ! (isset( $data['force'] ) && $data['force']) ) {
			$this->dieUsage( "Revision mismatch.  Current revision is $revision, model revision is {$data['revision']}." . print_r( $data, true ), 'revision-mismatch', 409 );
		}
		return $this->getModel( $title, $revision );
	}

	private function save( $pageName, $model, $data, $summaryMessage, $updatedTransclusions ) {

		$title = \Title::newFromText( $pageName );

		$wikiPage = \WikiPage::factory( $title );
		$wt = $this->parsoid->getPageWikitext( $pageName, $model->getXhtml() );

		if (isset($data['summary']) ) {
			global $wgContLang;

			# As in EditPage.php
			$summary = $summary = $wgContLang->truncate( $data['summary'], 255 );
		} else {
			$summary = $this->msg( $summaryMessage, implode(', ', $updatedTransclusions) );
		}

		$content = \ContentHandler::makeContent( $wt, $title );

		$status = $wikiPage->doEditContent( $content, $summary, 0, $model->getRevision() );

		if ( ! $status->isOK() ) {
			$this->getResult()->addValue( null, 'error', $status->getHTML() );
			$this->dieUsage( "Failed to save modified article.", 'save-failed', 500 );
		}

		$model->setRevision( $wikiPage->getRevision()->getId() );
	}

	private function validateTransclusion( $target, $parameters ) {
		$target = \Title::newFromText( $target, NS_TEMPLATE )->getText();

		if ( isset($parameters['index']) ) {
			if (!is_int( $parameters['index'] ) && $parameters['index'] >= 0 ) {
				$this->dieUsage( "Invalid index.", 'invalid-index', 400 );
			}
		} else {
			$parameters['index'] = 0;
		}

		if ( !isset($parameters['params']) ) {
			$this->dieUsage( "The transclusion parameters are not set on transclusion $target.", 'transclusion-params-not-set', 400 );
		}

		if ( !is_array($parameters['params']) ) {
			$this->dieUsage( "The transclusion parameters must be a map.", 'invalid-parameters-not-array', 400 );
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
			$this->dieUsageMsg( 'Unknown parameters in transclusion data: ' . implode( ', ', $unknown ), 'unknown-parameters', 400 );
		}

		foreach ( $parameters['params'] as $key => $value ) {
			if ( !isset( $value['wt'] ) ) {
				$this->dieUsage( "The parameter value is not set on parameter $key of transclusion $target-{$parameters['index']}.", 'parameter-value-not-set', 400);
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

	private function parseFlatParameter( $parameterName ) {
		$parts = \explode('/', $parameterName, 3);
		if ( count( $parts ) < 2 ) {
			return array(null, null,  null);
		}
		if ( count( $parts ) == 2 ) {
			return array( urldecode($parts[0]), (int)urldecode($parts[1]), null );
		}
		return array( urldecode($parts[0]), (int)urldecode($parts[1]), urldecode($parts[2]) );
	}

}