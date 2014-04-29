/**
 * JS to power the post administration screen
 */

( function( $, FP_Settings, window, undefined ) {

    "use strict";

    var settingsAdmin = ( function() {

        var _instance = null;

        function settingsAdminControls() {

            var $spinner = $( '#fp-spinner' );

            function handleDeletedPostReset() {
                $spinner.animate( { 'opacity' : 1 } );

                $.ajax( {
                    'type' : 'post',
                    'url' : ajaxurl,
                    'data' : {
                        'action' : 'reset_deleted_posts',
                        'nonce' : FP_Settings.reset_deleted_posts_nonce
                    },
                    'dataType' : 'json'
                } ).always( function() {
                    $spinner.animate( { 'opacity' : 0 } );
                } );
            }

            $( '#fp_reset_deleted_syndicated_posts' ).on( 'click', handleDeletedPostReset );
        }

        /**
         * Return singleton instance of postAdminControls
         * @returns {*}
         */
        function getInstance() {
            if ( _instance === null ) {
                _instance = new settingsAdminControls();
            }

            return _instance;
        }

        return {
            getInstance : getInstance
        }
    } )();

    settingsAdmin.getInstance();

} ( jQuery, FP_Settings, window ) );
