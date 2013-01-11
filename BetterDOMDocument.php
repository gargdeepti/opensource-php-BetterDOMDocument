<?php
/**
 * Highwire Better DOM Document
 *
 * Copyright (c) 2010-2011 Board of Trustees, Leland Stanford Jr. University
 * This software is open-source licensed under the GNU Public License Version 2 or later
 */

class BetterDOMDocument extends DOMDocument {

  private $auto_ns = FALSE;
  public  $ns = array();
  public  $error_checking = 'strict'; // Can be 'strict', 'warning', 'none' / FALSE

  function __construct($xml = FALSE, $auto_register_namespaces = FALSE, $error_checking = 'strict') {
    parent::__construct();
    
    // Check up error-checking
    if ($error_checking == FALSE) {
      $this->error_checking = 'none';
    }
    else {
      $this->error_checking = $error_checking;
    }
    if ($this->error_checking != 'strict') {
      $this->strictErrorChecking = FALSE;
    }
    
    if(is_object($xml)){
      $class = get_class($xml);
      if ($class == 'DOMElement') {
        $this->appendChild($this->importNode($xml, true));
      }
      if ($class == 'DOMDocument') {
        if ($xml->documentElement) {
          $this->appendChild($this->importNode($xml->documentElement, true));
        }
      }
    }

    if ($xml && is_string($xml)) {
      if ($this->error_checking == 'none') {
        @$this->loadXML($xml);
      }
      else {
        $this->loadXML($xml);
      }

      // There is no way in DOMDocument to auto-detect or list namespaces.
      // Regretably the only option is to parse the first container element for xmlns psudo-attributes
      if ($auto_register_namespaces) {
        $this->auto_ns = TRUE;
        if (preg_match('/<[^\?^!].+?>/s', $xml, $elem_match)) {
          if (preg_match_all('/xmlns:(.+?)=.*?["\'](.+?)["\']/s', $elem_match[0], $ns_matches)) {
            foreach ($ns_matches[1] as $i => $ns_key) {
              $this->registerNamespace(trim($ns_key), trim($ns_matches[2][$i]));
            }
          }
        }
      }
    }
  }

  // $raw should be FALSE, 'full', or 'inner'
  function getArray($raw = FALSE, $node = NULL) {
    $array = false;

    if ($node) {
      if ($raw == 'full') {
        $array['#raw'] = $this->saveXML($node);
      }
      if ($raw == 'inner') {
        $array['#raw'] = $this->innerText($node);
      }
      if ($node->hasAttributes()) {
        foreach ($node->attributes as $attr) {
          $array['@'.$attr->nodeName] = $attr->nodeValue;
        }
      }

      if ($node->hasChildNodes()) {
        if ($node->childNodes->length == 1 && $node->firstChild->nodeType == XML_TEXT_NODE) {
          $array['#text'] = $node->firstChild->nodeValue;
        }
        else {
          foreach ($node->childNodes as $childNode) {
            if ($childNode->nodeType == XML_ELEMENT_NODE) {
              $array[$childNode->nodeName][] = $this->getArray($raw, $childNode);
            }
          }
        }
      }
    }
    // Else no node was passed, which means we are processing the entire domDocument
    else {
      foreach ($this->childNodes as $childNode) {
        if ($childNode->nodeType == XML_ELEMENT_NODE) {
          $array[$childNode->nodeName][] = $this->getArray($raw, $childNode);
        }
      }
    }

    return $array;
  }

  function getNamespaces() {
    return $this->ns;
  }
  
  // Get the inner text of the element
  function innerText($context = NULL) {
    if (!$context) {
      $context = $this->documentElement;
    }
    else if (is_string($context)) {
      $context = $this->querySingle($context);
    }
    
    $pattern = "/<".preg_quote($context->nodeName)."\b[^>]*>(.*)<\/".preg_quote($context->nodeName).">/s";
    $matches = array();
    preg_match($pattern, $this->saveXML($context), $matches);
    return $matches[1];
  }

  function createElementFromXML($xml) {
    $dom = new BetterDOMDocument($xml, $this->auto_ns);
    if (!$dom->documentElement) {
      highwire_system_message('BetterDomDocument Error: Invalid XML: ' . $xml, 'error');
    }
    $element = $dom->documentElement;
    return $this->importNode($element, true);
  }

  function append($newnode, $ref = NULL) {
    if (is_string($newnode)) {
      $newnode = $this->createElementFromXML($newnode);
    }
    if (!$ref) {
      $ref = $this->documentElement;
    }
    $ref->insertBefore($newnode, $ref->lastChild);
  }

  function prepend($newnode, $ref = NULL) {
    if (is_string($newnode)) {
      $newnode = $this->createElementFromXML($newnode);
    }
    if (is_string($ref)) { 
      $ref = $this->querySingle($ref);
    }
    else if (!$ref) {
      $ref = $this->documentElement;
    }
    if (!$ref){
      return FALSE;
    }
    $ref->insertBefore($newnode, $ref->firstChild);
  }

  function prependSibling($newnode, $ref) {
    if (is_string($newnode)) {
      $newnode = $this->createElementFromXML($newnode);
    }
    if (is_string($ref)) { 
      $ref = $this->querySingle($ref);
    }
    else if (!$ref) {
      $ref = $this->documentElement;
    }
    if (!$ref){
      return FALSE;
    }
    
    $ref->parentNode->insertBefore($newnode, $ref);
  }
  
  function appendSibling($newnode, $ref) {
    if (is_string($newnode)) {
      $newnode = $this->createElementFromXML($newnode);
    }
    if (is_string($ref)) { 
      $ref = $this->querySingle($ref);
    }
    else if (!$ref) {
      $ref = $this->documentElement;
    }
    if (!$ref){
      return FALSE;
    }
    
    if ($ref->nextSibling) { 
      // $ref has an immediate brother : insert newnode before this one 
      return $ref->parentNode->insertBefore($newnode, $ref->nextSibling); 
    }
    else { 
      // $ref has no brother next to him : insert newnode as last child of his parent 
      return $ref->parentNode->appendChild($newnode); 
    } 
  }
  
  // Give an xpath or an element, return another BetterDOMDocument
  function extract($xpath_or_element, $contextnode = NULL) {
    if (is_string($xpath_or_element)) {
      $domNode = $this->querySingle($xpath_or_element, $contextnode);
      if (!$domNode) return FALSE;
    }
    else {
      $domNode = $xpath_or_element;
    }
    $dom = new BetterDOMDocument($domNode);
    $dom->ns = $this->ns;
    return $dom;
  }

  function query($xpath, $contextnode = NULL) {
    $xob = new DOMXPath($this);

    // Register the namespaces
    foreach ($this->ns as $namespace => $url) {
      $xob->registerNamespace($namespace, $url);
    }

    // DOMDocument is a piece of shit when it comes to namespaces
    // Instead of passing the context node, hack the xpath query to manually construct context using xpath
    if ($contextnode) {
      $ns = $contextnode->namespaceURI;
      $lookup = array_flip($this->ns);
      $prefix = $lookup[$ns];
      $prepend = str_replace('/', '/'. $prefix . ':', $contextnode->getNodePath());
      return $xob->query($prepend . $xpath);
    }
    else {
      return $xob->query($xpath);
    }
  }

  function querySingle($xpath, $contextnode = NULL) {
    $result = $this->query($xpath, $contextnode);
    
    if (!$result) {
      return NULL;
      dpm($xpath);
    }
    
    if ($result->length) {
      return $result->item(0);
    }
    else {
      return NULL;
    }
  }

  // Alias for backwards compat
  function query_single($xpath, $contextnode = NULL) {
    return $this->querySingle($xpath, $contextnode);
  }

  function registerNamespace($namespace, $url) {
    $this->ns[$namespace] = $url;
  }
  
  function tranform($xsl, $contextnode = NULL) {
    if (!$contextnode) {
      $doc = $this;
    }
    else {
      if (is_string($contextnode)) {
        $contextnode = $this->querySingle($contextnode);
      }
      $doc = new BetterDOMDocument($contextnode);
    }
    
    $xslDoc = new BetterDOMDocument($xsl);
    $xslt = new XSLTProcessor();
    $xslt->importStylesheet($xslDoc);
    
    return new BetterDomDocument($xslt->transformToDoc($doc));
  }
  
  function asHTML($contextnode = NULL) {
    $xsl = '
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
  <xsl:template match="*">
  <span class="{translate(name(.),\':\',\'-\')}">
    <xsl:for-each select="./@*">
      <xsl:attribute name="data-{translate(name(.),\':\',\'-\')}">
        <xsl:value-of select="." />
      </xsl:attribute>
    </xsl:for-each>
    <xsl:apply-templates/>
  </span>
  </xsl:template>
</xsl:stylesheet>';
    
    $transformed = $this->tranform($xsl, $contextnode);
    return $transformed->out();
  }

  // Note all objects are passed by reference
  function replace($node, $replace) {
    if (is_string($node)) {
      $node = $this->querySingle($node);
    }
    if (is_string($replace)) {
      $replace = $this->createElementFromXML($replace);
    }
    if (!$replace->ownerDocument->documentElement->isSameNode($this->documentElement)) {
      $replace = $this->importNode($replace, true);
    }
    $node->parentNode->replaceChild($replace, $node);
    $node = $replace;
  }

  // Can pass a DOMNode, a DOMNodeList, or an xpath string
  function remove($node) {
    if (is_string($node)) {
      $node = $this->query($node);
    }
    if ($node) {
      if (get_class($node) == 'DOMNodeList') {
        foreach($node as $item) {
          $this->remove($item);
        }
      }
      else {
        $node->parentNode->removeChild($node);
      }
    }
  }

  // contextnode can be either a DOMNode or an xpath string
  function out($contextnode = NULL) {
    if (!$contextnode) {
      $contextnode = $this->firstChild;
    }
    if (is_string($contextnode)) {
      $contextnode = $this->querySingle($contextnode);
      if (!$contextnode) return '';
    }
    
    if (!$this->documentElement) return '';
    
    return $this->saveXML($contextnode, LIBXML_NOEMPTYTAG);
  }

}

