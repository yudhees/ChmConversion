<?php

namespace Yudhees\ChmConversion\Models;

use Yudhees\ChmConversion\Models\Model;

class ChmLogs extends Model
{
    const UPDATED_AT = null;
    protected $table = 'chm_logs';
    protected $guarded = [    ];

    public function save(array $options = [])
    {
        // Remove updated_at from attributes before saving
        unset($this->updated_at);

        return parent::save($options);
    }
}
