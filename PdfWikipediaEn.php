<?php

require_once 'dompdf/autoload.inc.php';
require_once 'components/functions.php';


use Dompdf\Dompdf;
use Dompdf\Options;


class PdfLoaderWikipedia
{
  
  //аттрибут класса
  public $url;

  //конструктор класса, который передает атрибуту значение
  public function __construct($url)
  {
    $this->url = $url;
  }


  //get all a-tags from div.mw-body-content
  public function getLinks() 
  {
    $html = file_get_contents($this->url); 
    $dom = new DOMDocument();
    
    //вылазила ошибка: Warning: DOMDocument::loadHTML(): Unexpected end tag : 
    //p in Entity, line: 54 in /opt/lampp/htdocs/wikipdf.ru/PdfConverter.php on line 34 
    //решение тут https://joomlaforum.ru/index.php/topic,304834.0.html    libxml_use_internal_errors(true);
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);

    $links = [];
    $xPath = new DOMXPath($dom);
    $anchorTags = $xPath->evaluate("//div[@class=\"mw-body-content\"]//a/@href");

    //create an array[] of needed links to iterate trhough and to create PDF files from
    foreach ($anchorTags as $anchorTag) 
    {
      //decoded link
      $aLink = urldecode($anchorTag->nodeValue);
      $links[] = $aLink;
    
      //if link is not already in array:    
      if (!in_array($aLink, $links)) {
        $links[] = $aLink;
      }
    }

    //recheck the $links[] for there are doubled links
    $linksChecked = [];
    foreach ($links as $link) 
    {
      $linkDecoded = urldecode($link);
      if (!in_array($linkDecoded, $linksChecked)) 
      {
        if (count($linksChecked) < 30) {
          $linksChecked[] = $linkDecoded; 
        }
      }
    }
    return $linksChecked; //test
  }


  public function purifyLinks($linksArray) 
  {
    //handle the links for more usability
    //get rid of garbage
    foreach($linksArray as $link) 
    {
      //decode url to cyrillic
      $linkDecoded = urldecode($link);

      //if url contains garbage, delete 
      if ((strpos($linkDecoded, 'File:') === false) && (strpos($linkDecoded, 'Category:') === false) && 
        (strpos($linkDecoded, 'index.php') === false) && (strpos($linkDecoded, 'Help:') === false) && 
        (strpos($linkDecoded, '#') === false) && (strpos($linkDecoded, 'Template:') === false) && 
        (strpos($linkDecoded, 'Special:') === false) && (strpos($linkDecoded, '//www.') === false) && 
        (strpos($linkDecoded, 'Wikipedia:') === false) && (strpos($linkDecoded, 'upload.wikimedia.org') === false))
      {
        //add 'https://ru.wikipedia.org' if needed
        if ($linkDecoded[0] === '/') 
        {
          $fullLink  = trim('https://en.wikipedia.org' . $linkDecoded);
     
          //PDF file title
          $title = substr($fullLink, strpos($fullLink, 'wiki/') +5); 
          
          $fullLinks[] = $fullLink;
        }
      }
    }
    return $fullLinks;
  }



  public function renderLinks($links)
  {
    $html = '';
    
    foreach($links as $fullLink) 
    { 
      echoJS($fullLink);
      //get html of every article from array[]
      $html = file_get_contents($fullLink);
    }
  }



  public function savePdf($link)
  {

    $title = substr($link, strpos($link, 'wiki/') +5); 
    //get html of every article from array[]
    $html = file_get_contents($link);

    //creating PDFs 
    try 
    {
      $options = new Options();
      $options->set('defaultFont', 'DejaVu Sans'); 
      $dompdf = new Dompdf($options);

      //an alleged workout to POST images into pdf
      //according to https://github.com/dompdf/dompdf/wiki/Usage
      $context = stream_context_create
      (
        [ 
          'ssl' => 
          [ 
            'verify_peer' => FALSE, 
            'verify_peer_name' => FALSE,
            'allow_self_signed'=> TRUE 
          ] 
        ]
      );
            
      $dompdf->setHttpContext($context);

      //handle $html of an article
      $dompdf->loadHtml($html);

      //https://github.com/dompdf/dompdf/issues/2075
      //this solves the no-images-in-PDF issue
      $dompdf->set_protocol('http://');

      $dompdf->setPaper('A4', 'portrait');
      $dompdf->render($title);
      $output = $dompdf->output();
      file_put_contents("G:/Documents/WIKIPDF/$title.pdf", $output);
              
      //delete variables 
      unset($html);
      unset($output);
      unset($dompdf);
    } catch (Exception $e) { echo 'Выброшено исключение: ' .  $e . "\n"; } 
  }
}