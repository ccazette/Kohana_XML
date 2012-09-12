<?php defined('SYSPATH') or die('No direct script access.');
/**
 *	Document   : atom.php
 *	Created on : 1 mai 2009, 13:03:03
 *	@author Cedric de Saint Leger <c.desaintleger@gmail.com>
 *
 *	Description:
 *      Atom driver
 */

class XML_Driver_Atom extends XML {

	public $root_node = 'feed';


	protected static function initialize(XML_Meta $meta)
	{
		$meta	->content_type("application/atom+xml")
				->nodes (
							array(
								"feed"				=> array("namespace"	=> "http://www.w3.org/2005/Atom"),
							//	"entry"				=> array("namespace"	=> "http://www.w3.org/2005/Atom"),
								"href"				=> array("filter"		=> "normalize_uri"),
								"link"				=> array("filter"		=> "normalize_uri"),
								"logo"				=> array("filter"		=> "normalize_uri"),
								"icon"				=> array("filter"		=> "normalize_uri"),
								"id"				=> array("filter"		=> "normalize_uri"),
								"updated"			=> array("filter"		=> "normalize_datetime"),
								"published"			=> array("filter"		=> "normalize_datetime"),
								"startDate"			=> array("filter" 		=> "normalize_date"),
								'endDate'			=> array("filter" 		=> "normalize_date"),
								"summary"			=> array("filter"		=> "normalize_text"),
								"subtitle"			=> array("filter"		=> "normalize_text"),
								"title"				=> array("filter"		=> "normalize_text"),
								"content"			=> array("filter"		=> "normalize_text")
								)
						);
	}


	public function add_person($type, $name, $email = NULL, $uri = NULL)
	{
		$author = $this->add_node($type);
		$author->add_node("name", $name);
		if ($email)
		{
			$author->add_node("email", $email);
		}
		if ($uri)
		{
			$author->add_node("uri", $uri);
		}
		return $this;
	}


	public function add_content(XML $xml_document)
	{
		$this->add_node("content", NULL, array("type" => $xml_document->meta()->content_type()))->import($xml_document);
		return $this;
	}


	public function normalize_text($value, $node)
	{
		if (strpos($value, "<") >= 0 AND strpos($value, ">") > 0)
		{
			// Assume type = html
			$node->setAttribute("type", "html");
		}
		else
		{
			$node->setAttribute("type", "text");
		}
		return $value;
	}


	public function normalize_datetime($value)
	{
		if ( ! is_numeric($value))
		{
			$value = strtotime($value);
		}

		// Convert timestamps to RFC 3339 formatted datetime
		return date(DATE_RFC3339, $value);
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