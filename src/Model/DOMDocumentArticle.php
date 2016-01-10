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

class DOMDocumentArticle implements Article
{

	/**
	 * @var \DOMDocument
	 */
	private $domDocument = null;

	private $transclusions;

	private $revision;

	/**
	 * Set the xhtml contents of the article.
	 * @param string $xhtml The xml content obtained from the parsoid server.
	 * @param int $revision The article revision.
	 */
	public function setXhtml( $xhtml, $revision = null )
	{
		$this->revision = $revision;
		$this->domDocument = new \DOMDocument();
		$this->domDocument->preserveWhiteSpace = true;
		$this->domDocument->loadXML( $xhtml );

		$xpath = new \DOMXpath( $this->domDocument );

		$transclusionElements = $xpath->query( '//body//*[contains(concat(" ", normalize-space(@typeof), " "), " mw:Transclusion ")]' );

		$this->transclusions = array();

		foreach ($transclusionElements as $transclusionElement) {
			$this->addTransclusionElement( $transclusionElement );
		}
	}

	/**
	 * @return the xml contents of the article, suitable for passing to the parsoid server.
	 */
	public function getXhtml( )
	{
		return $this->domDocument->saveXML( $this->domDocument->getElementsByTagName( 'body' )->item( 0 ) );
	}

	/**
	 * @return array of unique template target strings.  List of templates trascluded in the article contents.  (Only top-level transclusions are included, i.e., transclusions from trancluded templates are not included.)
	 */
	public function getTransclusions()
	{
		return array_keys( $this->transclusions );
	}

	/**
	 * @param string $target
	 *
	 * @return int Number of transclusions of the given template contained in the article at top-level.
	 */
	public function getNumberOfTransclusions( $target )
	{
		if ( isset($this->transclusions[$target]) ) {
			return count($this->transclusions[$target]);
		} else {
			return 0;
		}
	}


	public function getTransclusionIds( $target ) {
		$ids = array();
		if ( isset( $this->transclusions[$target] ) ) {
			foreach( $this->transclusions[$target] as $t ) {
				$ids[] = $t->getId();
			}
		}
		return $ids;
	}

	/**
	 * @param string $target
	 * @param int $id
	 *
	 * @return reference to Transclusion model of a particular transclusion.  To obtain a reference to a new transclusion, call getTranclusion( 'Template', getNumberofTransclusion( 'Template ' )).
	 *
	 */
	public function &getTransclusion( $target, $id )
	{

		if ( !isset( $this->transclusions[$target] ) ) {
			$this->transclusions[$target] = array();
		}

		if ( !isset( $this->transclusions[$target][$id] ) ) {
			$e = $this->domDocument->createElement('p');
			$e->setAttribute('typeof', 'mw:Transclusion');
			$e->setAttribute('about', '#mwt' . $this->getTemplateNumber());
			$data = array(
				'parts' => array(
					array(
						'template' => array(
							'target' => array(
								'wt'   => $target,
								'href' => './' . $target
							),
							'params' => array(),
							'i' => 0
						)
					)
				)
			);

			$e->setAttribute('data-mw', \json_encode( $data ) );
			$body = $this->domDocument->getElementsByTagName( 'body' )->item(0);
			$body->appendChild( $this->domDocument->createTextNode( "\n\n" ) );
			$body->appendChild( $e );
			$body->appendChild( $this->domDocument->createTextNode( "\n\n" ) );
			$this->transclusions[$target][$id] = new DOMElementTransclusion( $target, $e, $id, 0 );
		}

		return $this->transclusions[$target][$id];
	}

	/**
	 * Remove the occurence of the target template tranclusion corresponding to $id.
	 * 
	 * @param string $target
	 * @param int $id
	 * 
	 * @return boolean true If a template was removed.  False if the template did not exist.
	 */
	public function removeTransclusion( $target, $id )
	{
		$n = $this->getNumberOfTransclusions( $target );
		if ( !(isset( $this->transclusions[$target]) && isset( $this->transclusions[$target][$id] )) ) {
			return false;
		}

		$this->transclusions[$target][$id]->remove();
		array_splice( $this->transclusions[$target], $id, 1 );

		return true;
	}

	public function getRevision()
	{
		return $this->revision;
	}

	public function setRevision( $revision )
	{
		$this->revision = $revision;
	}

	private function addtransclusionElement( $transclusionElement )
	{
		$dataMw = $transclusionElement->getAttribute('data-mw');

		if ( $dataMw !== null ) {
			$data = \json_decode( $dataMw, true );

			$i = 0;
			foreach ( $data['parts'] as $part ) {
				if (isset( $part['template'] )) {
					$target = $this->getTarget($part['template']['target']);
					if ($target === null) {
						continue;
					}
					if ( !isset( $this->transclusions[$target] ) ) {
						$this->transclusions[$target] = array();
					}
					$id = count( $this->transclusions[$target] );
					$this->transclusions[$target][] = new DOMElementTransclusion(
						$target,
						$transclusionElement,
						$id,
						$i
					);
				}
				$i++;
			}
		}
	}

	private function getTarget( $targetObj ) {
		if (isset( $targetObj['href'] ) ) {
			$target = substr($targetObj['href'], 2);
		} else {
			$target = $targetObj['wt'];
		}
		$title = \Title::newFromText( $target, NS_TEMPLATE );
		if ($title === null) {
			return null;
		}

		return $title->getText();
	}

	private function getTemplateNumber() {
		$n = 1;
		foreach ($this->transclusions as $target => $transclusions) {
			$n += count($transclusions);
		}
		return $n;
	}
}