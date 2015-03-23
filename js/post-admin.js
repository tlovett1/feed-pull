/**
 * JS to power the post administration screen
 */

( function( $, _, FP_Settings, window, undefined ) {

    "use strict";

    var postAdmin = ( function() {

        var _instance = null;

		var templateOptions = {
			evaluate: /<#([\s\S]+?)#>/g,
			interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
			escape: /\{\{([^\}]+?)\}\}(?!\})/g
		};

        /**
         * Setup functionality for post page
         */
        function postAdminControls() {
            var $mappingMetaBox = $( '#fp_field_mapping' );
            var $sourceMetaBox = $( '#fp_source_details' );
            var $customNamespacesDescription = $sourceMetaBox.find( '.custom-namespaces-description' );
            var $namespaceTable = $sourceMetaBox.find( 'table' );
            var $namespaceTableBody = $namespaceTable.find( 'tbody' );
            var $mappingTable = $mappingMetaBox.find( 'table' );
            var $mappingTableBody = $mappingTable.find( 'tbody' );
            var $manualPullButton = $( '#fp_manual_pull' );
            var $postIDField = $( '#post_ID' );
            var $manualPullSpinner = $( '#fp-spinner' );
            var $feedURLField = $( '#fp_feed_url' );

            var mappingRowTemplate = $( '#mapping-row-template' ).html();
            var namespaceRowTemplate = $( '#namespace-row-template' ).html();
            var logItemTemplate = $( '#log-item-template' ).html();
            var feedURLValue = null;

            function getLastMappingRowID() {
                return $mappingTableBody.find( 'tr').last().attr( 'data-mapping-row-id' );
            }

            function getLastNamespaceRowID() {
                return $namespaceTableBody.find( 'tr').last().attr( 'data-namespace-row-id' );
            }

            function handleFieldDelete( event ) {
                $( this ).parents( 'tr' ).fadeOut().remove();
            }

            function handleNamespaceDelete( event ) {
                function deleteComplete() {
                    $( this ).remove();

                    if ( $namespaceTableBody.find( 'tr').length < 1 ) {
                        $namespaceTable.hide();
                    }
                }
                $( this ).parents( 'tr' ).fadeOut( 400, deleteComplete )
            }

            function handleFieldAddNew( event ) {
                var nextMappingRowID = parseInt( getLastMappingRowID() ) + 1;
                var newRow = _.template( mappingRowTemplate, { rowID : nextMappingRowID }, templateOptions );
                $mappingTableBody.append( newRow );
            }

            function handleNamespaceAddNew( event ) {
                var nextNamespaceRowID = parseInt( getLastNamespaceRowID() ) + 1;
                var newRow = _.template( namespaceRowTemplate, {
                    rowID : nextNamespaceRowID,
                    'namespace_prefix' : '',
                    'namespace_url' : ''
                }, templateOptions );
                $namespaceTableBody.append( newRow );

                $namespaceTable.show();
            }

            function doManualPull() {
                $manualPullSpinner.animate( { 'opacity' : 1 } );

                $.ajax( {
                    'type' : 'post',
                    'url' : ajaxurl,
                    'dataType' : 'json',
                    'data' : {
                        'action' : 'pull',
                        'nonce' : FP_Settings.pull_nonce,
                        'source_feed_id' : $postIDField.val()
                    }
                } ).always( function() {
                    $manualPullSpinner.animate( { 'opacity' : 0 } );
                } );
            }

            function handlePossibleUnprefixedNamespace() {
                $.ajax( {
                    'type' : 'post',
                    'url' : ajaxurl,
                    'dataType' : 'json',
                    'data' : {
                        'action' : 'get_namespaces',
                        'nonce' : FP_Settings.get_namespaces_nonce,
                        'feed_url' : $feedURLField.val()
                    }
                } ).done( function( data, textStatus, jqXHR ) {

                    if ( $feedURLField.val() !== feedURLValue ) {
                        if ( data.namespaces && typeof data.namespaces[''] !== 'undefined' ) {

                            $customNamespacesDescription.html( FP_Settings.unprefixed_root_namespace );

                            var add_namespace = true

                            $namespaceTableBody.find( 'input[type=text]').each( function() {
                                if ( $( this ).val() == 'default' || $( this).val() == data.namespaces[''] ) {
                                    add_namespace = false;
                                }
                            } );

                            if ( add_namespace ) {
                                var nextNamespaceRowID = parseInt( getLastNamespaceRowID() ) + 1;
                                var newRow = _.template( namespaceRowTemplate, {
                                    'rowID' : nextNamespaceRowID,
                                    'namespace_prefix' : 'default',
                                    'namespace_url' : data.namespaces['']
                                }, templateOptions );

                                $namespaceTableBody.append( newRow );

                                $namespaceTable.show();
                            }
                        } else {
                            $customNamespacesDescription.html( FP_Settings.prefixed_root_namespace );
                        }
                    }

                }).always( function() {
                    feedURLValue = $feedURLField.val();
                } );
            }

            // Let's do this onload
            handlePossibleUnprefixedNamespace();

            $mappingTable.on( 'click', 'input.delete', handleFieldDelete );
            $mappingMetaBox.on( 'click', 'input.add-new', handleFieldAddNew );
            $namespaceTable.on( 'click', 'input.delete', handleNamespaceDelete );
            $sourceMetaBox.on( 'click', 'input.add-new', handleNamespaceAddNew );
            $feedURLField.on( 'blur', handlePossibleUnprefixedNamespace );
            $manualPullButton.on( 'click', doManualPull );
        }


        /**
         * Return singleton instance of postAdminControls
         * @returns {*}
         */
        function getInstance() {
            if ( _instance === null ) {
                _instance = new postAdminControls();
            }

            return _instance;
        }

        return {
            getInstance : getInstance
        }
    } )();

    postAdmin.getInstance();

} ( jQuery, _, FP_Settings, window ) );
