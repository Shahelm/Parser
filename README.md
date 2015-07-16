###Terms
- ROOT_PATH - The path to the directory where the unpacked parser.

###Usage
Run in the command line:

####Autoplicity
1. cd ROOT_PATH
2. php bin/application.php autoplicity:executor:run

In this case, the parser tries to read brand-page-urls from a file located in the following path:
- ROOT_PATH/configs/autoplicity/brand-page-urls.csv

The result will be located in the following folder: ROOT_PATH/var/autoplicity/images/

####Amazon
1. cd ROOT_PATH
2. php bin/application.php amazon:executor:run

In this case, the parser tries to read brand-page-urls from a file located in the following path:

ROOT_PATH/configs/amazon/brand-page-urls.csv

Format for amazon/brand-page-urls.csv: {brand-page-url},project-name

The result will be located in the following folder:

1. Images: ROOT_PATH/var/amazon/images/{projectName}/*
2. Product info: ROOT_PATH/var/amazon/tmp/{projectName}/product-info/product-info.csv
3. Compatibility charts: ROOT_PATH/var/amazon/compatibility-charts/{projectName}/compatibility-charts.csv

*product-info.csv*: asin, productName, brand, manufacturer part number, product description, features

*compatibility-charts.csv*: model, year, trim, engine, notes, brand, manufacturerPartNumber

###Features
To work with the proxy, you must add proxy to the file: ROOT_PATH/configs/proxies.yml in a specified format.

Autoplicity:
After each run cleared the following directories:
- ROOT_PATH/var/tmp/autoplicity
- ROOT_PATH/var/images/autoplicity

Amazon:
After each run cleared the following directories:
- ROOT_PATH/var/amazon

It follows that the figures in the previous one will be lost starting.
