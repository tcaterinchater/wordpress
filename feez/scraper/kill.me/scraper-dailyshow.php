<?
require 'simple_html_dom.php';
require 'pgbrowser.php';

class Scraper{
  var $browser, $page, $items, $startIndex, $endIndex, $currentIndex;
  function __construct($startIndex = 0, $endIndex = 100){
    $this->browser = new PGBrowser('simple html dom');
    $this->page = $this->browser->get('http://www.thedailyshow.com/videos');
    $this->startIndex = $startIndex;
    $this->endIndex = $endIndex;
    $this->currentIndex = 0;
    $this->items = $this->process($this->page);
    while($this->currentIndex < $this->startIndex){$this->nextItem();}
  }

  function process($page){
    $retval = array();

    foreach($page->search('div.entry') as $div){
      $item = array();
      $item['title'] = $page->at('span.title a', $div)->text();
      $item['tags'] = implode(array_map(function($x){return $x->text();}, $page->search('span.tags a', $div)), ', ');
      $item['description'] = $page->at('span.description', $div)->text();
      $item['link'] = $page->at('a.imageHolder', $div)->href;
      
      $thumb = $page->at('a.imageHolder img', $div)->src;
      $size = array("133", "71");
	  $newsize   = array("720", "480");
      $item['thumbnail'] = str_replace($size, $newsize, $thumb);

      if(preg_match('/\d+/', $div->id, $m)) $item['guid'] = $m[0];
      $str = $page->at('div.info_holder div.section', $div)->text();
      if(preg_match('/(\d+)\/(\d+)\/(\d+)/', $str, $m)){
        $item['pubDate'] = @date(DATE_RSS, mktime(0, 0, 0, $m[1], $m[2], $m[3]));
      }
      $retval[] = $item;
    }

    return $retval;
  }

  function nextItem(){
    if($this->currentIndex > $this->endIndex) return null;
    $this->currentIndex += 1;
    $item = array_shift($this->items);
    if($item) return $item;

    # do paging
    $nextLink = $this->page->at('a.search-next');

    # stop if there's no more
    if(preg_match('/disabled/', $nextLink->class)) return null;

    if(!preg_match("/'(.*)'/", $nextLink->onclick, $m)) die('bad onclick!');
    $nextUrl = $m[1];

    $this->page = $this->browser->get($nextUrl);
    $this->items = $this->process($this->page);
    return $this->nextItem();
  }
}
?>