#Terms
- ROOT_PATH - The path to the directory where the unpacked parser.

----

#Usage
##The first variant
Run in the command line:
1. cd ROOT_PATH
2. php bin/application.php executor:run

In this case, the parser tries to read brand-page-urls from a file located in the following path:
- ROOT_PATH/configs/brand-page-urls.csv

##The second variant
Run in the command line:
1. cd ROOT_PATH
2. php bin/application.php executor:run --file-path-to-brand-page-urls=/home/sasha/Загрузки/url.csv 

Where {--file-path-to-brand-page-urls} - this path to the file with brand-page-urls

The result will be located in the following folder: ROOT_PATH/var/images/

----

##Features
After each run cleared the following directories:
- ROOT_PATH/var/tmp
- ROOT_PATH/var/images

It follows that the figures in the previous one will be lost starting.
