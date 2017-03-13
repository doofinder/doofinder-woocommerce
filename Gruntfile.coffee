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
      build:
        src: ["doofinder-for-woocommerce/doofinder-for-woocommerce.php"]

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
