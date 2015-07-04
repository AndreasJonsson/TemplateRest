<?php

$doc = new DOMDocument();
$doc->load( 'Test1.xml' );

$xpath = new DOMXPath( $doc );

$transclusionElements = $xpath->query( '/body//span[@typeof="mw:Transclusion"]' );

$transclusion = $transclusionElements->item(0);

$data = json_decode($transclusion->getAttribute('data-mw'));

$data->parts[0]->params->paramname->wt = 'Updated value';

$transclusion->setAttribute('data-mw', json_encode( $data ) );

echo $doc->saveXML();
