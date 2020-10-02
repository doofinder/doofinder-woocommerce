module.exports = (grunt) ->
  require("load-grunt-tasks")(grunt)

  grunt.initConfig
    clean:
      build: ['build/trunk/*', 'build/assets/*']

    copy:
      build:
        expand: true
        cwd: "doofinder-for-woocommerce"
        src: ["**/*", "!**/*.scss"]
        dest: "build/trunk"
      assets:
        expand: true
        cwd: "assets"
        src: ["**/*"]
        dest: "build/assets"

    version:
      code:
        src: ["doofinder-for-woocommerce/doofinder-for-woocommerce.php"]
      text:
        options:
          prefix: 'Version: '
        src: [
          "doofinder-for-woocommerce/doofinder-for-woocommerce.php",
          "doofinder-for-woocommerce/readme.txt"
        ]

    compress:
      build:
        options:
          archive: "doofinder-for-woocommerce.zip"
        files: [{
          expand: true
          src: ["doofinder-for-woocommerce/**/*"]
          dest: "/"
        }]

  grunt.registerTask "build", ["clean", "copy", "compress"]
  grunt.registerTask "release", ["version", "build"]
  grunt.registerTask "default", ["build"]
