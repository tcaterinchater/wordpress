<?php
require 'scraper/cnn_scraper.php';

$author = 'Crossfire';

$channel = array(
  'title' => $author . ' Videos',
  'description' => 'This is a rss feed with a list of ' . $author . ' Videos',
  'link' => 'http://crossfire.blogs.cnn.com/'
);

$scraper = new CnnScraper($channel['link'], 0,30);

require_once dirname(__FILE__) . '/scraper/template.php';
?>