<?php

include(dirname(__FILE__) . '/simplehtmldom_1_5/simple_html_dom.php');

//For page links
 $html = file_get_html('http://www.ncbi.nlm.nih.gov/pubmedhealth/topics/drugs/z/');


		$key = 0;
		$rssdata = array();
				// Find all links
		$key = 0;
		foreach($html->find('div[class=title-list] ul[class=resultList] li') as $element){
			if($key > 99){
				$title = $element->innertext;
				$rssdata[$key]['title'] = preg_replace("/<\\/?a(\\s+.*?>|>)/", "", $title);
				$match = array();
				$url = preg_match('/<a href="(.+)">/', $title, $match);
				$info = parse_url($match[1]);
				$finalurl = $info['path'];
				$rssdata[$key]['link'] = 'http://www.ncbi.nlm.nih.gov'.$finalurl;
			}

			   $key++;
			if($key == 200){ break; }
		}


		$key = 100;
		foreach($rssdata as $data_desc){

			$get_description = file_get_html($data_desc['link']);

			/*foreach($html->find('div[class=title-list] ul[class=resultList] li') as $element){
				$rssdata[$key]['title'] = $element->innertext;
			}*/
			$rssdata[$key]['title'] = $data_desc['title'];
			$rssdata[$key]['link'] = $data_desc['link'];

			foreach($get_description->find('div[class=topic-content]') as $element){
				if($element->innertext != ''){
				$description = $element->innertext;
				$rssdata[$key]['description'] = preg_replace("/<\\/?a(\\s+.*?>|>)/", "", $description);
				}
			}

			$key++;
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
		<?php foreach($rssdata as $rdata){ ?>
		<item>
			<title><?php echo $rdata['title']; ?></title>
			<link><?php echo $rdata['link']; ?></link>
			<image src="<?php echo $rdata['image']; ?>" />
			<guid isPermaLink="false"><?php echo $rdata['link']; ?></guid>
			<content:encoded><![CDATA[ <?php echo html_entity_decode($rdata['description']); ?> ]]></content:encoded>
			<category> A </category>
		</item>
		<?php } ?>
	</channel>
</rss>
<?php exit; ?>