<?php

namespace TemplateRest\Parsoid;

/**
 * Communication interface with the parsoid server.
 */
interface Parsoid
{

	/**
	 * @param string $pageName.
	 * @return string the rendered xhtml of the page.
	 */
	function getPageXhtml( $pageName );


	/**
	 * @param string $pageName
	 * @param string $pageXhtml
	 * @return string wikitext
	 */
	function getPageWikitext( $pageName, $pageXhtml );

}