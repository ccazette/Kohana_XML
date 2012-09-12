<?php defined('SYSPATH') or die('No direct script access.');
/**
 *	Document   : xrds.php
 *	Created on : 1 mai 2009, 13:03:03
 *	@author Cedric de Saint Leger <c.desaintleger@gmail.com>
 *
 *	Description:
 *      XRDS driver. For Service Discovery.
 */

class XML_Driver_XRDS extends XML {

	public $root_node = 'xrds:XRDS';

	protected static function initialize(XML_Meta $meta)
	{
		$meta	->content_type("application/xrds+xml")
				->nodes (
							array(
								"xrds:XRDS"			=> array("namespace"	=> 'xri://$xrds', "attributes"	=> array("xmlns" => 'xri://$xrd*($v*2.0)')),
								"LocalID"			=> array("filter"		=> "normalize_uri"),
								"openid:Delegate"	=> array("filter"		=> "normalize_uri", "namespace" => "http://openid.net/xmlns/1.0"),
								"URI"				=> array("filter"		=> "normalize_uri"),
								)
						);
	}


	public function add_service($type, $uri, $priority = NULL)
	{
		if (! is_null($priority))
		{
			$priority = array("priority"	=> $priority);
		}
		else
		{
			$priority = array();
		}

		$service_node = $this->add_node("Service", NULL, $priority);

		if (! is_array($type))
		{
			$type = array($type);
		}

		foreach ($type as $t)
		{
			$service_node->add_node("Type", $t);
		}
		$service_node->add_node("URI", $uri);

		return $service_node;
	}
}