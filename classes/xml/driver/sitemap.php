<?php defined('SYSPATH') or die('No direct script access.');
/**
 *	Document   : sitemap.php
 *	Created on : 1 mai 2009, 13:03:03
 *	@author Cedric de Saint Leger <c.desaintleger@gmail.com>
 *
 *	Description:
 *      Sitemap driver
 * 		http://www.sitemaps.org/protocol.html
 */

class XML_Driver_Sitemap extends XML {

	public $root_node = 'urlset';


	protected static function initialize(XML_Meta $meta)
	{
		$meta	->nodes (
							array(
								"urlset"				=> array("namespace"	=> "http://www.sitemaps.org/schemas/sitemap/0.9"),
								"loc"					=> array("filter"		=> "normalize_uri"),
								"lastmod"				=> array("filter"		=> "normalize_date"),

								// Sitemap extensions
								// Videos
								// http://support.google.com/webmasters/bin/answer.py?hl=fr&answer=80472
								"video:video"			=> array("namespace"	=> "http://www.google.com/schemas/sitemap-video/1.1"),
								"video:thumbnail_loc"	=> array("filter"		=> "normalize_uri"),
								"video:content_loc"		=> array("filter"		=> "normalize_uri"),
								"video:player_loc"		=> array("filter"		=> "normalize_uri"),
								"video:expiration_date"	=> array("filter"		=> "normalize_datetime"),

								// Images
								// http://support.google.com/webmasters/bin/answer.py?hl=fr&answer=178636&topic=20986&ctx=topic
								"image:image"			=> array("namespace"	=> "http://www.google.com/schemas/sitemap-image/1.1"),
								"image:loc"				=> array("filter"		=> "normalize_uri"),

								// News
								// http://support.google.com/webmasters/bin/answer.py?hl=en&answer=74288
								"news:news"				=> array("namespace"	=> "http://www.google.com/schemas/sitemap-news/0.9"),
								"news:publication_date"	=> array("filter"		=> "normalize_date"),

								// Mobile
								// http://support.google.com/webmasters/bin/answer.py?hl=fr&answer=34648
								"mobile:mobile"			=> array("namespace"	=> "http://www.google.com/schemas/sitemap-mobile/1.0"),

								// Code Search
								// http://support.google.com/webmasters/bin/answer.py?hl=fr&answer=75225
								"codesearch:codesearch"	=> array("namespace"	=> "http://www.google.com/codesearch/schemas/sitemap/1.0"),
								)
						);
	}


	public function add_url($url, $options = array(), $extensions = array())
	{
		$url_node = $this->add_node("url");
		$url_node->add_node("loc", $url);
		foreach ($options as $key => $val)
		{
			$url_node->add_node($key, $val);
		}
		foreach ($extensions as $extension => $params)
		{
			$extension = $url_node->add_node("$extension:$extension");
			foreach ($params as $key => $val)
			{
				$extension->add_node($key, $val);
			}
		}
		return $this;
	}


	public function normalize_datetime($value)
	{
		if ( ! is_numeric($value))
		{
			$value = strtotime($value);
		}

		// Convert timestamps to W3C format datetime
		return date(DATE_W3C, $value);
	}


	public function normalize_date($value)
	{
		if ( ! is_numeric($value))
		{
			$value = strtotime($value);
		}

		// Convert timestamps to RFC 3339 formatted dates
		return date("Y-m-d", $value);
	}
}