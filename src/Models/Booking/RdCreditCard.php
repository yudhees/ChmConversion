<?php

namespace src\Models\Booking;

use src\Models\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class RdCreditCard extends Model
{
    use SoftDeletes;
    protected $collection='RDBooking_creditcard',$guarded=[];
}
