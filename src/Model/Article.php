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

namespace TemplateRest\Model;

interface Article
{

	/**
	 * Set the xhtml contents of the article.
	 * @param string $xhtml The xml content obtained from the parsoid server.
	 */
	function setXhtml( $xhtml, $revision = null );

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
	 *
	 * @return array Existing ids of transclusions of this target template.
	 */
	function getTransclusionIds( $target );

	/**
	 * @param string $target
	 * @param int $id
	 *
	 * @return reference to Transclusion model of a particular transclusion.  To obtain a reference to a new transclusion, call getTranclusion( 'Template', getNumberofTransclusion( 'Template ' )).
	 *
	 * @throws Exception if $index > getNumberOfTransclusions( $templateTitle ) for the given templateTitle.
	 */
	function &getTransclusion( $target, $id );

	/**
	 * Remove the occurence of the target template tranclusion corresponding to $index.
	 * 
	 * @param string $target
	 * @param int $id
	 * 
	 * @return boolean true If a template was removed.  False if the template did not exist.
	 */
	function removeTransclusion( $target, $id );

	/**
	 * @return int The revision of the article.
	 */
	function getRevision();

}