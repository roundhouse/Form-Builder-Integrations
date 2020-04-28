<?php

namespace roundhouse\formbuilderintegrations\Integrations\Payment\Taluspay;

use Craft;
use craft\base\Component;
use Money\Currencies\CurrencyList;
use roundhouse\formbuilderintegrations\Integrations\Payment\Converge;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Money;
use Money\Number;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Parser\DecimalMoneyParser;
use Omnipay\Common\Exception\InvalidRequestException;

class TaluspayValidation extends Component
{
    protected $parameters;
    protected $currencies;
    
    public $zeroAmountAllowed = false;
    public $negativeAmountAllowed = false;

    // Public Methods
    // =========================================================================


    // Protected Methods
    // =========================================================================


    public function validateLuhn($number)
    {
        $str = '';
        foreach (array_reverse(str_split($number)) as $i => $c) {
            $str .= $i % 2 ? $c * 2 : $c;
        }

        return array_sum(str_split($str)) % 10 === 0;
    }
}