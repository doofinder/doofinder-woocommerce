# doofinder-woocommerce

Integrate Doofinder in your WooCommerce site with (almost) no effort.

To learn more check the [plugin page](https://wordpress.org/plugins/doofinder-for-woocommerce/) in the WordPress Plugins Repository.

## Developers

### Docker

```
$ docker-compose up
```

#### Import test data

In the WooCommerce importer choose _Show advanced options_ and use this path:

```
# previously: wp-content/plugins/woocommerce/dummy-data/dummy-data.csv
wp-content/plugins/woocommerce/sample-data/sample_products.csv
```

Then click _Run the importer_.

### Update library

The [Doofinder library](https://github.com/doofinder/php-doofinder) has some dependencies.

To install them and perform some cleanup run:

```
$ ./update_lib.sh
```

That will:

- Remove `doofinder-for-woocommerce/lib/vendor`
- Reinstall dependencies
- Remove some useless files
- Prefix libraries so they are all under the `Doofinder\` namespace to prevent conflicts with other plugins

### Release

```
$ grunt       # Changes version number
$ grunt build # Builds the release
```
