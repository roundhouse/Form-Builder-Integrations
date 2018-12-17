<?php

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Db;
use yii\db\Schema;
use craft\helpers\Json;

class CreditCardNumberField extends Field
{
    public static function displayName(): string
    {
        return Craft::t('form-builder-integrations', 'Credit Card');
    }

}