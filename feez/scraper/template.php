<?php
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
 <title><?php echo $channel['title'] ?></title>
 <description><?php echo $channel['description'] ?></description>
 <link><?php echo $channel['link'] ?></link>
 <lastBuildDate><?php echo @date(DATE_RSS) ?></lastBuildDate>
 <pubDate><?php echo @date(DATE_RSS) ?></pubDate>
 <ttl>1800</ttl>
<?php
while($item = $scraper->nextItem()){?>
 <item>
  <?php foreach($item as $key => $value){ ?>
    <<?php echo $key ?>><?php echo $value ?></<?php echo $key ?>>
  <?php } ?>
    <author><?php echo $author ?></author>
 </item>
  <?}?>

</channel>
</rss>