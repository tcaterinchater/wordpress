<?php
/**
 * Template Name: Feed Powerful Politics
 */ 
set_time_limit(0);
$xml=file_get_contents("http://video.foxnews.com/v/feed/playlist/158694.rss");

$doc=new DomDocument();
$doc->loadXml($xml);

$xpath=new DomXpath($doc);

foreach($node=$xpath->query("//item") as $item)
{
	
	if($titlenode=$xpath->query("./title",$item)->item(0))
	{
		echo $title=$titlenode->nodeValues;
	}
	if($link=$xpath->query("./link",$item)->item(0))
	{
		
		$target_url = $link->nodeValue;
		//$userAgent = 'Googlebot/2.1 (http://www.googlebot.com/bot.html)';
		
		// make the cURL request to $target_url
		if(preg_match('/v\/(.*?)\//',$target_url,$matches))
			$id=$matches[1];
			
			$video_id = $doc->createElement("video_id");
			$video_id->appendChild($doc->createTextNode($id));
			$item->appendChild($video_id);
		 				
		
		
	}	
	if($str_tag=$xpath->query("./title",$item)->item(0))	
	{
		$n_words = preg_match_all('/([a-zA-Z]){5,}/', $str_tag->nodeValue, $match_arr);
		$word_arr = $match_arr[0];
		
		$tag_words=implode(', ',$word_arr);
		$tag = $doc->createElement("tag");
		$tag->appendChild($doc->createTextNode($tag_words));
		$item->appendChild($tag);
	}
}	
	echo $doc->saveXml();
?>