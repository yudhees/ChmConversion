<?php

namespace Yudhees\ChmConversion\Models\Booking;

use Yudhees\ChmConversion\Models\Model;

class RdRooms extends Model
{
    /**
     * roomStatus 1 means booked 2 means cancel
     *
     */
    public const ACTIVE = 1,CANCEL = 2;

    protected $collection='RDBooking_room',$guarded=[];
    public static function getMealCode(array $data): string
    {
        $default_no_meal = $data['default_no_meal']??'';
        $default_breakfast = $data['default_breakfast']??'';
        $default_halfboard = $data['default_halfboard']??'';
        $default_fullboard = $data['default_fullboard']??'';
        if ($default_no_meal == 0) {
            if ($default_breakfast == 1) {
                $mealcode = 'BB';
            } elseif ($default_halfboard == 1) {
                $mealcode = 'HB';
            } elseif ($default_fullboard == 1) {
                $mealcode = 'FB';
            } else {
                $mealcode = 'RO';
            }
        } else {
            $mealcode = 'RO';
        }
        return $mealcode;
    }

}
