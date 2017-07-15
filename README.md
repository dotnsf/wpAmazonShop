# wpAmazonShop

## Overview

This is renewed application from [phpAmazonShop](https://github.com/dotnsf/phpAmazonShop], which crawls items information via Amazon API, and create records in WordPress database.

## Pre-requisite

- WordPress (PHP+MySQL+Application set)

- Amazon Affiliate account

## Install

- Initialize your WordPress.

    - Remove default post(Helloworld).

    - Set permanent link as 'Basic'.

- git clone or download from https://github.com/dotnsf/wpAmazonShop

- Edit credentials.php with your MySQL & Amazon Affiliate account information.

    - You can leave 'AWS_ASSOC_TAG' as blank if you don't want to use affiliate' blank if you don't want to enable your affiliate.

- Edit $nodes array in main.php as your favorite items category.

- Run main.php ( $ php -f main.php ).

    - When finished, you will see your WordPress contains items information with your choice in $nodes.

- You can customize top page as your favorites.


## License

This code is licensed under MIT.

## Copyright

2017 K.Kimura @ Juge.Me all rights reserved.



