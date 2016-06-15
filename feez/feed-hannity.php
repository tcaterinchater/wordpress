<?php
require 'scraper/fox_scraper.php';

$author = 'Hannity';

$channel = array(
  'title' => $author . ' Videos',
  'description' => 'This is a rss feed with a list of ' . $author . ' Videos',
  'link' => 'http://video.foxnews.com/v/feed/playlist/86925.rss'
);

$scraper = new FoxScraper($channel['link'], 0,30);

require_once dirname(__FILE__) . '/scraper/template.php';
?>