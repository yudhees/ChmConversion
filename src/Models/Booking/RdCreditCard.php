<?php

namespace Yudhees\ChmConversion\Models\Booking;

use Yudhees\ChmConversion\Models\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class RdCreditCard extends Model
{
    use SoftDeletes;
    protected $collection='RDBooking_creditcard',$guarded=[];
}
