/**
 * JS to power the post administration screen
 */

( function( $, _, FP_Settings, window, undefined ) {

    "use strict";

    var postAdmin = ( function() {

        var _instance = null;

        /**
         * Setup functionality for post page
         */
        function postAdminControls() {
            var $mappingMetaBox = $( '#fp_field_mapping' );
            var $mappingTable = $mappingMetaBox.find( 'table' );
            var $mappingTableBody = $mappingTable.find( 'tbody' );
            var $manualPullButton = $( '#fp_manual_pull' );
            var $postIDField = $( '#post_ID' );
            var $manualPullSpinner = $( '#fp-spinner' );

            function getLastMappingRowID() {
                return $mappingTableBody.find( 'tr').last().attr( 'data-mapping-row-id' );
            }

            function handleDelete( event ) {
                $( this ).parents( 'tr' ).fadeOut().remove();
            }

            function handleAddNew( event ) {
                var nextMappingRowID = parseInt( getLastMappingRowID() ) + 1;

                var template = $( '#mapping-row-template' ).html();
                var newRow = _.template( template, { rowID : nextMappingRowID } );
                $mappingTableBody.append( newRow );
            }

            function doManualPull() {
                $manualPullSpinner.animate( { 'opacity' : 1 } );

                $.ajax( {
                    'type' : 'post',
                    'url' : ajaxurl,
                    'data' : {
                        'action' : 'pull',
                        'nonce' : FP_Settings.nonce,
                        'source_feed_id' : $postIDField.val()
                    }
                }).complete( function() {
                    $manualPullSpinner.animate( { 'opacity' : 0 } );
                } );
            }

            $mappingTable.on( 'click', 'input.delete', handleDelete );
            $mappingMetaBox.on( 'click', 'input.add-new', handleAddNew );
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
