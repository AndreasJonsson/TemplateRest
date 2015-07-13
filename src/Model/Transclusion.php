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

interface Transclusion
{

	/**
	 * @return associative array with parameter names as keys and
	 * associative arrays of parameter information as values.
	 */
	function getParameters();

	/**
	 * Set the value of a parameter.
	 *
	 * @param array $parameterData
	 */
	function setParameters( $parameterData );

	/**
	 * Update the parameters listed, ignore other parameters.
	 *
	 * @param array $parameterData.
	 */
	function patchParameters( $parameterData );

	/**
	 * @return string The template title.
	 */
	function getTarget();

	/**
	 * Remove this transclusion.
	 */
	function remove();
}