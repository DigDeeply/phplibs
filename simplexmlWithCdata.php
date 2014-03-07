<?php
/**
* to show <title lang="en"><![CDATA[Site Title]]></title>   instead of <title lang="en">Site Title</title>
*
*/
// http://coffeerings.posterous.com/php-simplexml-and-cdata
class SimpleXMLExtended extends SimpleXMLElement
  {
  public function addCData($cdata_text)
    {
    $node = dom_import_simplexml($this); 
    $no   = $node->ownerDocument; 
    $node->appendChild($no->createCDATASection($cdata_text)); 
    } 
  }
$xmlFile    = 'config.xml';
// instead of $xml = new SimpleXMLElement('<sites/>');
$xml = new SimpleXMLExtended('<sites/>');
$site = $xml->addChild('site');
// instead of $site->addChild('site', 'Site Title');
$site->title = NULL; // VERY IMPORTANT! We need a node where to append
$site->title->addCData('Site Title');
$site->title->addAttribute('lang', 'en');
$xml->asXML($xmlFile);
?>