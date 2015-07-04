<?php

namespace TemplateRest\Model;


/**
 * Service interface for indicating model dirty/clean status.
 */
interface ModificationListener
{

	/**
	 * Indicate that a model part is dirty.
	 */
	function dirty();


	/**
	 * Indicate that a model part that previosly was dirty, is now
	 * clean.
	 */
	function clean();

}