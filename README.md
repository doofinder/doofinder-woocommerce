# doofinder-wordpress
Integrate Doofinder in your WordPress site with (almost) no effort.

## Developers

For a local installation of a WordPress environment use `docker-compose up -d`. 
This installation brings: 

- **a mysql container** for the database (on port 3310)
- **a wordpress container** for the website on port 9010

You can now visit `localhost:9010/wp-admin` and login with the user `admin` and password `admin123`.
Wordpress is automatically installed, to configure the ports check the `.env` file, other configurations are defined in the `docker-compose.yml`.

## Before pushing changes to GitHub

NOTE: This part assumes that you have Composer installed in your PC and you have already run `composer install` on the root of this project.

Currently, we have a PHP Code Beautifier and Fixer (known as phpcbf) to force WordPress and WooCommerce standard-compliant code (at least for PHP for now).
So before commiting or pushing any changes in your code, execute the following command from the root folder of this project:

`make cs-fix`

This command will fix automatically all the minor issues regarding to the coding standards.

When all the manual errors are fixed, we will have to run another command just to check if there are some errors that cannot be fixed automatically by the previous command. The command is:

`make cs-check`
