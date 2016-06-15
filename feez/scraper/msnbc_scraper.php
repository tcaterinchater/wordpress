<?php
require_once dirname(__FILE__) . '/scraper.php';
class MsnbcScraper extends Scraper{

  function process($page){
    $source = $page->at('//div[@id="TPVideoPlaylistTaxonomyContainer"]')->getAttribute('source');
    preg_match('|/p/(\w+)/FeaturePlayer|', $page->body, $m);
    $player = $m[1];

    // tp:playerUrl="http://player.theplatform.com/p/2E2eJC/FeaturePlayer/select/{releasePid}"
    //
    $url = "http://feed.theplatform.com/f/" . $player . "/" . $source . "?form=json&range=-40&context=" . $source;

    $page = $this->browser->get($url);
    $data = json_decode($page->body, true);
    $retval = array();
    foreach($data['entries'] as $entry){
      $item['title'] = $entry['title'];
      $item['description'] = $entry['description'];
      $item['guid'] = $entry['guid'];
      $item['pubDate'] = @date(DATE_RSS, $entry['pubDate'] / 1000);
      $item['tags'] = $this->tags($item['title']);
      $item['link'] = $entry['nnd$canonicalUrl']['href'];
      $item['thumbnail'] = $entry['plmedia$defaultThumbnailUrl'];
      $item['image'] = $entry['plmedia$defaultThumbnailUrl'];
      //$item['embed_code'] = "<iframe src='http://player.theplatform.com/p/" . $player . "/EmbeddedOffSite?guid=" . $item['guid'] . "' height='500' width='635' scrolling='no' border='no' ></iframe>";
      $retval[] = $item;
    }
    return $retval;
  }

}

if(realpath($argv[0]) == __FILE__){
  //new MsnbcScraper('http://www.msnbc.com/now-with-alex-wagner', 0, 10, true);
  //new MsnbcScraper('http://www.msnbc.com/politicsnation', 0, 10, true);
  //new MsnbcScraper('http://www.msnbc.com/now-with-alex-wagner', 0, 10, true);
  //new MsnbcScraper('http://www.msnbc.com/melissa-harris-perry', 0, 10, true);
  //new MsnbcScraper('http://www.msnbc.com/the-last-word', 0, 10, true);
  //new MsnbcScraper('http://www.msnbc.com/hardball', 0, 10, true);
  //new MsnbcScraper('http://www.msnbc.com/martin-bashir', 0, 10, true);
  //new MsnbcScraper('http://www.msnbc.com/the-ed-show', 0, 10, true);
  //new MsnbcScraper('http://www.msnbc.com/all-in', 0, 10, true);
  //new MsnbcScraper('http://www.msnbc.com/the-cycle', 0, 10, true);
  //new MsnbcScraper('http://www.msnbc.com/news-nation', 0, 10, true);

/*
Al Sharpton    feed-politics-nation.php
Alex Wagner    feed-alex-wagner.php
Melissa Harris feed-melisa-harris.php
the last word  feed-the-last-word.php
hardball       feed-hardball.php
martin bashir  feed-martin-bashir.php
the ed show    feed-the-ed-show.php
all in         feed-all-in.php
The Cycle      feed-the-cycle.php
NewsNation     feed-newsnation.php
*/




}

?>