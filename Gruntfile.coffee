module.exports = (grunt) ->
  require("load-grunt-tasks")(grunt)

  grunt.initConfig
    clean:
      build: ['build/*']

    copy:
      build:
        expand: true
        cwd: "doofinder-for-woocommerce"
        src: ["**/*", "!**/*.scss"]
        dest: "build"

    version:
      php:
        src: ["doofinder-for-woocommerce/doofinder-for-woocommerce.php"]
      txt:
        options:
          prefix: 'Version: '
        src: ["doofinder-for-woocommerce/readme.txt"]

    compress:
      build:
        options:
          archive: "doofinder-for-woocommerce.zip"
        files: [{
          expand: true
          src: ["doofinder-for-woocommerce/**/*"]
          dest: "/"
        }]

  grunt.registerTask "default", ["version", "clean", "copy", "compress"]
