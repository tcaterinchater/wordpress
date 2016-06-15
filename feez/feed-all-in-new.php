<?php

include(dirname(__FILE__) . '/simplehtmldom_1_5/simple_html_dom.php');

//For page links
 $html = file_get_html('http://www.npr.org/rss/rss.php?id=103537970');


       $key = 0;

		$rssdata = array();
				// Find all images

		// Find all titles
		$key = 0;
		foreach($html->find('item title') as $element){
			   $rssdata[$key]['title'] = $element->innertext;
			   $key++;
		}

		// Find all description
		$key = 0;
		foreach($html->find('item description') as $element){
			   $rssdata[$key]['description'] = $element->innertext;
			   $key++;
		}

		// Find all content
		$key = 0;
		foreach($html->find('item content:encoded') as $element){
			   $rssdata[$key]['content'] = $element->innertext;
			   $key++;
		}

		// Find all pubdate
		$key = 0;
		foreach($html->find('item pubDate') as $element){
			   $rssdata[$key]['pubDate'] = $element->innertext;
			   $key++;
		}

		// Find all link
		$key = 0;
		foreach($html->find('item link') as $element){
			   $rssdata[$key]['link'] = $element->innertext;
			   $key++;
		}

		// Find all guid
		$key = 0;
		foreach($html->find('item guid') as $element){
			   $rssdata[$key]['guid'] = $element->innertext;
			   $key++;
		}


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
	>

	<channel>
		<title>Explaining Medicine</title>
		<atom:link rel="self" type="application/rss+xml" />
		<link>http://explainingmedicine.com</link>
		<description>Explaining Medicine</description>
		<language>en-US</language>
		<?php foreach($rssdata as $rdata){ ?>
		<item>
			<title><?php echo $rdata['title']; ?></title>
			<link><?php echo $rdata['guid']; ?></link>
			<pubDate><?php echo $rdata['pubDate']; ?></pubDate>
			<image src="<?php echo $rdata['image']; ?>" />
			<dc:creator><?php echo $rdata['pubDate']; ?></dc:creator>
			<guid isPermaLink="false"><?php echo $rdata['guid']; ?></guid>
			<content:encoded><?php echo html_entity_decode($rdata['content']); ?></content:encoded>
		</item>
		<?php } ?>
	</channel>
</rss>