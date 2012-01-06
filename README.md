Kohana_XML is a XML modules to generate and read XML documents in Kohana.
It is build for KO3, but there are barely one or two lines that makes it KO3 specific, 
so I guess it should work for KO2.x without much trouble.
 
## Notable Features

* **Extendible, configurable drivers** — You can use the XML class to write simple XML, 
or use the Atom driver to generate Atom compliant XML, or write your own driver (extending XML 
or another driver) to generate XML compliant to any specs you want. Driver support initial 
configuration, which will be used when using native functions, and your own function. 
Namespaces and prefix, value filters, default attributes, node name abstraction are all part 
of driver configuration and are then used as such by native functions, so they are dealt with 
on the fly. But you can also write your own function very easily in your drivers, and writing 
an add_author($user_model) function in the Atom driver would take a second.

* **Dealing with objects of the same class whatever function you use** – $xml→add_node(“test”); 
generates another XML instance of the same driver you can add nodes to, import array or XML files
to, search in, modify, export, combine… The whole XML document becomes modular, easy to read and 
to modify, and to run through with method chaining. Just play Lego with your XML.

* **Magic get and get()** — allows to easily run through the document. For instance 
$atom→author→name will return an atom document author’s name, this regardless of your driver 
configuration. As another example of node name abstraction, if you’ve decided to abstract “pubDate”
with “updated” in your RSS2 driver configuration and “published” with “updated” in you Atom driver, 
then $atom→updated will give you the same result as $rss→updated.

* **Jelly-style driver configuration** — I liked the way Jelly initializes its models, so you can 
configure yours just the same way. Driver configuration then goes into a static meta class, which 
improves performance.

* You can still use **DOM functions** if you wish and reintegrate in Kohana_XML