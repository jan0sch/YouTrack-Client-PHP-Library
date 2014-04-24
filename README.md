## Attention!

Because I was lucky enough to be able to dump php completely this repository is no longer under development!
If you want to take over as maintainer, just drop me a line. Meanwhile the [fork from nepda](https://github.com/nepda/youtrack) has some more functionality.

# YouTrack Client PHP Library

[![Build Status](https://travis-ci.org/jan0sch/YouTrack-Client-PHP-Library.png?branch=master)](https://travis-ci.org/jan0sch/YouTrack-Client-PHP-Library)

The bugtracker [YouTrack](http://www.jetbrains.com/youtrack/) provides a [REST-API](http://confluence.jetbrains.net/display/YTD3/YouTrack+REST+API+Reference). Because a lot of web applications are written in [PHP](http://php.net) I decided to write a client library for it. To make it easier for developers to write connectors to YouTrack.

Basically this is a port of the offical python api from Jetbrains.
The initial development was sponsored by [Telematika GmbH](http://www.telematika.de).

The source of this library is released under the BSD license (see LICENSE for details).

## Requirements

* PHP 5.3.x (Any version above 5 might work but I can't guarantee that.)
* curl
* simplexml
* YouTrack 3.0 with REST-API enabled

## Usage

    <?php
    require_once("youtrackclient.php");
    $youtrack = new \YouTrack\Connection("http://example.com", "login", "password");
    $issue = $youtrack->get_issue("TEST-1");
    ...

## Tests

The unit tests are incomplete but you can run them using `phpunit` like this:

    % phpunit test

