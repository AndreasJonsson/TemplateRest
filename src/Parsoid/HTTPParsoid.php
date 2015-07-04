<?php

namespace TemplateRest\Parsoid;

class HTTPParsoid implements Parsoid
{

	private $requestFactory;

	private $url;

	private $domain;

	private $format;

	public function __construct( $requestFactory,  $url, $domain, $format = 'html')
	{
		$this->requestFactory = $requestFactory;
		$this->url = $url;
		$this->domain = $domain;
		$this->format = $format;
	}


	/**
	 * @param string $pageName.
	 * @return string the rendered xhtml of the page.
	 */
	public function getPageXhtml( $pageName )
	{

        $factory = $this->requestFactory;

        $request = $factory( $this->url . '/v2/' . $this->domain . '/' . $this->format . '/' . $pageName, array( 'method' => 'get',  'followRedirects' => true ) );

		$responseContent = '';

        $request->setCallback(function ($fh, $content) use (&$responseContent) {
                $responseContent .= $content;
                return strlen($content);
            });

        $request->execute();

        if ( ! $request->status->isGood() ) {
			throw new Exception('Failed to get the page content from parsoid: ' . $request->status);
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

        $request = $factory( $this->url . '/v2/' . $this->domain . '/wt/' . $pageName, array( 'method' => 'post',  'followRedirects' => true ) );

        $request->setData(
           wfArrayToCgi(
			   array(
				   'html' => $pageXhtml,
			   )
		   )
		);

		$responseContent = '';
        $request->setCallback(function ($fh, $content) use (&$responseContent) {
                $responseContent .= $content;
                return strlen($content);
            });

        $request->execute();

		return $resposeContent;
	}


}