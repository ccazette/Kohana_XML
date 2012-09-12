<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 *	Document   : core.php
 *	Created on : 1 mai 2009, 13:03:03
 *	@author Cedric de Saint Leger <c.desaintleger@gmail.com>
 *
 *	Description:
 *      XML_Core class.
 */

class XML_Core {
	/**
	 * @var string XML document version
	 */
	public static $xml_version = "1.0";

	/**
	 * @var string Root Node name
	 */
	public $root_node;

	/**
	 * The DOM_Element corresponding to this XML instance
	 * This is made public to use DOM functions directly if desired.
	 * @var DOM_Element XML instance DOM node.
	 */
	public $dom_node;

	/**
	 * Basically a handy shortcut of $this->dom_node->ownerDocument
	 * All XML instance belonging to the same document will have this attribute in common
	 * @var DOM_Document XML instance DOM document, owner of dom_node
	 */
	public $dom_doc;

	/**
	 * @var array Array of XML_Meta, containing metadata about XML drivers config
	 */
	protected static $_metas = array();


	/**
	 * This creates an XML object from the specified driver.
	 * Specify the driver name, or if there is no specific driver, the root node name
	 * @param string $driver [optional] Driver Name
	 * @param string $root_node [optional] Root Node name. Force the root node name. Must be used if no driver nor element is specified.
	 * @param string $element [optional] XML string or file to generate XML from. Must be used if no driver nor root_node is specified.
	 * @return XML XML object
	 */
	public static function factory($driver = NULL, $root_node = NULL, $element = NULL)
	{
		if ($driver)
		{
			// Let's attempt to generate a new instance of the subclass corresponding to the driver provided
			$class = 'XML_Driver_'.ucfirst($driver);

			// Register a new meta object
			XML::$_metas[strtolower($class)] = $meta = new XML_Meta;

			// Override the meta with driver-specific attributes
			call_user_func(array($class, "initialize"), $meta);

			//  Set content type to default if it is not already set, and report it as initialized
			$meta->content_type("text/xml")->set_initialized();

			return new $class($element, $root_node);
		}
		else
		{
			// Register a new meta object in the root node
			XML::$_metas["xml"] = $meta = new XML_Meta;

			// Set content type to default if it is not already set, and report it as initialized
			$meta->content_type("text/xml")->set_initialized();

			return new XML($element, $root_node);
		}
	}


	/**
	 * Class constructor. You should use the factory instead.
	 * @param string $element [optional] What to construct from. Could be some xml string, a file name, or a DOMNode
	 * @param string $root_node [optional] The root node name. To be specified if no driver are used.
	 * @return XML XML object instance
	 */
	public function __construct($element = NULL, $root_node = NULL)
	{
		// Create the initial DOMDocument
		$this->dom_doc = new DOMDocument(XML::$xml_version, Kohana::$charset);

		if ($root_node)
		{
			// If a root node is specified, overwrite the current_one
			$this->root_node = $root_node;
		}

		// Initialize the document with the given element
		if (is_string($element))
		{
			if (is_file($element) OR Valid::url($element))
			{
				// Generate XML from a file
				$this->dom_doc->load($element);
			}
			else
			{
				// Generate XML from a string
				$this->dom_doc->loadXML($element);
			}
			// Node is the root node of the document, containing the whole tree
			$this->dom_node = $this->dom_doc->documentElement;
		}
		elseif ($element instanceof DOMNode)
		{
			// This is called from another XML instance ( through add_node, or else...)
			// Let's add that node to the new object node 
			$this->dom_node = $element;

			// And overwrite the document with that node's owner document
			$this->dom_doc = $this->dom_node->ownerDocument;
		}
		elseif ( ! is_null($this->root_node))
		{
			// Create the Root Element from the driver attributes
			if ($this->meta()->get("namespace", $this->root_node))
			{
				$root_node_name = $this->meta()->get("prefix", $this->root_node) ? $this->meta()->get("prefix", $this->root_node).":$this->root_node" : $this->root_node;

				// Create the root node in its prefixed namespace
				$root_node = $this->dom_doc->createElementNS($this->meta()->get("namespace", $this->root_node), $root_node_name);
			}
			else
			{
				// Create the root node
				$root_node = $this->dom_doc->createElement($this->root_node);
			}

			// Append the root node to the object DOMDocument, and set the resulting DOMNode as it's node
			$this->dom_node = $this->dom_doc->appendChild($root_node);

			// Add other attributes
			$this->add_attributes($this->dom_node);
		}
		else
		{
			throw new Kohana_Exception("You have to specify a root_node, either in your driver or in the constructor if you're not using any.");
		}
	}


	/**
	 * Adds a node to the document
	 * @param string $name Name of the node. Prefixed namespaces are handled automatically.
	 * @param value $value [optional] value of the node (will be filtered). If value is not valid CDATA, 
	 * it will be wrapped into a CDATA section
	 * @param array $attributes [optional] array of attributes. Prefixed namespaces are handled automatically.
	 * @return XML instance for the node that's been added.
	 */
	public function add_node($name, $value = NULL, $attributes = array())
	{
		// Trim the name
		$name = trim($name);

		// Create the element
		$node = $this->create_element($name);

		// Add the attributes
		$this->add_attributes($node, $attributes);

		// Add the value if provided
		if ($value !== NULL)
		{
			$value = strval($this->filter($name, $value, $node));

			// Value is valid CDATA, let's add it as a new text node
			$value = $this->dom_doc->createTextNode($value);

			$node->appendChild($value);
		}

		// return a new XML instance of the same class from the child node
		$class = get_class($this);
		return new $class($this->dom_node->appendChild($node));
	}



	/**
	 * Magic get returns the first child node matching the value
	 * @param string $node_name
	 * @return mixed If trying to get a node:
	 * NULL will be return if nothing is matched, 
	 * A string value is returned if it a text/cdata node is matched
	 * An XML instance is returned otherwise, allowing chaining.
	 */
	public function __get($value)
	{
		if ( ! isset($this->$value))
		{
			$node = current($this->get($value));

			if ($node instanceof XML)
			{
				// Return the whole XML document
				return $node;
			}
			// We did not match any child nodes
			return NULL;
		}
		parent::__get($value);
	}



	/**
	 * Gets all nodes matching a name and returns them as an array.
	 * Can also be used to get a pointer to a particular node and then deal with that node as an XML instance.
	 * @param string $value name of the nodes desired
	 * @param bool $as_array [optional] whether or not the nodes should be returned as an array
	 * @return array Multi-dimensional array or array of XML instances
	 */
	public function get($value, $as_array = FALSE)
	{
		$return = array();

		$value = $this->meta()->alias($value);

		foreach ($this->dom_node->getElementsByTagName($value) as $item)
		{
			if ($as_array)
			{
				// Return as array but ignore root node
				$array = $this->_as_array($item);
				foreach ($array as $val)
				{
					$return[] = $val;
				}
			}
			else
			{
				$class = get_class($this);
				$return[] = new $class($item);
			}
		}
		return $return;
	}


	/**
	 * Queries the document with an XPath query
	 * @param string $query XPath query
	 * @param bool $as_array [optional] whether or not the nodes should be returned as an array
	 * @return array Multi-dimensional array or array of XML instances
	 */
	public function xpath($query, $as_array = TRUE)
	{
		$return = array();

		$xpath = new DOMXPath($this->dom_doc);

		foreach ($xpath->query($query) as $item)
		{
			if ($as_array)
			{
				$array = $this->_as_array($item);
				foreach ($array as $val)
				{
					$return[] = $val;
				}
			}
			else
			{
				$class = get_class($this);
				$return[] = new $class($item);
			}
		}
		return $return;
	}



	/**
	 * Exports the document as a multi-dimensional array.
	 * Handles element with the same name.
	 *
	 * Root node is ignored, as it is known and available in the driver.
	 * Example :
	 * <node_name attr_name="val">
	 * 		<child_node_name>
	 * 			value1
	 * 		</child_node_name>
	 * 		<child_node_name>
	 * 			value2
	 * 		</child_node_name>
	 * </node_name>
	 *
	 * Here's the resulting array structure :
	 * array ("node_name" => array(
	 * 					// array of nodes called "node_name"
	 * 					0 => array(
	 *							// Attributes of that node
	 *							"xml_attributes" => array(
	 *											"attr_name" => "val",
	 *													)
	 *							// node contents
	 * 							"child_node_name" => array(
	 * 												// array of nodes called "child_node_name"
	 * 												0 => value1,
	 * 												1 => value2,
	 * 														)
	 * The output is retro-actively convertible to XML using from_array().
	 * @return array
	 */
	public function as_array()
	{
		$dom_element = $this->dom_node;

		$return = array();

		// This function is run on a whole XML document and this is the root node.
		// That root node shall be ignored in the array as it driven by the driver and handles document namespaces.
		foreach($dom_element->childNodes as $dom_child)
		{
			if ($dom_child->nodeType == XML_ELEMENT_NODE)
			{
				// Let's run through the child nodes
				$child = $this->_as_array($dom_child);

				foreach ($child as $key => $val)
				{
					$return[$key][]=$val;
				}
			}
		}

		return $return;
	}



	/**
	 * Recursive as_array for child nodes
	 * @param DOMNode $dom_node
	 * @return Array
	 */
	private function _as_array(DOMNode $dom_node)
	{
		// All other nodes shall be parsed normally : attributes then text value and child nodes, running through the XML tree
		$object_element = array();

		// Get the desired node name for this node
		$node_name = $this->meta()->key($dom_node->tagName);

		// Get children, run through XML tree
		if ($dom_node->hasChildNodes())
		{
			if (!$dom_node->firstChild->hasChildNodes())
			{
				// Get text value
				$object_element[$node_name] = trim($dom_node->firstChild->nodeValue);
			}

			foreach($dom_node->childNodes as $dom_child)
			{
				if ($dom_child->nodeType === XML_ELEMENT_NODE)
				{
					$child = $this->_as_array($dom_child);

					foreach ($child as $key=>$val)
					{
						$object_element[$node_name][$key][]=$val;
					}
				}
			}
		}

		// Get attributes
		if ($dom_node->hasAttributes())
		{
	 		$object_element[$node_name] = array('xml_attributes' => array());
			foreach($dom_node->attributes as $att_name => $dom_attribute)
			{
				// Get the desired name for this attribute
				$att_name = $this->meta()->key($att_name);
				$object_element[$node_name]['xml_attributes'][$att_name] = $dom_attribute->value;
			}
		}
		return $object_element;
	}


	/**
	 * Converts an array to XML. Expected structure is given in as_array().
	 * However, from_array() is essentially more flexible regarding to the input array structure,
	 * as we don't have to bother about nodes having the same name.
	 * Try something logical, that should work as expected.
	 * @param object $mixed
	 * @return XML
	 */
	public function from_array($array)
	{
		$this->_from_array($array, $this->dom_node);

		return $this;
	}


	/**
	 * Array shall be like : array('element_name' => array( 0 => text, 'xml_attributes' => array()));
	 * @param object     $mixed
	 * @param DOMElement $dom_element
	 * @return 
	 */
	protected function _from_array($mixed, DOMElement $dom_element)
	{
		if (is_array($mixed))
		{
			foreach( $mixed as $index => $mixed_element )
			{
				if ( is_numeric($index) )
				{
					// If we have numeric keys, we're having multiple children of the same node.
					// Append the new node to the current node's parent
					// If this is the first node to add, $node = $dom_element
					$node = $dom_element;
					if ( $index != 0 )
					{
						// If not, lets create a copy of the node with the same name 
						$node = $this->create_element($dom_element->tagName);
						// And append it to the parent node
						$node = $dom_element->parentNode->appendChild($node);
					}
					$this->_from_array($mixed_element, $node);
				}
				elseif ($index == "xml_attributes")
				{
					// Add attributes to the node
					$this->add_attributes($dom_element, $mixed_element);
				}
				else
				{
					// Create a new element with the key as the element name.
					// Create the element corresponding to the key
					$node = $this->create_element($index);

					// Add the driver attributes
					$this->add_attributes($node);

					// Append it
					$dom_element->appendChild($node);

					// Treat the array by recursion
					$this->_from_array($mixed_element, $node);
				}
			}
		}
		elseif ($mixed)
		{
			// This is a string value that shall be appended as such
			$mixed = $this->filter($dom_element->tagName, $mixed, $dom_element);
			$dom_element->appendChild($this->dom_doc->createTextNode($mixed));
		}
	}


	/**
	 * This function is used to import another XML instance, or whatever we can construct XML from (string, filename, DOMNode...)
	 * 
	 * $xml1 = XML::factory("atom", "<feed><bla>bla</bla></feed>");
	 * $xml2 = XML::factory("rss", "<test></test>");
	 * $node_xml2 = $xml2->add_node("key");
	 * 
	 * // outputs "<test><key><feed><bla>bla</bla></feed></key></test>"
	 * $node_xml2->import($xml1)->render();
	 * 
	 * // outputs "<feed><bla>bla</bla></feed><key><feed><bla>bla</bla></feed></key>"
	 * $xml1->import($xml2->get("key"))->render();
	 * 
	 * @param object $xml XML instance or DOMNode
	 * @return object $this Chainable function
	 */
	public function import($xml)
	{
		if (! $xml instanceof XML)
		{
			// Attempt to construct XML from the input
			$class = get_class($this);
			$xml = new $class($xml);
		}
		// Import the node, and all its children, to the document
		$node = $this->dom_doc->importNode($xml->dom_node, TRUE);
		$this->dom_node->appendChild($node);

		return $this;
	}


	/**
	 * Creates an element, sorts out namespaces (default / prefixed)
	 * @param string $name element name
	 * @return DOMElement
	 */
	private function create_element($name)
	{
		$name = $this->meta()->alias($name);

		// Let's check if the element name has a namespace, and if this prefix is defined in our driver
		if ($namespace_uri = $this->meta()->get("namespace", $name))
		{
			if (stristr($name, ":"))
			{
				// Separate the namespace prefix and the name
				list($prefix, $name) = explode(":", $name);

				// Register the prefixed namespace in the document root
				$this->dom_doc->documentElement->setAttributeNS("http://www.w3.org/2000/xmlns/" ,"xmlns:".$prefix, $namespace_uri);

				// Create the prefixed element within that namespace
				$node = $this->dom_doc->createElementNS($namespace_uri, $prefix.":".$name);
			}
			else
			{
				// Create the element normally
				$node = $this->dom_doc->createElement($name);

				// Add the new default namespace as an attribute.
				$node->setAttribute("xmlns", $namespace_uri);
			}
		}
		else
		{
			// Simply create the element
			$node = $this->dom_doc->createElement($name);
		}
		return $node;
	}


	/**
	 * Applies attributes to a node
	 * @param DOMNode $node
	 * @param array  $attributes as key => value
	 * @return DOMNode
	 */
	private function add_attributes(DOMNode $node, $attributes = array())
	{
		$node_name = $this->meta()->alias($node->tagName);

		if ($this->meta()->get("attributes", $node_name))
		{
			$attributes = array_merge($this->meta()->get("attributes", $node_name), $attributes);
		}

		foreach ($attributes as $key => $val)
		{
			// Trim elements
			$key = $this->meta()->alias(trim($key));
			$val = $this->filter($key, trim($val), $node);

			// Set the attribute
			// Let's check if the attribute name has a namespace prefix, and if this prefix is defined in our driver
			if ($namespace_uri = $this->meta()->get("namespace", $key)
				AND stristr($name, ":"))
			{
				// Separate the namespace prefix and the name
				list($prefix, $name) = explode(":", $name);

				// Register the prefixed namespace
				$this->dom_node->setAttributeNS("http://www.w3.org/2000/xmlns/" ,"xmlns:".$prefix, $namespace_uri);

				// Add the prefixed attribute within that namespace
				$node->setAttributeNS($namespace_uri, $key, $val);
			}
			else
			{
				// Simply add the attribute
				$node->setAttribute($key, $val);
			}
		}
		return $node;
	}


	/**
	 * Applies filter on a value.
	 * These filters are callbacks usually defined in the driver.
	 * They allow to format dates, links, standard stuff, and play 
	 * as you wish with the value before it is added to the document.
	 * 
	 * You could even extend it and modify the node name.
	 * 
	 * @param string $name
	 * @param string $value
	 * @return string $value formatted value
	 */
	protected function filter($name, $value, &$node)
	{
		$name = $this->meta()->alias($name);

		if ($this->meta()->get("filter", $name))
		{
			return call_user_func(array($this, $this->meta()->get("filter", $name)), $value, $node);
		}
		return $value;
	}


	/**
	 * This is a classic filter that takes a uri and makes a proper link
	 * @param object $value
	 * @return $value
	 */
	public function normalize_uri($value, $node)
	{
		if (strpos($value, '://') === FALSE)
		{
			if (strlen(URL::base()) > 1 AND stristr($value, URL::base()))
			{
				// Make sure the path is not base related
				$value = str_replace(URL::base(), '', $value);
			}
			// Convert URIs to URLs
			$value = URL::site($value, TRUE);
		}
		return $value;
	}


	/**
	 * Another classic filter to deal with boolean
	 * @param boolean $value
	 * @return string $value, true or false
	 */
	public function normalize_bool($value)
	{
		return $value ? "true" : "false";
	}


	/**
	 * Returns this drivers XML metadata
	 * @return XML_Meta
	 */
	public function meta()
	{
		return XML::$_metas[strtolower(get_class($this))];
	}


	/**
	 * Outputs nicely formatted XML when converting as string
	 * @return string
	 */
	public function __toString()
	{
		return $this->render(TRUE);
	}


	/**
	 * Render the XML.
	 * @param boolean $formatted [optional] Should the output be formatted and indented ?
	 * @return string
	 */
	public function render($formatted = FALSE)
	{
		$this->dom_doc->formatOutput = $formatted;
		return $this->dom_doc->saveXML();
	}


	/**
	 * Outputs the XML in a file
	 * @param string filename
	 * @return 
	 */
	public function export($file)
	{
		return $this->dom_doc->save($file);
	}


	/**
	 * Returns this instance node value, if the dom_node is a text node
	 * 
	 * @return string
	 */
	public function value()
	{
		if ($this->dom_node->hasChildNodes() AND $this->dom_node->firstChild->nodeType === XML_TEXT_NODE)
		{
			return $this->dom_node->nodeValue;
		}
		return NULL;
	}


	/**
	 * Returns this instance node value
	 * 
	 * @return string|array attributes as array of attribute value if a name is specified
	 */
	public function attributes($attribute_name = NULL)
	{
		if ($attribute_name === NULL)
		{
			// Return an array of attributes
			$attributes = array();

			if ($this->dom_node->hasAttributes())
			{
				foreach ($this->dom_node->attributes as $attribute)
				{
					$attributes[$attribute->name] = $attribute->value;
				}
			}
			return $attributes;
		}

		// Simply return the attribute value
		return $this->dom_node->getAttribute($attribute_name);
	}
} // End XML_Core