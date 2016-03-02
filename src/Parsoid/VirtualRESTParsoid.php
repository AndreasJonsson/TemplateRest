<?php

namespace TemplateRest\Parsoid;

class VirtualRESTParsoid implements Parsoid {

	private $serviceClient;

	public function __construct( \Config $config )
	{
		$this->serviceClient = new \VirtualRESTServiceClient( new \MultiHttpClient( array() ) );
		$this->serviceClient->mount('/restbase/', $this->getVRSObject( $config ) );
	}

	/**
	 * Mostly copied from ApiVisualEditor
	 */
	private function getVRSObject( \Config $config )
	{
		// the params array to create the service object with
		$params = array();
		// the VRS class to use, defaults to Parsoid
		$class = '\ParsoidVirtualRESTService';
		$vrs = $config->get( 'VirtualRestConfig' );

		if ( isset( $vrs['modules'] ) && isset( $vrs['modules']['restbase'] ) ) {
			// if restbase is available, use it
			$params = $vrs['modules']['restbase'];
			$params['parsoidCompat'] = false; // backward compatibility
			$class = '\RestbaseVirtualRESTService';
		} elseif ( isset( $vrs['modules'] ) && isset( $vrs['modules']['parsoid'] ) ) {
			// there's a global parsoid config, use it next
			$params = $vrs['modules']['parsoid'];
			$params['restbaseCompat'] = true;
		} else {
			// no global modules defined, fall back to old defaults
			$params = array(
				'URL' => $config->get( 'VisualEditorParsoidURL' ),
				'prefix' => $config->get( 'VisualEditorParsoidPrefix' ),
				'domain' => $config->get( 'VisualEditorParsoidDomain' ),
				'timeout' => $config->get( 'VisualEditorParsoidTimeout' ),
				'HTTPProxy' => $config->get( 'VisualEditorParsoidHTTPProxy' ),
				'forwardCookies' => $config->get( 'VisualEditorParsoidForwardCookies' ),
				'restbaseCompat' => true
			);
		}
		// merge the global and service-specific params
		if ( isset( $vrs['global'] ) ) {
			$params = array_merge( $vrs['global'], $params );
		}
		// set up cookie forwarding
		if ( $params['forwardCookies'] && !\User::isEveryoneAllowed( 'read' ) ) {
			$params['forwardCookies'] = \RequestContext::getMain()->getRequest()->getHeader( 'Cookie' );
		} else {
			$params['forwardCookies'] = false;
		}
		// create the VRS object and return it
		return new $class( $params );
	}

	private function requestRestbase( $method, $path, $params ) {
		$request = array(
			'method' => $method,
			'url' => '/restbase/local/v1/' . $path
		);
		if ( $method === 'GET' ) {
			$request['query'] = $params;
		} else {
			$request['body'] = $params;
		}
		$response = $this->serviceClient->run( $request );
		if ( $response['code'] === 200 && $response['error'] === "" ) {
			// If response was served directly from Varnish, use the response
			// (RP) header to declare the cache hit and pass the data to the client.
			$headers = $response['headers'];
			$rp = null;
			if ( isset( $headers['x-cache'] ) && strpos( $headers['x-cache'], 'hit' ) !== false ) {
				$rp = 'cached-response=true';
			}
			if ( $rp !== null ) {
				$resp = $this->getRequest()->response();
				$resp->header( 'X-Cache: ' . $rp );
			}
		} elseif ( $response['error'] !== '' ) {
			throw new \Exception( 'parsoidserver-http-error: ' . $response['error'] );
		} else { // error null, code not 200
			throw new \Exception( 'parsoidserver-http: HTTP ' . $response['code'] );
		}
		return $response['body'];
	}


	/**
	 * @param string $pageName.
	 * @param int $revision.
	 * @return string the rendered xhtml of the page.
	 */
	public function getPageXhtml( $pageName, $revision = null ) {
		$title = \Title::newFromText( $pageName );

		if ($title->exists()) {
			if ( $revision !== null && $revision !== 0 ) {
				$oldid = $revision;
			} else {
				$latestRevision = \Revision::newFromTitle( $title );
				if ( $latestRevision === null ) {
					throw new \Exception( 'Could not find latest revision for title ' . $title );
				}
				$oldid = $latestRevision;
			}

			$content = $this->requestRestbase(
				'GET',
				'page/html/' . \urlencode( $title->getPrefixedDBkey() ) . '/' . $oldid,
				array()
			);

			if ( $content === false ) {
				throw new \Exception( 'Error contacting the Parsoid server' );
			}

			return $content;
		} else {
			throw new \Exception( "Page does not exist: " . $title );
		}
	}

	/**
	 * @param string $pageName
	 * @param string $pageXhtml
	 * @return string wikitext
	 */
	public function getPageWikitext( $pageName, $pageXhtml ) {
		$title = \Title::newFromText( $pageName );
		$path = 'transform/html/to/wikitext/' . urlencode( $title->getPrefixedDBkey() );
		return $this->requestRestbase(
			'POST',
			$path,
			array(
				'html' => $pageXhtml,
				'scrubWikitext' => 1,
			)
		);
	}



}