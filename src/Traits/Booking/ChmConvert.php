<?php

namespace src\Traits\Booking;


use src\Models\Booking\RdRooms;
use Carbon\Carbon;
use Illuminate\Support\Str;
use src\CommonHelpers;

trait ChmConvert
{
   
    static function getValueFromDefault($defaultValue,$currentValue){
        if(empty($defaultValue))
            return $currentValue;
        switch ($defaultValue) {
            case 'Refundable Rate':
                  if(in_array($currentValue,['Refundable','Non Refundable']))
                       return $currentValue.' Rate';
                break;
        }
        return $currentValue;
    }
    static function getAgeQualificationCode($room)
    {
          if($room['childCount']>0)
               return "8";
          if($room['babyCount']>0)
               return "7";
          return "10";
    }
    static function transfromGuests(&$guests,$rooms,$bookingDet)
    {
        $roomIndex=collect($rooms)->mapWithKeys(fn($val)=>[$val['bk_room_id']=>$val['roomIndex']]);
        $checkInTime=Carbon::parse($bookingDet['checkIn'])->format('H:i:s');
        foreach ($guests as $index=>&$guest) {
            $guest['roomIndex']=$roomIndex[$guest['bk_room_id']];
            // $guest['primaryindicator']=$guest['ResGuestRPH']==1?"1":"0";
            $guest['countryCode']=CommonHelpers::country_to_code($guest['country']);
            $guest['checkInTime']=$checkInTime;
            $guest['company_profile_type']='4';
            $guest['guest_type']='guest';
            $guest['fullName']=$guest['firstName'].$guest['middleName'].$guest['lastName'];
            $guest['partnerGuestIndex']=$index+1;
        }
    }
    static function transformRooms(&$rooms,$bookingDet,$guests)
    {
        $roomGuets=collect($guests)->groupBy('bk_room_id')->toArray();
        $checkInYmd=Carbon::parse($bookingDet['checkIn'])->format('Y-m-d');
        $checkOutYmd=Carbon::parse($bookingDet['checkOut'])->format('Y-m-d');
        foreach ($rooms as $key=> &$room) {
            $cancle_room_type=$room['prop_cancelationType']??0;
            $rate_name=$cancle_room_type == 1?'Refundable Rate':"Standard Rate";
            $room['rate_name']=$rate_name;
            $room['bookingPerDayPrices']=array_map(function($perDayPrice) use($bookingDet,$room){
               $nxtday=Carbon::parse($perDayPrice['dateYmd'])->addDay();
               $perDayPrice['expiryYmd']=$nxtday->toDateString();
               $perDayPrice['prop_currency']=$bookingDet['prop_currency'];
               $perDayPrice['amountBeforeTaxes']=$perDayPrice['price'];
               $perDayPrice['amountAfterTaxes']=$perDayPrice['price'];
               $perDayPrice['rate_name']=$room['rate_name'];
               $perDayPrice['total_amount_prop_currency']=$perDayPrice['price'];
               $perDayPrice['bk_ratePlan_id']=$room['bk_ratePlan_id'];
               $perDayPrice['bk_room_id']=$room['bk_room_id'];
               $perDayPrice['numberOfUnits']=$room['numberOfUnits'];
               $perDayPrice['total_rooms']=$bookingDet['total_rooms'];
               $perDayPrice['propRateCode']=Str::before($room['bk_ratePlan_id'],'.');
               if(!isset($perDayPrice['cancelPolicy']))
                  $perDayPrice['cancelPolicy']=$room['cancelPolicy'];

               return $perDayPrice;
            },$room['bookingPerDayPrices']);
            $room['currencyCode']=$bookingDet['currencyCode'];
            if($room['bk_ratePlan_id']){
               $room['ratePlans']=[[
                   'bk_ratePlan_id'=>$room['bk_ratePlan_id'],
                   'cancelPolicy'=>$room['cancelPolicy'],
               ]];
               $room['propRateCode']=Str::before($room['bk_ratePlan_id'],'.');
            }else{
               foreach ($room['bookingPerDayPrices'] as $perDayprice) {
                   $room['ratePlans'][]=[
                       'bk_ratePlan_id'=>$perDayprice['bk_ratePlan_id'],
                       'cancelPolicy'=>$perDayprice['cancelPolicy'],
                   ];
               }
            };
            $default_no_meal =   $room['default_no_meal']??'';
            $default_breakfast = $room['default_breakfast']??'';
            $default_halfboard = $room['default_halfboard']??'';
            $default_fullboard = $room['default_fullboard']??'';
            $mealdesc=$defaultbreakfast='test';
            if ($default_no_meal == 1) {
                $defaultbreakfast = '1';
                $mealdesc = 'Default_breakfast';
            }
            if ($default_breakfast == 1) {
                $defaultbreakfast = '2';
                $mealdesc = 'Default_breakfast';
            }
            if ($default_halfboard == 1) {
                $defaultbreakfast = '8';
                $mealdesc = 'Default_halfboard';
            }
            if ($default_fullboard == 1) {
                $defaultbreakfast = '9';
                $mealdesc = 'Default_fullboard';
            }
            $room['mealDesc']=$mealdesc;
            $room['defaultMeal']=$defaultbreakfast;
            $room['mealCode']=RdRooms::getMealCode($room);
            $room['prop_currency']=$bookingDet['prop_currency'];
            $room['bk_prop_code']=$bookingDet['bk_prop_code'];
            $room['hotelName']=$bookingDet['hotelName'];
            $room['comment']=$bookingDet['comment'];
            $roomGuests=collect($roomGuets[$room['bk_room_id']]);
            $room['guestRPHs']=$roomGuests->pluck('ResGuestRPH')->toArray();
            $roomFirstGuest=$roomGuests->where('ResGuestRPH',$room['roomIndex'])->first();
            $room['roomPrimaryGuestFirstName']=$roomFirstGuest['firstName'];
            $room['roomPrimaryGuestLastName']=$roomFirstGuest['lastName'];
            $room['partnerName']=$bookingDet['partnerName'];
            $room['ageQualifyingCode']=self::getAgeQualificationCode($room);
            $room['checkInYmd']=$checkInYmd;
            $room['specialRequest_text']=$bookingDet['comment'];
            $room['specialRequest_code']=$bookingDet['comment'];
            $room['checkOutYmd']=$checkOutYmd;
            $room['total_amount_prop_currency']=$bookingDet['total_amount_prop_currency'];
            $room['propRoomCode']=Str::before($room['bk_room_id'],'.');
            $room['noOfGuests']= $room['adultCount']+$room['childCount']+$room['babyCount'];
            $room['night']=$bookingDet['night'];
            $nightNum = (float)$room['night'];
            if (!empty($nightNum)) {
                $NightroomRate = $room['amountAfterTaxes'] / $nightNum;
            }else {
                $NightroomRate = $room['amountAfterTaxes'];
            }
            $room['pricePerUnit']=$NightroomRate;
            $room['room_currency']= $bookingDet['prop_currency'];
            $room['checkOut']=$bookingDet['checkOut'];
            $room['checkIn']=$bookingDet['checkIn'];
            $room['roomArrIndex']=$key;
            $guestCounts=[];
            $guestCounts[]=[
                'ageQualifyingCode'=>10,
                'count'=>$room['adultCount'],
                'codeText'=>'Adult'
            ];
            $guestCounts[]=[
                'ageQualifyingCode'=>8,
                'count'=>$room['childCount'],
                'codeText'=>'Child'
            ];
            if($room['babyCount'])
            $guestCounts[]=[
                'ageQualifyingCode'=>7,
                'count'=>$room['babyCount'],
                'codeText'=>'Baby'
            ];
            $room['guestCounts']=$guestCounts;
            foreach ($room['roomIncServices'] as &$service) {
                 $service['Inc_total_amount_prop_currency']=$room['total_amount_prop_currency'];
                 $service['Inc_currencyCode']=$bookingDet['prop_currency'];
                 $service['Inc_room_amountAfterTaxes']=$room['amountAfterTaxes'];
            }
            $child_ages=$room['child_ages'];
            if(!empty($child_ages)){
                sort($child_ages,SORT_NUMERIC);
                $room['MinAge']=$child_ages[0];
                $room['MaxAge']=last($child_ages);
            }
       }
    }
    static function isEmpty($str){
          return is_null($str) || $str=='';
    }

    static function transformDefaultValue($val)
    {
        if($val=='null') return null;
        if(str_starts_with($val,'now(')){
            $inside = substr($val, strpos($val, '(') + 1, -1);
            $now='';
            $now=Carbon::now();
            try {
                $now=$now->format($inside);
            } catch (\Throwable $th) {
                $now=$now->toDateTimeString();
            }
            return $now;
        }
        $actions=explode('|',$val);
        if(count($actions)==3){
            $status=self::$bookingDet['status']-1;
            return $actions[$status];
        }
        if(str_starts_with($val,'created_at(')){
             $created_at=self::$bookingDet['booking_created_at'];
             $inside = substr($val, strpos($val, '(') + 1, -1);
             try {
                $now=$created_at->format($inside);
            } catch (\Throwable $th) {
                $now=$created_at->toDateTimeString();
            }
            return $now;
        }
        if(str_starts_with($val,'updated_at(')){
             $date=self::$bookingDet['booking_updated_at'];
             $inside = substr($val, strpos($val, '(') + 1, -1);
             try {
                $now=$date->format($inside);
            } catch (\Throwable $th) {
                $now=$date->toDateTimeString();
            }
            return $now;
        }
        return $val;
    }
}
