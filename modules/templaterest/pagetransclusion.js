(function(mw, Backbone, _) {

    var PageTransclusions = Backbone.Model.extend({

        pageRevision: null,

        url: function() {
            return mw.util.wikiScript('api') + '?action=templaterest&format=json&title=' + encodeURIComponent(this.get('id'));
        },

        parse: function ( attributes, options ) {
            this.pageRevision = attributes['revision'];
            return attributes['attributes'];
        },

        toJSON: function () {
            var attrs = Backbone.Model.prototype.toJSON.apply(this);
            return {
                revision: this.pageRevision,
                attributes: attrs
            };
        },

        sync: function (method, model, options) {
            var xhr;
            options = _.extend({ parse: true, processData: true,
                                 xhr: function () {
                                     xhr = $.ajaxSettings.xhr();
                                     return xhr;
                                 }
                               },
                               options
                              );
            var success = options.success;
            options.success = function (model, resp, options) {
                var data = JSON.parse( xhr.responseText );
                this.pageRevision = data['revision'];
                if (success) {
                    success.call(options.context, model, resp, options);
                }
            }
            return Backbone.sync.apply(this, [method, model, options]);
        },

        parameterName: function( target, index, parameter ) {
            var targetTitle = mw.Title.newFromText(target, mw.config.get('wgNamespaceIds').template)
			return encodeURI(targetTitle.getMain()) + '/' + encodeURI(index) + '/' + encodeURI(parameter);
        },

        getTransclusionParameter: function ( target, index, parameter ) {
            var param = this.get( this.parameterName( target, index, parameter ) );
            if (typeof(param) == 'object' && typeof(param.wt) == 'string') {
                return param.wt;
            }
            return null;
        },

        setTransclusionParameter: function ( target, index, parameter, value, options ) {
            var param = {};
            if (typeof(options) == 'undefined') {
                options = {};
            }
            param.wt = value;
            this.set( this.parameterName( target, index, parameter ), param, options );
        },

        getTransclusions: function ( onlyTarget ) {
            var attrs = this.prototype.toJSON.apply(this);
            var keys = _.keys(attributes);
            var transclusions = {};

            for (var i = 0; i < keys.length; i++) {

                var key = keys[i];

                if (attrs[key] == this.idAttribute) {
                    continue;
                }

                var parts = key.split('/');
                if (parts.length < 2) {
                    continue;
                }

                var target = parts[0],
                    index  = parts[1];

                if ( typeof(onlyTarget) === 'string' && target != onlyTarget ) {
                    continue;
                }

                if ( typeof(transclusions[target]) !== 'object' ) {
                    transclusions[target] = [];
                }

                transclusions[target].push( index );
            }

            if ( typeof(onlyTarget) === 'string' ) {
                if (typeof(transclusions[target]) === 'object') {
                    return transclusions[target];
                }
                return [];
            }
            return transclusions;
        }

    });

    if ( typeof(mw.templaterest) !== 'object') {
        mw.templaterest = {};
    }

    mw.templaterest.PageTransclusions = PageTransclusions;

})(mediaWiki, Backbone, _);