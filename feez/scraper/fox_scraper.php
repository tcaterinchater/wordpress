<?
require_once dirname(__FILE__) . '/scraper.php';
class FoxScraper extends Scraper{

  function process($page){
    $retval = array();
    foreach($page->search('//item') as $div){
      $item = array();
      $item['description'] = $page->at('.//title', $div)->nodeValue;
      $item['link'] = $page->at('.//link', $div)->nodeValue;
      $item['pubDate'] = $page->at('.//pubDate', $div)->nodeValue;
      $d = 

$dom = new DOMDocument;
@$dom->loadHTML($page->at('.//description', $div)->nodeValue);
$xpath = new DOMXPath($dom);



      $item['title'] = $xpath->query("//p")->item(0)->nodeValue;
      $item['image'] = $item['thumbnail'] = $xpath->query("//img")->item(0)->getAttribute('src');
      $item['tags'] = $this->tags($item['title']);



      preg_match('/v\/(\d+)/', $item['link'], $m);
      $item['guid'] = $m[1];
      $movie = "http://video.foxnews.com/v/embed.js?id=" . $m[1] . "&w=466&h=263";

      $item['embed_code'] = '<script type="text/javascript" src="' . $movie . '"></script>';

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
    if(!$nextLink = $this->page->at('//a[contains(text(),"older posts")]')){
      return null;
    }

    $this->page = $this->browser->get($nextLink->getAttribute('href'));
    $this->items = $this->process($this->page);
    return $this->nextItem();
  }
}

if(realpath($argv[0]) == __FILE__){
  //new CnnScraper('http://amanpour.blogs.cnn.com/', 0, 30, true);
  //new CnnScraper('http://ac360.blogs.cnn.com/', 0, 30, true);
  new FoxScraper('http://video.foxnews.com/v/feed/playlist/86923.rss', 0, 30, true);
  //new CnnScraper('http://globalpublicsquare.blogs.cnn.com/', 0, 30, true);
  //new CnnScraper('http://piersmorgan.blogs.cnn.com/', 0, 30, true);

/*
    

Amanpour              feed-amanpour.php           
Anderson Cooper 360   feed-anderson-cooper.php    
Crossfire             feed-crossfire.php          
Fareed Zakaria        feed-fareed-zakaria.php     
Piers Morgan          feed-piers-morgan.php       

FOX
bill o'reilly     feed-bill-oreilly.php       http://video.foxnews.com/v/feed/playlist/86924.rss
Hannity           feed-hannity.php            http://video.foxnews.com/v/feed/playlist/86925.rss
glenn beck        feed-glenn-beck.php         http://video.foxnews.com/v/feed/playlist/86917.rss


rush limbaugh     feed-rush-limbaugh.php      http://www.rushlimbaugh.com/videos/


*/

}

?>