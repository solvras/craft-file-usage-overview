<?php

namespace solvras\craftcraftfileusageoverview\records;

use Craft;
use craft\db\ActiveRecord;

/**
 * Assets Usage record
 */
class AssetsUsage extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%file-usage-overview_redactor-relations}}';
    }
}
