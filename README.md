#Terms
- ROOT_PATH - The path to the directory where the unpacked parser.

----

#Usage
##The first variant
Run in the command line:
1. cd ROOT_PATH
2. php bin/application.php autoplicity:executor:run

In this case, the parser tries to read brand-page-urls from a file located in the following path:
- ROOT_PATH/configs/autoplicity/brand-page-urls.csv

The result will be located in the following folder: ROOT_PATH/var/images/autoplicity/

----

##Features
To work with the proxy, you must add proxy to the file: ROOT_PATH/configs/proxies.yml in a specified format.

After each run cleared the following directories:
- ROOT_PATH/var/tmp/autoplicity
- ROOT_PATH/var/images/autoplicity

It follows that the figures in the previous one will be lost starting.
