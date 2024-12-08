<?php

namespace src\Models\Booking;

use src\Models\Model;


class RdBooking extends Model
{
    /**
     * partner_booking_type 1 for perbooking 2 for confirm whcih is live property, 3 and 4 for sandbox proerty
     */

    protected $collection = 'RDBooking';
    protected $guarded = [];

    public const NEW = 1,MODIFY = 2,CANCEL = 3;

    public static function getStatus(string $status)
    {
        return match (strtolower($status)) {
            'new' => self::NEW,
            'modify' => self::MODIFY,
            'cancel' => self::CANCEL,
        };
    }
       public function guests()
    {
        return $this->hasMany(RdGuests::class, 'bookingId', 'bookingId');
    }
    public function rooms()
    {
        return $this->hasMany(RdRooms::class, 'bookingId', 'bookingId');
    }
    public function creditCard()
    {
        return $this->hasOne(RdCreditCard::class, 'bookingId', 'bookingId');
    }
    public function bookingRequest()
    {
        return $this->hasOne(RDBooking_request::class, 'bookingId', 'bookingId');
    }
}
