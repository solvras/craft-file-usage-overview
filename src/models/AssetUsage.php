<?php

namespace solvras\craftcraftfileusageoverview\models;

use Craft;
use craft\base\Model;

/**
 * Asset Usage model
 */
class AssetUsage extends Model
{
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rules() : array
    {
        return [
            [['assetId', 'entryId'], 'required'],
        ];
    }
}
