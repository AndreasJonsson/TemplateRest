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

namespace TemplateRest\Parsoid;

/**
 * Communication with parsoid over an HTTP connection.
 */
class HTTPParsoid implements Parsoid
{

	/**
	 * @var function MWHTTRequest factory function.
	 */
	private $requestFactory;

	/**
	 * @param string $url The url to the parsoid server.
	 */
	private $url;

	/**
	 * @param string $domain The domain that identifies the particular wiki to the parsoid server.
	 */
	private $domain;

	/**
	 * @param function $requestFactory Function to generate MWHttpRequest objects (normally MWHttpRequest::factory).
	 * @param string $url
	 * @param string $domain
	 */
	public function __construct( $requestFactory,  $url, $domain, $prefix )
	{
		$this->requestFactory = $requestFactory;
		$this->url = $url;
		$this->domain = $domain;
		$this->prefix = $prefix;
	}

	/**
	 * @param string $pageName.
	 * @param int $revision.
	 * @return string the rendered xhtml of the page.
	 */
	public function getPageXhtml( $pageName, $revision = null )
	{

        $factory = $this->requestFactory;

		$rev = $revision == null ? '' : '?oldid=' . $revision ;

		$url = $this->url . '/' . $this->prefix . '/'  . $this->pageName($pageName) . $rev;

        $request = call_user_func($factory,  $url, array( 'method' => 'get',  'followRedirects' => true ));

		$responseContent = '';

        $request->setCallback(function ($fh, $content) use (&$responseContent) {
                $responseContent .= $content;
                return strlen($content);
            });

        $request->execute();

        if ( ! $request->status->isGood() ) {
			throw new \Exception('Failed to get the page content from parsoid: ' . $url);
        }

		return $responseContent;
	}


	/**
	 * @param string $pageName
	 * @param string $pageXhtml
	 * @return string wikitext
	 */
	public function getPageWikitext( $pageName, $pageXhtml )
	{

        $factory = $this->requestFactory;

		$url = $this->url . '/' . $this->prefix . '/'  . $this->pageName($pageName);

        $request = call_user_func($factory, $url, array( 'method' => 'post',  'followRedirects' => true ) );

        $request->setData(
			\wfArrayToCgi(
				array(
					'html' => $pageXhtml,
				)
			)
		);

		$responseContent = '';
        $request->setCallback(function ($fh, $content) use (&$responseContent) {
                $responseContent .= $content;
                return \strlen($content);
            });

        $request->execute();

		$wt = \json_decode( $responseContent, true );

		if ( !(isset($wt['wikitext']) && isset($wt['wikitext']['body'])) ) {
			throw new \MWException('Did not get wikitext on expected format.');
		}

		return $wt['wikitext']['body'];
	}

	private function pageName( $pageName ) {
		$title = \Title::newFromText( $pageName );
		return urlencode( $title->getPrefixedDbKey() );
	}

}
