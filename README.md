# plugin-bigcommerce-official

eKomi Plugin for BigCommerce allows you to integrate your BigCommerce shop easily with eKomi system. This allows you to collect verified reviews, display eKomi seal on your website and get your seller ratings on Google. This helps you increase your website's click through rates, conversion rates and also, if you are running Google AdWord Campaigns, this helps in improving your Quality Score and hence your costs per click.

<p>
<strong>eKomi Reviews and Ratings allows you to:</strong>
</p>
<ul>
<li>Collect order and/or product base Reviews</li>
<li>Supports Simple, Configurable, Grouped and Bundle products</li>
<li>Manage Reviews: our team of Customer Feedback Managers, reviews each and every review for any terms which are not allowed and also put all negative reviews in moderation.</li>
<li>Publish reviews on search engines: Google, Bing, Yahoo!</li>
<li>Easy Integration with eKomi.</li>
<li>Get Google Seller Ratings.</li>
<li>Increase Click through Rate by over 17%</li>
<li>Increase conversion Rate</li>
</ul>

<p>eKomi is available in English, French, German, Spanish, Dutch, Italian, Russian and Polish<br />If you have any questions regarding the plugin, please contact your eKomi Account Manager.</p>

<p><b>Please note</b> that you will need an eKomi account to use the plugin. To create an eKomi account, go to 
<a href='http://eKomi.com'>eKomi.com</a>

## Getting Started

1. Clone the project in local. 
2. Create database named "bigcommerce"

After creating the database, import bigcommerce.sql in created database.

Set the database user and password in *.env* file.

### Prerequisites

Composer needs to install to run the setup in local.

```
sudo apt-get update
sudo apt-get install curl php5-cli

curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

### Installing

Follow these steps to install the plugin.

```
git clone https://github.com/ekomi-ltd/plugin-bigcommerce-official.git
```

* Clone the project in local. 
* Create database named "bigcommerce"

After creating the database, import *bigcommerce.sql* in created database.

Set the database user and password in *.env* file.

Update the composer in plugin plugin-bigcommerce-official directory.

```
composer update
```

## Deployment

Steps to deploy this on a live system.
* Upload the plugin Live server
* Create database named "bigcommerce" on live
* Import database bigcommerce.sql
* Run composer update
* Update .env to set plugin url and database credentials

## Built With

* silex framework
* Twig templating engine
* symfony/twig-bridge
* Doctrine
* bigCommerce Api

## Versioning

### v1.0.0 (15-06-2017)

- A complete working plugin

## Authors

* **Khadim raath** - *khadim.nu@gmail.com* - [github profile](https://github.com/kRaath)

See also the list of [contributors](https://github.com/ekomi-ltd/plugin-bigcommerce-official/graphs/contributors) who participated in this project.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details
