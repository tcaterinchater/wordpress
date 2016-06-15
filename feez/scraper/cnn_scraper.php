<?
require_once dirname(__FILE__) . '/scraper.php';
class CnnScraper extends Scraper{

  function process($page){
    $retval = array();
    foreach($page->search('//div[contains(@class, "cnnPostWrap")]') as $div){
      $item = array();
      if(!$el = $page->at('.//h2/a', $div)) continue;
      $item['title'] = trim($el->nodeValue);
      $str = trim($page->at('.//div[@class="cnnLeftPost"]/div[@class="cnnBlogContentDateHead"]', $div)->nodeValue);
      $str .= ' ' . trim($page->at('.//div[@class="cnnLeftPost"]/div[@class="cnnGryTmeStmp"]', $div)->nodeValue);
      $str = preg_replace('/(\d{2}:\d{2} [AP]M) [A-Z]*T\b/', '\1', $str);
      $item['pubDate'] = @date(DATE_RSS, strtotime($str));
      $d = $page->at('.//div[@data-video-url]', $div);
      if(!$d) continue;
      $item['image'] = $d->getAttribute('data-image-url');
      $item['thumbnail'] = $item['image'];
      $item['link'] = $d->getAttribute('data-url');
      $item['tags'] = $this->tags($item['title']);
      $guid =  $item['guid'] = $d->getAttribute('data-video-url');

      $item['embed_code'] = "<iframe src='" . $d->getAttribute('data-video-url') . "' height='" . $d->getAttribute('data-video-height') . "' width='" . $d->getAttribute('data-video-width') . "' scrolling='no' border='no' ></iframe>";
      $item['embed_code'] = <<<EOF
<object width="416" height="234" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" id="ep_1012"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="wmode" value="transparent" /><param name="movie" value="http://i.cdn.turner.com/cnn/.element/apps/cvp/3.0/swf/cnn_embed_2x_container.swf?site=cnn&profile=desktop&context=embed&videoId=$guid&contentId=$guid" /><param name="bgcolor" value="#000000" /><embed src="http://i.cdn.turner.com/cnn/.element/apps/cvp/3.0/swf/cnn_embed_2x_container.swf?site=cnn&profile=desktop&context=embed&videoId=$guid&contentId=$guid" type="application/x-shockwave-flash" bgcolor="#000000" allowfullscreen="true" allowscriptaccess="always" width="416" wmode="transparent" height="234"></embed></object>
EOF;
      
      
      $ps = $page->search('.//div[@class="cnnBlogContentPost"]/p', $div);
      $arr = array();
      foreach($ps as $p){
        if(preg_match('/^By |FULL POST/', $p->nodeValue)) continue;
        $arr[] = $p->nodeValue;
      }
      $item['description'] = implode(' ', $arr);

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
  new CnnScraper('http://crossfire.blogs.cnn.com/', 0, 30, true);
  //new CnnScraper('http://globalpublicsquare.blogs.cnn.com/', 0, 30, true);
  //new CnnScraper('http://piersmorgan.blogs.cnn.com/', 0, 30, true);

/*
    

Amanpour              feed-amanpour.php           
Anderson Cooper 360   feed-anderson-cooper.php    
Crossfire             feed-crossfire.php          
Fareed Zakaria        feed-fareed-zakaria.php     
Piers Morgan          feed-piers-morgan.php       

FOX
bill o'reilly     feed-bill-oreilly.php       http://www.billoreilly.com/video
Hannity           feed-hannity.php            http://www.foxnews.com/on-air/hannity/index.html
glenn beck        feed-glenn-beck.php         http://www.video.theblaze.com/media/video.jsp
rush limbaugh     feed-rush-limbaugh.php      http://www.rushlimbaugh.com/videos/



*/

}

?>