# Digital License Manager WordPress Updater

Updater package for WordPress that utilizes the Digital License Manager PRO [REST API](https://docs.codeverve.com/digital-license-manager/rest-api/) for update checks and update downloads. It can be integrated in every plugin that you want to provide updates through [Digital License Manager PRO](https://codeverve.com/product/digital-license-manager-pro/).

## Requirements

1. WordPress 4.0+
2. Digital License Manager PRO (on your plugin update server site)

## Configuration

1. Login to your Digital License Manager store.
2. Go to "License Manager" > "Software" > "Add New" and create software item.
3. Obtain the software ID and then proceed with the next step **Installation**.

## Installation

The PHP package can be imported either with Composer or manually with including the `autoload.php` file:

```shell
composer install ideologix/dlm-wp-updater
```

or manually, first download the package and save it to your plugin, then include it as follows:

```php
require_once 'path/to/dlm-wordpress-updater/autoload.php';
```

## Integration

The following example explains how to use the library within your PRO/Premium plugin.

```php
use \IdeoLogix\DigitalLicenseManagerUpdaterWP\Main;

try {
    $instance = new Main( array(
        'id'              => 'The software ID',
        'name'            => 'The software Name',
        'file'            => '/path/to/wp-content/plugins/your-plugin/your-plugin.php', // Tip: use __FILE__ to define it in your-plugin.php
        'basename'        => 'your-plugin/your-plugin.php', // Tip: use plugin_basename( __FILE__ ) in your-plugin.php
        'version'         => YOUR_PLUGIN_VERSION, // Tip: Define this in your-plugin.php file and increment with every release.
        'url_settings'    => 'https://url-to-your-plugin/settings-page',
        'url_purchase'    => 'https://url-to-your-website/purchase-page',
        'consumer_key'    => 'ck_XXXXXXXXXXXXXXXXX',
        'consumer_secret' => 'cs_XXXXXXXXXXXXXXXXX',
        'api_url'         => 'https://yourwoocommercesite.com/wp-json/dlm/v1/',
        'prefix'          => 'dlm',
    ) );
} catch (\Exception $e) {
    error_log('Error: ' . $e->getMessage());
}

// Important: To display the activation form in your settings page (url_settings), use the renderActivationForm() method like bellow.
// Note: If you want to override the activation form, you can extend the Activator class with your own class and override the renderActivationForm() method.
$instance->getActivator()->renderActivationForm();
```
