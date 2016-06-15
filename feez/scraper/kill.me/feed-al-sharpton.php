<?php
/**
 * Template Name: Feed Scraper
 * The template for displaying featured posts on home page
 *
 * @package deTube
 * @subpackage Template
 * @since deTube 1.0
 */ 
echo '<?xml version="1.0" encoding="UTF-8" ?>' ?>
<rss version="2.0">
<channel>
 <title>Daily Show Videos</title>
 <description>This is a rss feed with a list of Daily Show Videos</description>
 <link>http://www.thedailyshow.com/videos</link>
 <lastBuildDate><?php echo @date(DATE_RSS) ?></lastBuildDate>
 <pubDate><?php echo @date(DATE_RSS) ?></pubDate>
 <ttl>1800</ttl>

<?php
require 'scraper/scraper.php';
$scraper = new Scraper(0,10);

while($item = $scraper->nextItem()){?>
 <item>
  <title><?php echo $item['title'] ?></title>
  <description><?php echo $item['description'] ?></description>
  <dc:creator>The Daily Show</dc:creator>
  <tags><?php echo $item['tags'] ?></tags>
  <link><?php echo $item['link'] ?></link>
  <thumbnail><?php echo $item['thumbnail'] ?></thumbnail>
  <guid><?php echo $item['guid'] ?></guid>
  <pubDate><?php echo $item['pubDate'] ?></pubDate>
 </item>
<?}?>

</channel>
</rss>