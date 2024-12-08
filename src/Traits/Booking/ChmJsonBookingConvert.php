<?php

/**
 * The ChmJsonBookingConvert Class
 *
 * @see ChmJsonBookingConvert Class
 * phpcs:disable
 */

namespace src\Traits\Booking;


trait ChmJsonBookingConvert
{
    public static function processJson($template)
    {
        $template = json_decode($template,true);
        $formattedJson=[];
        $template=self::getHierarchyParams();
        self::jsonRecursiveV2($template,$formattedJson);
        return $formattedJson;
    }
    static function getHierarchyParams()
    {
        $channelsBlock=self::$channelsBlock;
        $params=$channelsBlock->format_parameters;
        $changeParentKey=[];
        foreach ($params as $index=>&$param) {
            $nextvalKey=$index+1;
            $nextval=$params[$nextvalKey]??null;
            if(isset($changeParentKey[$param['parentId']])){
               $param['parentId']=$changeParentKey[$param['parentId']];
            }
            while ($nextval && $nextval['icon']==$param['icon'] && $nextval['name']==$param['name']) {
                $changeParentKey[$nextval['id']]=$param['id'];
                unset($params[$nextvalKey]);
                $nextval=$params[++$nextvalKey]??null;
             }
        }
        return self::buildHierarchy($params);
    }
    static function buildHierarchy(array $elements, $parentId = null) {
        $branch = [];

        foreach ($elements as $element) {
            if ($element['parentId'] === $parentId) {
                $children = self::buildHierarchy($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }
    static function  jsonRecursiveV2(array $params,array &$formattedJson,$data=[],$type='booking'){
            foreach ($params as $key => $val) {
                 $value='';
                 $name=$val['name'];$bakuun=$val['bakuun'];
                if($bakuun!=null){
                    if($val['type']=='Hardcoded')
                        $value=self::transformDefaultValue($bakuun);
                    else{
                        $value=self::getVal($bakuun,$type,$data);
                    }
                }
                $formattedJson[$name]=$value;
                if(isset($val['children'])){
                    switch ($bakuun) {
                        case '[]':
                            $formattedJson[$name]=[];
                            $formattedJson[$name][]=[];
                            $child = &$formattedJson[$name][0];
                            self::jsonRecursiveV2($val['children'],$child,$data,$type);
                        break;
                        case 'roomStays':
                            $rooms=self::$rooms;
                            $child = &$formattedJson[$name];
                            $child=[];
                            foreach ($rooms as $index=>$room) {
                                $child[$index]=[];
                                self::jsonRecursiveV2($val['children'],$child[$index],$room,'room');
                             }
                        break;
                        case 'bookingPerDayPrices':
                             $child = &$formattedJson[$name];
                             $child=[];
                             $arr=$val['children'][0];
                             $indexkey=null;
                             if(isset($arr['children'])){
                                $indexkey=$arr['bakuun'];
                             }
                             foreach($data['bookingPerDayPrices'] as $index=>$perDayPrice){
                                if($indexkey){
                                   $indexVal=$perDayPrice[$indexkey]??'';
                                   $children=$arr['children'];
                                   $index=$indexVal;
                                }else
                                  $children=$val['children'];
                               $child[$index]=[];
                               self::jsonRecursiveV2($children,$child[$index],$perDayPrice,'perDayPrices');
                           }
                        break;
                        case 'guests':
                        case 'partnerGuests':
                            $guestDet=self::$guests;
                            if($type=='room'){
                                $guestDet=self::$roomGuestMapping[$data['bk_room_id']];
                                $guestDet=array_filter($guestDet,fn($guest)=>$guest['ResGuestRPH']==$data['roomIndex']);
                            }
                            $child = &$formattedJson[$name];
                            $child=[];
                            $arr=$val['children'][0];
                            $indexkey=null;
                            if(isset($arr['children']))
                               $indexkey=$arr['bakuun'];
                            foreach($guestDet as $index=>$guest){
                                if($indexkey){
                                   $indexVal=$guest[$indexkey]??'';
                                   $children=$arr['children'];
                                   $index=$indexVal;
                                }else
                                  $children=$val['children'];
                               $child[$index]=[];
                               if($bakuun=='partnerGuests')
                                     $child[$index]['company']=null;
                               self::jsonRecursiveV2($children,$child[$index],$guest,'guests');
                            }
                            if($bakuun=='partnerGuests')
                                   $child[$index+1]=self::$bookingDet['partnerGuest'];
                        break;
                        case 'guestCounts':
                            $child = &$formattedJson[$name];
                            $child=[];
                            foreach($data['guestCounts'] as $index=>$guestCount){
                                $child[$index]=[];
                                self::jsonRecursiveV2($val['children'],$child[$index],$guestCount,'guestCount');
                           }
                        break;
                        default:
                            $child=&$formattedJson[$name];
                            $child=[];
                            self::jsonRecursiveV2($val['children'],$child,$data,$type);
                    }
                }
            }
    }
    static function getAttributes(array $params){
            $result=[];
            foreach ($params as $key => $val) {
                   if($val['icon']=='master')
                        break;
                    $result[]=$val;
            }
        return $result;
    }
    public static function jsonRecursive(array $template,array &$formattedJson,$data=[],$type='booking')
    {
        foreach ($template as $key => $val) {
            $paramsKey=self::getParams($key,$type);
            $str=self::getVal($paramsKey,$type,$data);
            if(self::isEmpty($str))$str=self::getDefaulVal($key);
            $valIsArray=is_array($val);
            $child = &$formattedJson[$key];
            if(self::isEmpty($child))
               $child=($valIsArray &&!in_array($paramsKey,['guestRPHs'])?[]:($str==""?null:$str));
            if($paramsKey=='roomStays'){
                $rooms=self::$rooms;
               foreach ($rooms as $index=>$room) {
                   $child[$index]=[];
                   self::jsonRecursive($val[0],$child[$index],$room,'room');
                }
                continue;
            }
            if(in_array($paramsKey,['guestCounts'])){
                foreach($data['guestCounts'] as $index=>$guestCount){
                     $child[$index]=[];
                     self::jsonRecursive($val[0],$child[$index],$guestCount,'guestCount');
                }
                continue;
            }
            if(in_array($paramsKey,['bookingPerDayPrices'])){
                $arrKey=array_key_first($val);
                foreach($data['bookingPerDayPrices'] as $index=>$perDayPrice){
                     if($arrKey!=0){
                        $paramsKey=self::getParams($arrKey,'perDayPrices');
                        $str=self::getVal($paramsKey,$type,$perDayPrice);
                        $index=$str;
                     }
                    $child[$index]=[];
                    self::jsonRecursive($val[$arrKey],$child[$index],$perDayPrice,'perDayPrices');
                }
                continue;
            }
            if(in_array($paramsKey,['guests','partnerGuests'])){
                $guestDet=self::$guests;
                $arrKey=array_key_first($val);
                foreach ($guestDet as $index=>$guest) {
                      if($arrKey=='<*>'){
                          $index+=1;
                      }
                      $child[$index]=[];
                      if($paramsKey=='partnerGuests')
                         $child[$index]['company']=null;
                      self::jsonRecursive($val[$arrKey],$child[$index],$guest,'guests');
                }
                if($paramsKey=='partnerGuests')
                   $child[$index+1]=self::$bookingDet['partnerGuest'];
              continue;
            }
            if(in_array($paramsKey,['guarantee'])){
                $guranteeDet=self::$guranteeDet;
                self::jsonRecursive($val,$child,$guranteeDet,'guranteeKeys');
                continue;
            }
            if($valIsArray){
                self::jsonRecursive($val,$child,$data,$type);
            }
       }
    }
}
/**
 * phpcs:enabled
 */
