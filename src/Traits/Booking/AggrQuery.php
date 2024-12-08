<?php

namespace Yudhees\ChmConversion\Traits\Booking;

trait AggrQuery
{
 

    public static function getBoookinDetAggrQuery(int $bookingId){
        $project=[
            'payments'=>0,'_id'=>0
        ];
        return [
            ['$match' => ['bookingId' => $bookingId]],
            ['$lookup' =>
                 ['from' => 'RDBooking_room',
                 'localField' => 'bookingId',
                 'foreignField' => 'bookingId',
                //  ['$match' => ['roomStatus' => 1]]
                 'pipeline' => [['$sort'=>['roomIndex'=>1]],['$project'=>$project]],
                 'as' => 'rooms'
                 ]
            ],
            ['$lookup' =>
                 ['from' => 'RDBooking_guest',
                 'localField' => 'bookingId',
                 'foreignField' => 'bookingId',
                //  ['$match' => ['deleted_at' => null]]
                 'pipeline' => [['$sort'=>['ResGuestRPH'=>1]],['$project'=>$project]],
                 'as' => 'guests'
                 ]
            ],
            ['$lookup'=>
                ['from'=>'RDBooking_creditcard',
                'localField'=>'bookingId',
                'foreignField' => 'bookingId',
                // ['$match' => ['deleted_at' => null]]
                'pipeline' => [['$project'=>$project]],
                'as'=>'payments'
                ]
            ],
            ['$addFields'=>['paymentDet'=>['$first'=>'$payments']]],
            ['$project'=>$project]
        ];
    }
    static function getChannelConnectQuery(int $propId,string $account_id){
        return [
            ['$match' => ['account_id' => $account_id]],
            ['$lookup' => [
                'from' => 'bk_channel_connect_auth',
                'let' => ['conn_id' => ['$toString' => '$_id']],
                'pipeline' => [
                    ['$match' => ['$expr' =>
                        ['$and' => [
                            ['$eq' => ['$connId', '$$conn_id']],
                            ['$eq' => ['$propId', $propId]],
                            ['$eq' => ['$channelId', $account_id]]
                            ]
                        ]
                      ]
                    ],
                    ['$project' => ['created_at' => 0, 'updated_at' => 0]]
                 ],
                'as' => 'channel_connect']
            ],
            ['$addFields' => ['channel_connect' => ['$first' => '$channel_connect']]],
            ['$project' => ['category' => 1, 'account_id' => 1, 'channel_connect' => 1]],
            ['$match'=>['channel_connect'=>['$ne'=>null]]],
            ['$addFields' => ['value' => '$channel_connect.value']],
        ];
    }
    static function getChannelEndpointAggrQuery($api_id)
    {
        return[
            ['$match' => ['api_id' => $api_id,'method'=>'Post']],
            ['$project' => ['endpoint' => 1, 'account_id' => 1, 'api_id' => 1, 'method' => 1]],
            ['$addFields' => ['_id' => ['$toString' => '$_id']]],
            ['$lookup' => [
                'from' => 'webhooks',
                'localField' => '_id',
                'foreignField' => 'block_id',
                'pipeline' => [
                    ['$addFields' => ['_id' => ['$toString' => '$_id']]],
                    ['$lookup' => [
                        'from' => 'webhook_settings',
                        'localField' => '_id',
                        'foreignField' => 'webhook_id',
                        'pipeline' => [
                            ['$match' => ['category' => 'Custom', 'name' => 'ApiType', 'value' => 'Cancel']],
                            ['$project' => ['name' => 1, 'value' => 1, 'webhook_id' => 1]]
                        ],
                        'as' => 'setting'
                        ]
                    ],
                    ['$addFields' => ['setting' => ['$first' => '$setting']]],
                    ['$project' => ['category' => 1, 'account_id' => 1, 'setting' => 1]]
                ],
               'as' => 'webhoooks']
            ],
            ['$addFields' => [
                'webhoooks' => ['$first' => '$webhoooks'],
                'ApiType' => ['$first' => '$webhoooks.setting.value']
                ]
            ],
            ['$project' => ['webhoooks' => 0]]
        ];
    }
    static function getCustomEndpoint($accountId)
    {
        return [['$match' => ['account_id' => $accountId]], ['$lookup' => ['from' => 'webhook_settings', 'let' => ['webhook_id' => ['$toString' => '$_id']], 'pipeline' => [['$match' => ['$expr' => ['$and' => [['$eq' => ['$webhook_id', '$$webhook_id']],
         /* ['$eq' => ['$name', 'chm_endpoint']], ['$eq' => ['$category', 'Custom']] */
        ]]]]], 'as' => 'settings']],/*  ['$addFields' => ['settings' => ['$first' => '$settings']]], */ ['$match' => ['settings' => ['$ne' => null]]], ['$project' => ['settings' => 1]]];
    }
}
/**
 * phpcs:enabled
 */




