<?php

namespace roundhouse\formbuilderintegrations\Integrations\Payment\Converge;

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

class ConvergeValidation extends Component
{
    protected $parameters;
    protected $currencies;
    
    public $zeroAmountAllowed = false;
    public $negativeAmountAllowed = false;

    // Public Methods
    // =========================================================================

//    public function getMoney()
//    {
//        if (Converge::instance()->amountValue !== null && is_integer(Converge::instance()->amountValue)) {
//            $money = new Money(Converge::instance()->amountValue, Converge::instance()->currency);
//        } else {
//            Craft::dd(new CurrencyList($this->getCurrencies()));
//            $moneyParser = new DecimalMoneyParser($this->getCurrencies());
//            Craft::dd($moneyParser);
//            $number = Number::fromString(Converge::instance()->amountValue);
//            $decimal_count = strlen($number->getFractionalPart());
//            $subunit = $this->getCurrencies()->subunitFor(Converge::instance()->currency);
//
//            Craft::dd($moneyParser);
//            if ($decimal_count > $subunit) {
//                throw new InvalidRequestException('Amount precision is too high for currency.');
//            }
//            $money = $moneyParser->parse((string) $number, Converge::instance()->currency);
//        }
//
//        return $money;
//    }

    // Protected Methods
    // =========================================================================

//    /**
//     * @return ISOCurrencies
//     */
//    protected function getCurrencies()
//    {
//
//        if ($this->currencies === null) {
//            $this->currencies = $this->loadCurrencies();
//        }
//
//        return $this->currencies;
//    }

//    private function loadCurrencies()
//    {
//        $file = CRAFT_VENDOR_PATH.'/roundhouse/form-builder-integrations/resources/currency.php';
//
//        if (file_exists($file)) {
//            return require $file;
//        }
//
//        throw new \RuntimeException('Failed to load currency ISO codes.');
//    }

    public function validateLuhn($number)
    {
        $str = '';
        foreach (array_reverse(str_split($number)) as $i => $c) {
            $str .= $i % 2 ? $c * 2 : $c;
        }

        return array_sum(str_split($str)) % 10 === 0;
    }
}