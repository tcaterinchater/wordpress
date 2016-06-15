<?php
/**
 * Template Name: Feed Colbert Report
 */ 
header("Content-Type: application/rss+xml; charset=UTF-8");
echo '<?xml version="1.0"?>';
?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
	xmlns:georss="http://www.georss.org/georss" xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#" xmlns:media="http://search.yahoo.com/mrss/"
	>
<channel>
 <title>Daily Show Videos</title>
 <description>This is a rss feed with a list of Daily Show Videos</description>
 <link>http://www.thedailyshow.com/videos</link>
 <lastBuildDate><?php echo @date(DATE_RSS) ?></lastBuildDate>
 <pubDate><?php echo @date(DATE_RSS) ?></pubDate>
 <ttl>1800</ttl>
<?php
require 'scraper/scraper-dailyshow.php';
$scraper = new Scraper(0,30);

while($item = $scraper->nextItem()){?>
 <item>
  <title><?php echo $item['title'] ?></title>
  <description><?php echo $item['description'] ?></description>
  <dc:creator>The Colbert Report</dc:creator>
  <tags><?php echo $item['tags'] ?></tags>
  <link><?php echo $item['link'] ?></link>
  <thumbnail><?php echo $item['thumbnail'] ?></thumbnail>
  <guid><?php echo $item['guid'] ?></guid>
  <pubDate><?php echo $item['pubDate'] ?></pubDate>
 </item>
<?}?>

</channel>
</rss>