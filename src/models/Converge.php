<?php

namespace roundhouse\formbuilderintegrations\models;

use craft\base\Model;

class Converge extends Model
{
    // Public Properties
    // =========================================================================

    public $id;
    public $integrationId;
    public $entryId;
    public $amount;
    public $currency;
    public $last4;
    public $status;
    public $metadata;
    public $errorCode;
    public $errorMessage;
}