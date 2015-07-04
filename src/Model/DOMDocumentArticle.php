<?php

namespace TemplateRest\Model;

class DOMDocumentArticle implements Article
{

	/**
	 * @var \DOMDocument
	 */
	private $domDocument = null;

	private $transclusions;

	/**
	 * Set the xhtml contents of the article.
	 * @param string $xhtml The xml content obtained from the parsoid server.
	 */
	public function setXhtml( $xhtml )
	{
		$this->domDocument = new \DOMDocument();
		$this->domDocument->preserveWhiteSpace = true;
		$this->domDocument->loadXML( $xhtml );

		$xpath = new \DOMXpath( $this->domDocument );

		$transclusionElements = $xpath->query( '/body//span[@typeof="mw:Transclusion"]' );

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
		return $this->domDocument->saveXML();
	}

	/**
	 * @return array of unique template target strings.  List of templates trascluded in the article contents.  (Only top-level transclusions are included, i.e., transclusions from trancluded templates are not included.)
	 */
	public function getTransclusions()
	{
		return array_keys($this->transclusions);
	}

	/**
	 * @param string $target
	 *
	 * @return int Number of transclusions of the given template contained in the article at top-level.
	 */
	public function getNumberOfTransclusions( $target )
	{
		if (isset($this->transclusions[$target])) {
			return count($this->transclusions[$target]);
		} else {
			return 0;
		}
	}

	/**
	 * @param string $target
	 * @param int $index
	 *
	 * @return reference to Transclusion model of a particular transclusion.  To obtain a reference to a new transclusion, call getTranclusion( 'Template', getNumberofTransclusion( 'Template ' )).
	 *
	 * @throws Exception if $index > getNumberOfTransclusions( $target ) for the given templateTitle.
	 */
	public function &getTransclusion( $target, $index )
	{
		$n = $this->getNumberOfTransclusions( $target );
		if ( $n < $index ) {
			throw new Exception('No transclusion of ' . $target . ' indexed ' . $index . ' in this article.');
		}

		if ( $n == $index ) {
			$e = $this->domDocument->createElement('span');
			$e->setAttribute('typeof', 'mw:Transclusion');
			$data = new \stdClass();
			$obj = new \stdClass();
			$obj->target = $target;
			$obj->params = array();
			$data->parts = array( $obj );
			$e->setAttribute('mw-data', \json_encode( $data ) );
			$this->domDoc->getElementsByTagName( 'body' )->item(0)->appendChild( $e );
			$this->transclusions[$target][] = new DOMElementTransclusion( $target, $e, $index, 0 );
		}

		return $this->transclusions[$target][$index];
	}

	private function addtransclusionElement( $transclusionElement )
	{
		$dataMw = $transclusionElement->getAttribute('data-mw');

		if ( $dataMw !== null ) {
			$data = \json_decode( $dataMw );

			$i = 0;
			foreach ( $data->parts as $part ) {
				if (isset( $part->target )) {
					$target = $this->getTarget($part->target);
					if ( !isset( $this->transclusions[$target] ) ) {
						$this->transclusions[$target] = array();
					}
					$this->transclusions[$target][] = new DOMElementTransclusion(
						$target,
						$transclusionElement,
						count($this->transclusions[$target]),
						$i
					);
				}
				$i++;
			}
		}
	}

	private function getTarget( $targetObj ) {
		if (isset( $targetObj->href ) ) {
			return substr($targetObj->href, 2);
		}
		return $targetObj->wt;
	}

}