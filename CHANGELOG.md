## Change Log

### v0.1 (2015/06/29)
- Add Autoplicity parser

### v0.2 (2015/07/16)
- Add Amazon parser
- Change the entire structure Projects for the convenience of adding new parsers
- Replace GuzzleHttp\Client on Guzzle\Http\Client for work with Cookies
- Fixed a bug with proxy functionality

### v0.201 (2015/07/17)
fixed bug with deleting folders:
- images
- compatibility-charts
- log
- tmp
in src/ConsoleCommands/Amazon/Executor.php

### v0.202 (2015/07/18)
- fixed bug with deleting directories log in ConsoleCommands/Amazon/Executor.php

### v0.203 (2015/07/18)
- fixed a bug with the lack of field make in var/amazon/compatibility-charts/{projectName}/compatibility-charts.csv
