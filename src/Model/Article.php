<?php

namespace TemplateRest\Model;

interface Article
{

	/**
	 * Set the xhtml contents of the article.
	 * @param string $xhtml The xml content obtained from the parsoid server.
	 */
	function setXhtml( $xhtml );

	/**
	 * @return the xml contents of the article, suitable for passing to the parsoid server.
	 */
	function getXhtml( );

	/**
	 * @return array of unique template targets.  List of templates trascluded in the article contents.  (Only top-level transclusions are included, i.e., transclusions from trancluded templates are not included.)
	 */
	function getTransclusions();

	/**
	 * @param string $target
	 *
	 * @return int Number of transclusions of the given template contained in the article at top-level.
	 */
	function getNumberOfTransclusions( $target );

	/**
	 * @param string $target
	 * @param int $index
	 *
	 * @return reference to Transclusion model of a particular transclusion.  To obtain a reference to a new transclusion, call getTranclusion( 'Template', getNumberofTransclusion( 'Template ' )).
	 *
	 * @throws Exception if $index > getNumberOfTransclusions( $templateTitle ) for the given templateTitle.
	 */
	function &getTransclusion( $target, $index );

	/**
	 * Remove the occurence of the target template tranclusion corresponding to $index.
	 * 
	 * @param string $target
	 * @param int $index
	 */
	function removeTransclusion( $target, $index );

}