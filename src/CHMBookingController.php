<?php

namespace src;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use src\Models\BlockECP;
use src\Models\Booking\RdBooking;
use src\Models\Booking\RdRooms;
use src\Models\ChmLogs;
use src\Models\Partner;
use src\Traits\Booking\ChmConvert;
use src\Traits\Booking\ChmJsonBookingConvert;
use src\Traits\Booking\ChmXmlBookingConvert;
use src\Traits\Booking\AggrQuery;

class CHMBookingController
{
     use ChmConvert,ChmJsonBookingConvert,ChmXmlBookingConvert;
    /**
     * Summary of index
     * @param array{
     *   bookingId:int,
     *   bookingCode:string,
     *   partnername:string,
     *   partnerID:int,
     *   chm_api_id:string,
     *   bookingDet:array[],
     *   resId:string,
     * }$payload
     *
     */
    protected static BlockECP $channelsBlock;
    protected static array $mappedParameters=[], $bookingDet=[], $rooms=[], $guests=[],$roomGuestMapping=[],$paymentDet=[],
    $guranteeDet=[];
    protected static $roomkeys=["prt_room_id", "roomName", "roomType", "roomStatus", "bk_room_id", "bk_ratePlan_id", "prt_ratePlan_id", "cancelPolicy", "numberOfUnits", "adultCount", "childCount", "babyCount", "commission", "roomIndex", "ResGuestRPH", "amountAfterTaxes", "amountBeforeTaxes", "child_ages", "baby_ages", "bookingPerDayPrices", "roomIncServices", "bookingId", "ref_bookingcode",'guests','ratePlans','ageQualifyingCode','checkOutYmd','checkInYmd','prop_currency','hotelName','bk_prop_code',"comment",'MaxAge','MinAge','AdditionalGuestAmounts', 'partnerName','propRoomCode','total_amount_prop_currency','noOfGuests','room_currency','guestCounts','checkIn','checkOut','roomArrIndex','guestRPHs','specialRequest','specialRequest_text','specialRequest_code','mealCode','roomPrimaryGuestLastName','roomPrimaryGuestFirstName','mealDesc','defaultMeal','pricePerUnit','night'];
    protected static $bookingKeys=["hotelCode", "hotelName", "partnerId", "bk_prop_id", "bk_prop_code", "checkIn", "checkOut", "paymentMethod", "rateType", "currencyCode", "totalAdult", "totalChild", "child_ages", "baby_ages", "totalBaby", "room_type", "status", "partner_currency", "prop_currency", "partner_booking_type", "night", "vat_amount", "vat_text", "total_rooms", "total_amount_prop_currency", "total_amount_prop_currency_beforeTaxes", "total_amount_partner_currency", "comment", "price_rate_type", "rooms_default_commision", "amttaxtext", "vattexall", "Rateselect_text", "feesselect_text", "feestexall", "partnerroomIds", "partnerratePlanIds", "bk_roomIds", "bk_ratePlanIds", "partnerType", "BookingTokenCode", "bookingCode", "bookingId", "resId",'roomStays',"cardHolderName", "cardType", "cardCategory", "cardNumber", "ExpiryMonth", "ExpiryYear", "StartDayMonth", "StartDayYear", "IssueNum", "seriesCode", "vccBalance", "vccCurrencyCode", "vccActivationDate", "vccDeactivationDate", "bookingId",'guestDetails','lastName','firstName','middleName','email','phone','address','country','totalChild','guests','amountAfterTaxes','amountBeforeTaxes','bookingCodeDup','channelDet','checkOutYmd','checkInYmd','cardCodeIdentifier','cardTypeIdentifier','expireDate','channelPassword','channelUsername1','CTDcomment','providername','providercode','posName','fullName','paymentType','partnerName','singleReservationDetail','singleReservationBookingPerDayPrices','paxCount','roomIncServices','profiles','guarantee','partnerGuests'];
    protected static $bookingPerDayPricesKeys=['dateYmd','price','bk_ratePlan_id','expiryYmd','prop_currency','amountBeforeTaxes','amountAfterTaxes','cancelPolicy','bk_rate_plan_id','bk_room_id','total_amount_prop_currency','numberOfUnits','propRateCode','total_rooms','rate_name'];
    protected static $guestKeys=['lastName','firstName','middleName','email','phone','address','country','primaryindicator','ResGuestRPH','roomIndex','checkInTime','countryCode','fullName','guest_type'];
    protected static $channelkeys=['channelPassword','channelUsername1','channelUsername2','channelUsername3','channelUsername4','bk_prop_code','bookingCode','company_profile_type'];
    protected static $roomIncKeys=['IncServiceName','Inc_currencyCode','Inc_total_amount_prop_currency','Inc_room_amountAfterTaxes'];
    protected static $guestCountKeys=['count','ageQualifyingCode'];
    protected static $guranteeKeys=['guarantee_type','cardHolderName','cardNumber','ExpiryMonth','ExpiryYear','seriesCode','vccBalance'];
    public static function index($payload)
    {
        try {
            if(isset($payload['bookingDet'])){
                $bookingDet=$payload['bookingDet'];
            }else{
                $bookingAggrQuery=AggrQuery::getBoookinDetAggrQuery($payload['bookingId']);
                $bookingDet=RdBooking::raw()->aggregate($bookingAggrQuery,def_pipeline_option())->toArray();
                $bookingDet=$bookingDet[0];
                if(in_array($bookingDet['status'],[RdBooking::NEW,RdBooking::MODIFY])){
                    $roomActive=RdRooms::ACTIVE;
                    $bookingDet['rooms']=array_filter($bookingDet['rooms'],fn($room)=>$room['roomStatus']==$roomActive);
                    $bookingDet['guests']=array_filter($bookingDet['guests'],fn($guest)=>!isset($guest['deleted_at']));
                    if(isset($bookingDet['paymentDet']['deleted_at'])){
                        $bookingDet['paymentDet']=null;
                    }
                }
            }
            if(isset($payload['bookingId']))
                $bookingDet['bookingId']=(int)$payload['bookingId'];
            if(isset($payload['bookingCode']))
                $bookingDet['bookingCode']=$payload['bookingCode'];
            if(isset($payload['resId']))
               $bookingDet['resId']=$payload['resId'];
            $channelsBlock=BlockECP::where('api_id',$payload['chm_api_id'])->first();
            $partnerDet=Partner::where('partnerID',$payload['partnerID'])->project(['conn_environment'=>0,'conn_structure'=>0])->first();
            $bookingCodeEx = explode("-", $bookingDet['bookingCode']);
            $posName =  array_pop($bookingCodeEx);
            $bookingDet['posName']=$posName;
            $bookingDet['providername']=$partnerDet->providername;
            $bookingDet['providercode']=$partnerDet->providercode;
            $prop_id=$bookingDet['bk_prop_id'];
            // $prop_id=2529;
            // $prop_id=2695;
            $aggrQuery=AggrQuery::getChannelConnectQuery($prop_id,$channelsBlock->account_id);
            $connectDet=DB::collection('ConnAuthentication')->raw()->aggregate($aggrQuery,def_pipeline_option())->toArray();
            $connectDet=collect($connectDet)->mapWithKeys(fn($val)=>[$val['category']=>$val]);
            if($password=$connectDet['Password']??null){
                $pass=ssl_decrypt($password['value']);
                $channelsBlock['channelPassword']=$bookingDet['channelPassword']=$pass;
            }
            if($Username=$connectDet['Username']??null){
                $channelsBlock['channelUsername1']=$channelsBlock['channelUsername2']=$channelsBlock['channelUsername3']=$channelsBlock['channelUsername4']=$channelsBlock['bookingCode']=$bookingDet['channelUsername1']=$Username['value'];
            }
            $bookingDet['channelId']=$channelsBlock->account_id;
            $channelsBlock['bk_prop_code']=$bookingDet['bk_prop_code'];
            $channelsBlock['bk_prop_id']=$bookingDet['bk_prop_id'];
            $channelsBlock['company_profile_type']="4";
            $carbonTime = Carbon::instance($bookingDet['created_at']->toDateTime());
            $checkInYmd=Carbon::parse($bookingDet['checkIn'])->format('Y-m-d');
            $checkOutYmd=Carbon::parse($bookingDet['checkOut'])->format('Y-m-d');
            $bookingDet['booking_created_at']=$carbonTime;
            $bookingDet['booking_updated_at']=Carbon::instance($bookingDet['updated_at']->toDateTime());
            $rooms=$bookingDet['rooms'];
            $guests=$bookingDet['guests'];
            $bookingDet['amountBeforeTaxes']=$bookingDet['total_amount_prop_currency_beforeTaxes'];
            $bookingDet['amountAfterTaxes']=$bookingDet['total_amount_prop_currency'];
            $bookingDet['bookingCodeDup']=$bookingDet['bookingCode'];
            $bookingDet['CTDcomment']=$bookingDet['comment'];
            $bookingDet['checkInYmd']=$checkInYmd;
            $bookingDet['checkOutYmd']=$checkOutYmd;
            $bookingDet['partnerName']=$partnerDet->name;
            $bookingDet['partnerPosName']="RDK".$partnerDet->partnerID.'_'.$partnerDet->name;
            $bookingDet['paxCount']=$bookingDet['totalBaby']+$bookingDet['totalChild']+$bookingDet['totalAdult'];
            $bookingDet['arrComments']=[$bookingDet['comment']];
            self::transformRooms($rooms,$bookingDet,$guests);
            self::transfromGuests($guests,$rooms,$bookingDet);
            self::$rooms=$rooms;
            self::$guests=$guests;
            $bookingDet['singleRoom']=$rooms[0];
            $patnerGuest= [
                "type" => "travelagent",
                "id" => $partnerDet->partnerID,
                "idContext" =>'RDK'.$partnerDet->partnerID,
                "customer" => null,
                "company" => [
                    "name" => $partnerDet->name,
                    "codeContext" => null,
                    "code" => null,
                    "telephone" => $partnerDet->contactno,
                    "email" => $partnerDet->email ,
                    "address" => array(
                        "addressLine" => $partnerDet->address1,
                        "city" => $partnerDet->city ,
                        "countryCode" => $partnerDet->countryCode,
                        "stateCode" => null,
                        "postCode" => $partnerDet->pinCode,
                    )
                ]
            ];
            $bookingDet['partnerGuest']=$patnerGuest;
            $paymentDet=$bookingDet['paymentDet']??null;
            unset($bookingDet['rooms'],$bookingDet['guests'],$bookingDet['paymentDet']);
            $bookingDet=array_merge($bookingDet,$guests[0]);
            $bookingDet['paymentType']=1;
            if($paymentDet){
                $conn_ref=DB::collection('connectivity_references')->where('referenceDescription',$paymentDet['cardType'])->first();
                if($conn_ref){
                    $ref=$conn_ref['referenceId'];
                    $bookingDet['cardCodeIdentifier']=$ref;
                    $bookingDet['cardTypeIdentifier']=$ref[0]??'';
                }
                if(!empty($paymentDet['ExpiryMonth'])){
                    $bookingDet['expireDate']=$paymentDet['ExpiryMonth'].'/'.$paymentDet['ExpiryYear'];
                }
                $bookingDet['vccBalance']??=0;
                $bookingDet['paymentType']=4;
            }else{
                $paymentDet['cardHolderName']='Unknown';
                $paymentDet['cardNumber']='00';
                $paymentDet['ExpiryMonth']='00';
                $paymentDet['ExpiryYear']='00';
                $paymentDet['vccBalance']='00';
                $paymentDet['CardType']='';
                $paymentDet['ExpireDate']='00';
                $paymentDet['seriesCode']='000';
            }
            $bookingDet=array_merge($bookingDet,$paymentDet);
            $guranteeDet=$paymentDet;
            $guranteeDet['guarantee_type']='guarantee';
            $bookingDet['roomIncServices']=collect($rooms)->mapWithKeys(fn($val)=>[$val['bk_room_id']=>$val['roomIncServices']])->toArray();
            self::$bookingDet=$bookingDet;
            $roomGuestMapping=collect($guests)->groupBy('bk_room_id')->toArray();
            self::$roomGuestMapping=$roomGuestMapping;
            self::$guranteeDet=$guranteeDet;
            $template=$channelsBlock->req;
            $params=[];
            $bookingKeys=self::$bookingKeys;
            $roomkeys=self::$roomkeys;
            $bookingPerDayPricesKeys=self::$bookingPerDayPricesKeys;
            $guestKeys=self::$guestKeys;
            $channelKeys=self::$channelkeys;
            $guestCountKeys=self::$guestCountKeys;
            $roomIncKeys=self::$roomIncKeys;
            $guaranteeKeys=self::$guranteeKeys;
            foreach ($channelsBlock->format_parameters as $param) {
                 if($param['bakuun']==null)continue;
                 $bakuun=$param['bakuun'];
                 $name=$param['name'];
                 if(in_array($bakuun,$bookingKeys))
                    $params['booking'][$name]=$bakuun;
                 if(in_array($bakuun,$roomkeys))
                    $params['room'][$name]=$bakuun;
                 if(in_array($bakuun,$bookingPerDayPricesKeys))
                    $params['perDayPrices'][$name]=$bakuun;
                 if(in_array($bakuun,$guestKeys))
                    $params['guests'][$name]=$bakuun;
                 if(in_array($bakuun,$channelKeys))
                    $params['channel'][$name]=$bakuun;
                 if(in_array($bakuun,$guestCountKeys))
                    $params['guestCount'][$name]=$bakuun;
                 if(in_array($bakuun,$roomIncKeys))
                    $params['incKeys'][$name]=$bakuun;
                 if(in_array($bakuun,$guaranteeKeys))
                    $params['guranteeKeys'][$name]=$bakuun;
                 if($param['type']=='Hardcoded')
                    $params['defaultVals'][$name]=$bakuun;
            }
            // dd($params);
            self::$mappedParameters=$params;
            self::$channelsBlock=$channelsBlock;
            if($channelsBlock->type=='json')
            $chmfomatTemplete= self::processJson($template);
            else
            $chmfomatTemplete= self::precessXmlV2();
            return self::executeChmCall($chmfomatTemplete);

        } catch (Exception $e) {
            Log::error('Error in CHMBookingController: ' . $e->getMessage(), ['exception' => $e]);
            return ['success' => false, 'message' => 'Something went wrong', 'status' => 500];
        }
    }
    static function executeChmCall($payload) {
        try {
                $chmAggrQuery = AggrQuery::getCustomEndpoint(self::$channelsBlock->account_id);
                $webhookdetail = DB::collection('webhooks')->raw()->aggregate($chmAggrQuery, def_pipeline_option())->toArray();
                $chmresponse = $chm_Username =  $chm_Password= '';
                $allSettings = collect($webhookdetail)->pluck('settings')->flatten(1);
                $chm_endpoint = $allSettings->first(fn($setting) => $setting['category'] === 'Custom' && $setting['name'] === 'chm_endpoint')['value'] ?? '';
                $chm_Username = $allSettings->first(fn($setting) => $setting['category'] === 'Username' && $setting['name'] === 'Username')['value'] ?? '';
                $chm_Password = $allSettings->first(fn($setting) => $setting['category'] === 'Password' && $setting['name'] === 'Password')['value'] ?? '';
                if (empty($chm_endpoint)) {
                    $chmresponse ='CHM endpoint is missing.';
                    self::connectivity_logs($payload, $chmresponse, 413, $chm_endpoint);
                    return 413;
                }
                $httpClient = Http::withOptions([]);
                $reqPayload=$payload;
                if (!empty($chm_Username) && !empty($chm_Password)) {
                    $httpClient = $httpClient->withBasicAuth($chm_Username, $chm_Password);
                }
                if(is_string($payload))
                {
                   $reqPayload=['body'=>$payload];
                }
                $chmresponse = $httpClient->withoutVerifying()->send('POST', $chm_endpoint, $reqPayload);
                $responseBody = $chmresponse->body();
                $statuscode = self::checkSuccessKey($responseBody) ? 200 : 413;
                self::connectivity_logs($payload, $responseBody, $statuscode, $chm_endpoint);

                return $statuscode;

            } catch (\Exception $e) {
                Log::error('Exception occurred: ' . $e);
                return 413;
            }
    }

    static function connectivity_logs($payload, $resp, $statusCode, $CallerURL)
    {
        $channel_user = (object) DB::table('channel_user')->where('account_id', self::$channelsBlock->account_id)->get(['comp_name'])->first();
        $request=Request::capture();
        $data = [
            "CallerURL" => $CallerURL,
            "CallerIP" => $request->ip(),
            "Request" => JsontoString($payload),
            "Response" => JsontoString($resp),
            "APIMethod" => "POST",
            "Status" => $statusCode,
            "Category" => 'booking',
            "ApiId" => self::$channelsBlock->api_id ?? null,
            "PropCode" => self::$channelsBlock->bk_prop_code ?? null,
            "PropId" =>self::$channelsBlock->bk_prop_id ?? null,
            "createdBy" =>self::$channelsBlock->account_id ?? null,
            "CallerCompany" => $channel_user->comp_name??null,
        ];
        ChmLogs::create($data);
    }
    static function checkSuccessKey($input) {
        $keywords = ['success', 'Success', 'SUCCESS', 'isSuccess'];
        $statusKey = 'status';
        $statusValue = 'true';
        if (is_string($input)) {   //xml or soap
            return array_reduce($keywords, function ($found, $keyword) use ($input) {
                return $found || stripos($input, $keyword) !== false;
            }, false);
        }
        else if (is_array($input)) {   //json
            $foundSuccess = array_reduce($keywords, function ($found, $keyword) use ($input) {
                return $found || array_key_exists($keyword, $input);
            }, false);

            $foundFail = isset($input[$statusKey]) && strtolower($input[$statusKey]) === $statusValue;
            return $foundSuccess || $foundFail;
        }
        return false;
    }

   
    static function getVal($paramsKey,$type='booking',$data=[])
    {
        $attr_val='NA';
        switch ($type) {
            case 'booking':
                $attr_val=self::$bookingDet[$paramsKey]??'';
                break;
            default :
                $attr_val=$data[$paramsKey]??'';
                break;
        }
        return $attr_val;
    }

    static function getParams($xmlKey,$type='booking')
    {
        $parm=self::$mappedParameters[$type][$xmlKey]??'';
        if(empty($parm))
            $parm=self::getDefaulVal($xmlKey);
        return $parm;
    }
    static function getDefaulVal($key)
    {
        $val=self::$mappedParameters['defaultVals'][$key]??'';
        if(!empty($val)){
            $val=self::transformDefaultValue($val);
        }
        return $val;
    }
}
