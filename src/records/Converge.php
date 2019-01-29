<?php

namespace roundhouse\formbuilderintegrations\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

use roundhouse\formbuilder\elements\Entry;
use roundhouse\formbuilder\records\Integration;

class Converge extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%formbuilder_integrations_payments}}';
    }

    /**
     * Return entry
     *
     * @return ActiveQueryInterface
     */
    public function getEntry(): ActiveQueryInterface
    {
        return $this->hasOne(Entry::class, ['id' => 'entryId']);
    }

    /**
     * Return entry
     *
     * @return ActiveQueryInterface
     */
    public function getIntegration(): ActiveQueryInterface
    {
        return $this->hasOne(Integration::class, ['id' => 'integrationId']);
    }
}
