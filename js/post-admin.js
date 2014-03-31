/**
 * JS to power the post administration screen
 */

( function( $, _, window, undefined ) {

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

            $mappingTable.on( 'click', 'input.delete', handleDelete );
            $mappingMetaBox.on( 'click', 'input.add-new', handleAddNew );
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

} ( jQuery, _, window ) );
