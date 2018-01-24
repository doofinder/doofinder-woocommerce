# doofinder-woocommerce

Integrate Doofinder in your WooCommerce site with (almost) no effort.

To learn more check the [plugin page](https://wordpress.org/plugins-wp/doofinder-for-woocommerce/) in the WordPress Plugins Repository.

## Developers

### Docker

```
$ docker-compose up
```

#### Import test data

In the WooCommerce importer choose _Show advanced options_ and use this path:

```
wp-content/plugins/woocommerce/dummy-data/dummy-data.csv
```

Then click _Run the importer_.

### Release

```
$ grunt       # Changes version number
$ grunt build # Builds the release
```
