<?php

namespace src\Traits\Booking;

use DOMDocument;
use DOMElement;
use SimpleXMLElement;
trait ChmXmlBookingConvert
{
   static function precessXmlV2()
   {
      $template=self::getHierarchyParams();
      $dom = new DOMDocument('1.0', 'UTF-8');
      $root = $dom->createElement('root');
      $dom->appendChild($root);
      self::xmlRecursiveV2($template,$root,$dom);
      $formattedXml=$dom->saveXML();
      $formattedXml=str_replace('</root>','',$formattedXml);
      $formattedXml=str_replace('<root>','',$formattedXml);
      return $formattedXml;
   }
   static function xmlRecursiveV2(array $params, DOMElement $formattedXml, DOMDocument $doc, $data = [], $type = 'booking') {
    foreach ($params as $val) {
        $value = '';
        $name = $val['name'];
        $bakuun = $val['bakuun'];
        $isCtd = is_string($bakuun) && str_starts_with($bakuun, 'CTD');

        if ($bakuun !== null) {
            if ($isCtd) {
                $bakuun = str_replace('CTD', '', $bakuun);
            }
            if ($val['type'] === 'Hardcoded') {
                $value = self::transformDefaultValue($bakuun);
            } else {
                $value = self::getVal($bakuun, $type, $data);
            }
        }
        if ($val['icon'] === 'attribute') {
            $formattedXml->setAttribute($name, $value);
            $child = $formattedXml;
        } elseif ($isCtd) {
            $child = $doc->createElement($name);
            $cdata = $doc->createCDATASection($value);
            $child->appendChild($cdata);
            $formattedXml->appendChild($child);
        } else {
            $child = $doc->createElement($name, is_array($value) ? '' : $value);
            $formattedXml->appendChild($child);
        }

        if (isset($val['children'])) {
            switch ($bakuun) {
                case 'roomStays':
                    $rooms = self::$rooms;
                    foreach ($rooms as $room) {
                        self::xmlRecursiveV2($val['children'], $child, $doc, $room, 'room');
                    }
                    break;
                case 'bookingPerDayPrices':
                    foreach ($data['bookingPerDayPrices'] as $perDayPrice) {
                        $children = $val['children'];
                        self::xmlRecursiveV2($children, $child, $doc, $perDayPrice, 'perDayPrices');
                    }
                    break;
                case 'guests':
                    $guestDet = self::$guests;
                    if ($type === 'room') {
                        $guestDet = self::$roomGuestMapping[$data['bk_room_id']];
                        $guestDet = array_filter($guestDet, fn($guest) => $guest['ResGuestRPH'] == $data['roomIndex']);
                    }
                    foreach ($guestDet as  $guest) {
                        $children = $val['children'];
                        self::xmlRecursiveV2($children, $child, $doc, $guest, 'guests');
                    }
                    // if ($bakuun === 'partnerGuests') {
                    //     $child->appendChild($doc->createTextNode(self::$bookingDet['partnerGuest']));
                    // }
                    break;
                case 'guestCounts':
                    foreach ($data['guestCounts'] as $guestCount) {
                        self::xmlRecursiveV2($val['children'], $child, $doc, $guestCount, 'guestCount');
                    }
                    break;
                default:
                    self::xmlRecursiveV2($val['children'], $child, $doc, $data, $type);
            }
        }
    }
}

   static function  xmlRecursiveSimpleXml(array $params,SimpleXMLElement &$formattedXml,$data=[],$type='booking'){
    foreach ($params as $val) {
         $value='';
         $name=$val['name'];$bakuun=$val['bakuun'];
         $isctd=is_string($bakuun) && str_starts_with($bakuun, 'CTD');
        if($bakuun!=null){
            if($isctd){
                $bakuun=str_replace('CTD','',$bakuun);
            }
            if($val['type']=='Hardcoded')
                $value=self::transformDefaultValue($bakuun);
            else{
                $value=self::getVal($bakuun,$type,$data);
            }
        }
        if($val['icon']=='attribute'){
            $formattedXml->addAttribute($name,$value);
            $child=$formattedXml;
        }elseif($isctd){
            $dom = dom_import_simplexml($formattedXml);
            $owner = $dom->ownerDocument;
            $child = $owner->createElement($name);
            $cdata = $owner->createCDATASection($value);
            $child->appendChild($cdata);
            $dom->appendChild($child);
        }else{
            if (strpos($name, ':') !== false) {
                list($prefix, $localName) = explode(':', $name, 2);
                // dd($prefix,$localName);
                // $formattedXml->registerXPathNamespace($prefix, $name);
                $child = $formattedXml->addChild($prefix . ':' . $localName, is_array($value) ? '' : $value);
            } else {
                // If no namespace, add child normally
                $child = $formattedXml->addChild($name, is_array($value) ? '' : $value);
            }
            // dump($name);
            // $child = $formattedXml->addChild($name,is_array($value)?'':$value);
        }
        // dd($child->asXML());
        if(isset($val['children'])){
            switch ($bakuun) {
                case 'roomStays':
                    $rooms=self::$rooms;
                    foreach ($rooms as $index=>$room) {
                        self::xmlRecursiveV2($val['children'],$child,$room,'room');
                     }
                break;
                case 'bookingPerDayPrices':
                     foreach($data['bookingPerDayPrices'] as $index=>$perDayPrice){
                       $children=$val['children'];
                       self::xmlRecursiveV2($children,$child,$perDayPrice,'perDayPrices');
                   }
                break;
                case 'guests':
                    $guestDet=self::$guests;
                    if($type=='room'){
                        $guestDet=self::$roomGuestMapping[$data['bk_room_id']];
                        $guestDet=array_filter($guestDet,fn($guest)=>$guest['ResGuestRPH']==$data['roomIndex']);
                    }
                    foreach($guestDet as $index=>$guest){
                       $children=$val['children'];
                       self::xmlRecursiveV2($children,$child,$guest,'guests');
                    }
                    if($bakuun=='partnerGuests')
                           $child[$index+1]=self::$bookingDet['partnerGuest'];
                break;
                case 'guestCounts':
                    foreach($data['guestCounts'] as $index=>$guestCount){
                        self::jsonRecursiveV2($val['children'],$child,$guestCount,'guestCount');
                }
                break;
                default:
                    self::xmlRecursiveV2($val['children'],$child,$data,$type);
            }
        }
    }
}
}
