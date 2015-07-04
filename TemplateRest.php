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

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'TemplateRest',
	'author' => array(
		'Andreas Jonsson'
	),
	'version' => '0.1',
	'descriptionmsg' => 'templaterest-desc',
	'url' => ''
);

$wgMessagesDirs['TemplateRest'] = __DIR__ . '/i18n';

$wgAPIModules['templaterest'] = 'ApiTemplateRest';

$wgAutoloadClasses['ApiTemplateRest'] = __DIR__ . '/ApiTemplateRest.php';

/*

$wgAutoloadClasses['TemplateRestHooks'] = __DIR__ . '/TemplateRest.hooks.php';

*/

/*
$wgResourceModules['ext.templaterest'] = array(
	'scripts' => array()
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'templaterest',
);
*/
