module.exports = function ( grunt ) {
    grunt.initConfig( {
        pkg : grunt.file.readJSON( 'package.json' ),
        uglify : {
            js : {
                files : {
                    'build/js/post-admin.min.js' : ['js/post-admin.js'],
                    'build/js/settings-admin.min.js' : ['js/settings-admin.js']
                }
            }
        },
        jshint : {
            options : {
                smarttabs : true
            }
        },
        sass : {
            dist : {
                files : {
                    'build/css/post-admin.css' : 'scss/post-admin.scss'
                }
            }
        },
        cssmin : {
            backend : {
                src : 'build/css/post-admin.css',
                dest : 'build/css/post-admin.min.css'
            }
        },
        watch : {
            files : [
                'js/*',
                'scss/*'
            ],
            tasks : ['uglify', 'sass', 'cssmin:backend']
        }
    } );
    grunt.loadNpmTasks( 'grunt-contrib-uglify' );
    grunt.loadNpmTasks( 'grunt-contrib-jshint' );
    grunt.loadNpmTasks( 'grunt-contrib-sass' );
    grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
    grunt.loadNpmTasks( 'grunt-contrib-watch' );
    grunt.registerTask( 'default', ['uglify:js', 'sass', 'cssmin:backend'] );
};