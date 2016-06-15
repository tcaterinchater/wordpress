<?php

include(dirname(__FILE__) . '/simplehtmldom_1_5/simple_html_dom.php');

//For page links
 $html = file_get_html('http://www.ncbi.nlm.nih.gov/pubmedhealth/topics/drugs/a/');



		$rssdata = array();
				// Find all links
		$keycount = 0;
		foreach($html->find('div[class=title-list] ul[class=resultList] li a') as $element){
				$finalurl = $element->href;
			   $rssdata[$keycount]['link'] = 'http://www.ncbi.nlm.nih.gov'.$finalurl;
			   $rssdata[$keycount]['count'] = $keycount;
			   $keycount++;
		}
		$key = 0;
		foreach($rssdata as $data_desc){
			if(in_array($data_desc['count'],range(601,681))) {
				$get_description = file_get_html($data_desc['link']);

				foreach($get_description->find('h1 span[itemprop=name]') as $element){
					$rssdata[$key]['title'] = $element->innertext;
				}

				foreach($get_description->find('div[itemprop=description]') as $element){
					$rssdata[$key]['description'] = $element->innertext;
				}

				foreach($get_description->find('div[class=topic-content]') as $element){
					if($element->innertext != ''){
					$rssdata[$key]['description'] = $element->innertext;
					}
				}

				$key++;
			}
		}

/*echo "<pre>";
print_r($rssdata);
echo "</pre>";*/

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
		<?php foreach($rssdata as $rdata){
			if(in_array($rdata['count'],range(0,80))) { ?>
		<item>
			<title><?php echo $rdata['title']; ?></title>
			<link><?php echo $rdata['link']; ?></link>
			<image src="<?php echo $rdata['image']; ?>" />
			<guid isPermaLink="false"><?php echo $rdata['link']; ?></guid>
			<content:encoded><![CDATA[ <?php echo html_entity_decode($rdata['description']); ?> ]]></content:encoded>
		</item>
		<?php } } ?>
	</channel>
</rss>
<?php exit; ?>