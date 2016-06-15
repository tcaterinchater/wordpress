<?
require_once dirname(__FILE__) . '/scraper.php';
class BillOreillyScraper extends Scraper{

  function process($page){
    $retval = array();
    foreach($page->search('//td[contains(@class, "videoCenterInnerContainer")]') as $div){
      $item = array();
      if(!$el = $page->at('.//td[@class="vcHeaderText"]', $div)) continue;
      $item['title'] = trim($el->nodeValue);
      $item['description'] = trim($page->at('.//tr[td[@class="vcHeaderText"]]/following-sibling::tr/td', $div)->nodeValue);
      $item['image'] = $item['thumbnail'] = $page->at('.//img', $div)->getAttribute('src');
      $item['tags'] = $this->tags($item['title']);
      $guid =  $item['guid'] = $div->getAttribute('id');
      $item['pubDate'] = null;
      $item['link'] = null;
      $item['embed_code'] = null;
      var_dump($item);
      exit;
      $str = trim($page->at('.//div[@class="cnnLeftPost"]/div[@class="cnnBlogContentDateHead"]', $div)->nodeValue);
      $str .= ' ' . trim($page->at('.//div[@class="cnnLeftPost"]/div[@class="cnnGryTmeStmp"]', $div)->nodeValue);
      $str = preg_replace('/(\d{2}:\d{2} [AP]M) [A-Z]*T\b/', '\1', $str);
      $item['pubDate'] = @date(DATE_RSS, strtotime($str));
      $d = $page->at('.//div[@data-video-url]', $div);
      if(!$d) continue;
      $item['link'] = $d->getAttribute('data-url');

      $item['embed_code'] = "<iframe src='" . $d->getAttribute('data-video-url') . "' height='" . $d->getAttribute('data-video-height') . "' width='" . $d->getAttribute('data-video-width') . "' scrolling='no' border='no' ></iframe>";
      $item['embed_code'] = <<<EOF
<object width="416" height="234" classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" id="ep_1012"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="wmode" value="transparent" /><param name="movie" value="http://i.cdn.turner.com/cnn/.element/apps/cvp/3.0/swf/cnn_embed_2x_container.swf?site=cnn&profile=desktop&context=embed&videoId=$guid&contentId=$guid" /><param name="bgcolor" value="#000000" /><embed src="http://i.cdn.turner.com/cnn/.element/apps/cvp/3.0/swf/cnn_embed_2x_container.swf?site=cnn&profile=desktop&context=embed&videoId=$guid&contentId=$guid" type="application/x-shockwave-flash" bgcolor="#000000" allowfullscreen="true" allowscriptaccess="always" width="416" wmode="transparent" height="234"></embed></object>
EOF;

/*
<td align="center" valign="top" id="-956241600597751277" class="videoCenterInnerContainer" style="background-color: rgb(222, 223, 239);">
                            <table width="100%" cellspacing="0" cellpadding="0" border="0" class="vcSmallDescText">
                              <tbody><tr>
                                  <td class="vcSmallDescText" align="left" valign="top" height="35">TALKING POINTS MEMO</td>
                              </tr>
                              <tr>
                                <td valign="middle" align="center">
                                    <table cellpadding="0" cellspacing="0" border="0" height="100%">
                                        <tbody><tr>
                                            <td style="background-color: #000; height: 110px; width:150px; overflow: hidden; cursor: pointer;" onclick="javascript:loadFoxVideo('-956241600597751277', '556', 'true');">
                                                <a href="#play" onclick="javascript:loadFoxVideo('-956241600597751277', '556', 'true');" class="defaultLinks"><img src="http://images.BillOReilly.com/images/videoscreens/tpmscreen11.06.13.jpg" border="0" width="150" style="display:block;"></a>
                                            </td>
                                        </tr>
                                    </tbody></table>
                                </td>
                              </tr>
                              
                              <tr>
                                <td valign="top" class="vcHeaderText" width="100%" style="padding-top:5px;">Talking Points 11/6</td>
							  </tr>
							  
                              
                              
                              <tr>
                                  <td style="padding-top:5px;">How ObamaCare is impacting American politics
                                </td>
                              </tr>
                              
                            </tbody></table>
                          </td>
*/

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
  new BillOreillyScraper('http://www.billoreilly.com/video', 0, 30, true);
  //new CnnScraper('http://globalpublicsquare.blogs.cnn.com/', 0, 30, true);
  //new CnnScraper('http://piersmorgan.blogs.cnn.com/', 0, 30, true);

/*
    

Amanpour              feed-amanpour.php           
Anderson Cooper 360   feed-anderson-cooper.php    
Crossfire             feed-crossfire.php          
Fareed Zakaria        feed-fareed-zakaria.php     
Piers Morgan          feed-piers-morgan.php       

FOX
bill o'reilly     feed-bill-oreilly.php       
Hannity           feed-hannity.php            http://www.foxnews.com/on-air/hannity/index.html
glenn beck        feed-glenn-beck.php         http://www.video.theblaze.com/media/video.jsp
rush limbaugh     feed-rush-limbaugh.php      http://www.rushlimbaugh.com/videos/



*/

}

?>