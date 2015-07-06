<?php

namespace TemplateRest\Parsoid;

/**
 * Communication interface with the parsoid server.
 */
interface Parsoid
{

	/**
	 * @param string $pageName.
	 * @param int $revision.
	 * @return string the rendered xhtml of the page.
	 */
	function getPageXhtml( $pageName, $revision = null );


	/**
	 * @param string $pageName
	 * @param string $pageXhtml
	 * @return string wikitext
	 */
	function getPageWikitext( $pageName, $pageXhtml );

}