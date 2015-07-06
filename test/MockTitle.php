<?php

define( 'NS_TEMPLATE', 10 );

class Title {

	var $text;

	public function __construct( $text ) {
		$this->text = $text;
	}

	public static function newFromText( $text, $namespace = 0 )
	{
		return new Title( $text );
	}

	public function getText()  {
		return $this->text;
	}
}


function wfDebug( $msg )
{
	echo $msg;
}