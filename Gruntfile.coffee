module.exports = (grunt) ->
  require("load-grunt-tasks")(grunt)

  grunt.initConfig
    clean:
      build: ['build/trunk/*', 'build/assets/*']

    copy:
      source:
        expand: true
        cwd: "doofinder"
        src: ["**/*", "!**/*.scss"]
        dest: "build/trunk"
      assets:
        expand: true
        cwd: "assets"
        src: ["**/*"]
        dest: "build/assets"

    version:
      code:
        src: ["doofinder/doofinder.php"]
      text:
        options:
          prefix: 'Version: '
        src: [
          "doofinder/doofinder.php",
          "doofinder/readme.txt"
        ]

    compress:
      source:
        options:
          archive: "doofinder.zip"
        files: [{
          expand: true
          src: ["doofinder/**/*"]
          dest: "/"
        }]

  grunt.registerTask "build", ["clean", "copy", "compress"]
  grunt.registerTask "release", ["version", "build"]
  grunt.registerTask "default", ["build"]
