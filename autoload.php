<?php

spl_autoload_register(function ($class) {

		$namespaces = array(
			'TemplateRest' => '/src/'
		);

		foreach ($namespaces as $ns => $dir) {

			if (strpos($class, "$ns\\") === 0) {
				$class = substr( $class, strlen( $ns ) + 1 );
				require_once __DIR__ . $dir . str_replace('\\', '/', $class) . '.php';
				break;
			}

		}

	});