## Overview

Starting with Sylius and plugin development I read the guide on https://docs.sylius.com/en/latest/plugins/plugin-development-guide/naming.html about how to renaming the dummy plugin.
So I have to do this over and over again ... and not only me ... everybody who wants to create a custom plugin has to do this boring task.

No way!

<img src="https://arroba-it.de/img/automate.jpeg" />
 
The command does the following things:

* Installs the Sylius Plugin Skeleton to a custom directory
* Adjust the composer.json
* Renaming all the things
* Installing the assets (optional)
* Creates an SQLite database (optional)
* Loads the Fixtures to the database (optional)
* Starts the internal server (optional)

## Installation

```bash
$ composer global require konafets/sylius-installer 
```

## Synopsis

```bash
Description:
  Creates the plugin skeleton

Usage:
  new:plugin [options]

Options:
  -d, --description=DESCRIPTION     The description of your plugin
  -a, --author=AUTHOR               Author name of the plugin
      --dev                         Installs the latest "development" release
  -f, --force                       Forces install even if the directory already exists
  -h, --help                        Display this help message
  -q, --quiet                       Do not output any message
  -V, --version                     Display this application version
      --ansi                        Force ANSI output
      --no-ansi                     Disable ANSI output
  -n, --no-interaction              Do not ask any interactive question
  -pn, --package-name=PACKAGE-NAME  Name of the package
  -v|vv|vvv, --verbose              Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug 
```
## Usage

```bash
$ cd Development/
$ sylius new:plugin 
```


