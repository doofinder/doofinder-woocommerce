# doofinder-wordpress
Integrate Doofinder in your WordPress site with (almost) no effort.

## Developers

For a local installation of a WordPress environment use `docker-compose up -d`. 
This installation brings: 

- **a mysql container** for the database (on port 3310)
- **a wordpress container** for the website on port 9010

You can now visit `localhost:9010/wp-admin` and login with the user `admin` and password `admin123`.
Wordpress is automatically installed, to configure the ports check the `.env` file, other configurations are defined in the `docker-compose.yml`.
