# Extension:TemplateRest

Template rest is a RESTful API module that takes advantage of a [Parsoid server](https://www.mediawiki.org/wiki/Parsoid) to manipulate template transclusions in MediaWiki articles.  It also supports manipulating categories.

## Installation

1. Install a parsoid server ([instructions])[https://www.mediawiki.org/wiki/Parsoid/Setup]
1. Configure parsoid access in LocalSettings.php ($wgVirtualRestConfig['modules']['parsoid']['url'])
1. Install [Extension:JSModules](https://github.com/AndreasJonsson/JSModules) (if you want to use the included javascript model.)
1. Copy TemplateRest extension to extensions folder of your MediaWiki installation
1. add <code>wfLoadExtension('TemplateRest');</code> to LocalSettings.php

## Accessing the API

The entry point to the API will be depending on the settings of $wgScriptPath of your MediaWiki installation.

   $wgServer/$wgScriptPath/api.php?action=templaterest

Example:

   http://example.com/w/api.php?action=templaterest&title=Animal_Farm&structured=1

## Query parameters

<table>
  <tr><th>Parameter</th><th>type</th><th>Mandatory?</th><th>Default</th><th>Description</th></tr>
  <tr><td>title</td><td>string</td><td>yes</td><td></td><td>The title of the article.</td></tr>
  <tr><td>force</td><td>boolean</td><td>no</td><td><i>unset</i></td><td>Ignore revision mismatch.</td></tr>
  <tr><td>structured</td><td>boolean</td><td>no</td><td><i>unset</i></td><td>With the structured parameter set to true, the produced result will contain nested objects.  Otherwise, the result will be a flat object with hierarcically structured attribute names on the form <code>templatename/index/parameter</code>.  The index is used to distinguish several transclusions of the same template.</td></tr>
  <tr><td>withCategories</td><td>boolean</td><td>no</td><td><i>unset</i></td><td>Include categories.  There will be two sets of categories: <em>editableCategories</em> and <em>readonlyCategories</em>.  The latter will be ignored on modifications.</td></tr>
</table>

## Supported methods

For the PUT, PATCH, and DELETE methods, the transclusion model must be included in the body of the HTTP request encoded as JSON.  The pageRevision parameter must be set (unless 'force' is set) and must match the latest revision of he page.

<table>
  <tr><th>HTTP Method</th><th>Description</th></tr>
  <tr><td>GET</td><td>Fetches the transclusions in latest revision of the page.  Categories may be included if the <em>withCategories</em> parameter is set.</td></tr>
  <tr><td>PUT</td><td>Sets the transclusions included in the request.  Omitted transclusions and transclusion parameters will be deleted from the page.  With <em>withCategories</em>, also sets the editable categories of the page.</td></tr>
  <tr><td>PATCH</td><td>Updates the transclusions by setting only the transclusion parameters included in the request.  With <em>withCategories</em>, also sets the editable categories (same as for PUT).</td></tr>
  <tr><td>DELETE</td><td>Deletes template transclusion from a page.  (Note that deleting individual parameters is not currently supported.)</td></tr>
</table>

## Javascript Model

The included javascript model is based on [backbone](http://backbonejs.org/), which must be installed separately.  Backbone is included in the [Extension:JSModules package](https://github.com/AndreasJonsson/JSModules).

The model extends Backbone.Model and is exposed to the global environment via the variable mw.templaterest.PageTransclusions.

<table>
  <tr><th>Member</th><th>type</th><th>default</th><th>Description</th></tr>
  <tr><td>pageRevision</td><td><code>string</code></td><td><code>null</code></td><td>The page revision.  Will be set when the model is fetched.</td></tr>
  <tr><td>withCategories</td><td><code>boolean</code></td><td><code>false</code></td><td>Include categories in the model.</td></tr>
  <tr><td>parameterName</td><td><code>function(target, index, parameter)</code></td><td>-</td><td>Generate a matching parameter name for the non-structured model.</td></tr>
  <tr><td>getTransclusionParameter</td><td><code>function(target, index, parameter)</code></td><td>-</td><td>Get the wikitext value of the parameter.</td></tr>
  <tr><td>setTransclusionParameter</td><td><code>function(target, index, parameter, value)</code></td><td>-</td><td>Set the wikitext value of the parameter.</td></tr>
  <tr><td>getTransclusions</td><td><code>function( onlyTarget )</code></td><td>-</td><td>Get the list of transclusions.  The returned value is a map whith template names as keys and arrays of indicies as values.  If the parameter <em>onlyTarget</em> is set, only the array of indicies corresponding to the given target template will be returned.</td></tr>
</table>

Example usage:

 var transclusions = new mw.templaterest.PageTransclusions({ title: 'Animal Farm' });
 transclusions.fetch();
 transclusions.setTransclusionParameter( 'Infobox book', 0, 'title', 'Animal Farm' );
 transclusions.setTransclusionParameter( 'Infobox book', 0, 'author', '[[George Orwell]]' );
 transclusions.setTransclusionParameter( 'Infobox book', 0, 'published', '17 August 1945' );
 transclusions.on('sync', function() { alert('Successfully updated page'); });
 transclusions.on('error', function() { alert('Failed to update page'); });
 transclusions.save();

Please see the [backbone documentation](http://backbonejs.org/) for details.
