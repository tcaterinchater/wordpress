<?php
require_once dirname(__FILE__) . '/lib/simple_html_dom.php';
require_once dirname(__FILE__) . '/lib/pgbrowser.php';

class Scraper{
  var $browser, $page, $items, $startIndex, $endIndex, $currentIndex;
  function __construct($start_url, $startIndex = 0, $endIndex = 100, $use_cache = false){
    $this->browser = new PGBrowser('simple html dom');
    $this->browser->useCache = $use_cache;
    $this->browser->convertUrls = true;
    $this->page = $this->browser->get($start_url);
    $this->startIndex = $startIndex;
    $this->endIndex = $endIndex;
    $this->currentIndex = 0;
    $this->items = $this->process($this->page);
    while($this->currentIndex < $this->startIndex){$this->nextItem();}
  }

  function nextItem(){
    if($this->currentIndex > $this->endIndex) return null;
    $this->currentIndex += 1;
    $item = array_shift($this->items);
    if($item) return $item;
  }

  function tags($str){
    $retval = array();
    preg_match_all("/\b[\w'-]+\b/u", $str, $m);
    foreach($m[0] as $word){
      if(strlen($word) > 4) $retval[] = $word;
    }
    return implode(', ', $retval);
  }
}
?>