<?php

namespace roundhouse\formbuilderintegrations\Integrations\Payment;

use Craft;
use craft\base\Component;
use craft\web\View;
use yii\base\Exception;
use roundhouse\formbuilder\FormBuilder;
use roundhouse\formbuilderintegrations\Integrations\Payment\Converge\ConvergeValidation;

use Omnipay\Omnipay;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Helper;
use Money\Currency;

class Converge extends Component
{
    public $amount;
    public $currency;
    public $number;
    public $cvc;
    public $expMonth;
    public $expYear;
    public $amountValue;

    public $customer = [];
    public $card;

    public $entry;
    public $form;
    public $integration;
    public $integrationModel;
    public $settings;

    // Public Methods
    // =========================================================================

    public function prepare($form, $entry, $converge)
    {
        $settings = [
            'merchantId'    => $converge['integration']->settings['merchantId'],
            'merchantKey'   => $converge['integration']->settings['merchantKey'],
            'merchantPin'   => $converge['integration']->settings['merchantPin'],
            'currency'      => $converge['integration']->settings['currency'],
            'testMode'      => $converge['integration']->settings['testMode'],
        ];

        $this->settings         = $settings;
        $this->entry            = $entry;
        $this->form             = $form;
        $this->integration      = $converge;
        $this->integrationModel = $converge['integration'];

        $this->buildCustomer();
        $this->buildCreditCard();

        $this->currency     = $this->buildCurrency();
        $this->amountValue  = (isset($converge['ccAmountField']) && $this->integration['ccAmountField'] != '' ? $this->entry->{$this->integration['ccAmountField']} : null);
//        $this->amount       = $this->buildAmount();
        
        // Validate Entry
        if ($entry->hasErrors()) {
            return $entry;
        }

        $gateway = $this->buildGateway();

        // Transaction
        $transactionParams = [
            'amount' => $this->amountValue,
            'currency' => $this->currency,
            'card' => $this->card
        ];
        switch ($this->integration['transactionType']) {
            case 'ccsale':
                $transaction = $gateway->purchase($transactionParams);
                break;
            case 'ccauthonly':
                $transaction = $gateway->purchase($transactionParams);
                break;
            default:
                return null;
                break;
        }

        $response = $transaction->send();

        if ($response->isSuccessful()) {
            Craft::$app->session->setFlash('paymentSuccess', $response->getMessage());
        } else {
            if ($response->getCode() === '4007') {
                $field = Craft::$app->fields->getFieldByHandle($converge['ccCvcField']);
                $entry->addError($converge['ccCvcField'], FormBuilder::t($field->name . ' is required'));
            }
            Craft::$app->session->setFlash('paymentFailed', $response->getMessage());
        }

        return $entry;
    }

    // Private Methods
    // =========================================================================

    /**
     * Build Customer
     */
    private function buildCustomer()
    {
        if (isset($this->integration['firstNameField']) && $this->integration['firstNameField'] != '') {
            $this->customer['firstName'] = $this->entry->{$this->integration['firstNameField']};
        }

        if (isset($this->integration['lastNameField']) && $this->integration['lastNameField'] != '') {
            $this->customer['lastName'] = $this->entry->{$this->integration['lastNameField']};
        }

        if (isset($this->integration['emailField']) && $this->integration['emailField'] != '') {
            $this->customer['email'] = $this->entry->{$this->integration['emailField']};
        }

        if (isset($this->integration['phoneField']) && $this->integration['phoneField'] != '') {
            $this->customer['phone'] = $this->entry->{$this->integration['phoneField']};
        }
    }

    /**
     * Build Credit Card
     *
     * @return mixed
     */
    private function buildCreditCard()
    {
        $this->card = new CreditCard();

        if (isset($this->integration['ccExpirationMonthField']) && $this->entry->{$this->integration['ccExpirationMonthField']} != '') {
            $this->card->setExpiryMonth($this->entry->{$this->integration['ccExpirationMonthField']});
        } else {
            $this->entry->addError($this->integration['ccExpirationMonthField'], FormBuilder::t('Expiration month is required'));
        }

        if (isset($this->integration['ccExpirationYearField']) && $this->entry->{$this->integration['ccExpirationYearField']} != '') {
            $this->card->setExpiryYear($this->entry->{$this->integration['ccExpirationYearField']});
        } else {
            $this->entry->addError($this->integration['ccExpirationYearField'], FormBuilder::t('Expiration year is required'));
        }

        if (isset($this->integration['ccNumberField']) && $this->entry->{$this->integration['ccNumberField']} != '') {
            $cardNumber = $this->entry->{$this->integration['ccNumberField']};

            if (!Helper::validateLuhn($cardNumber)) {
                $this->entry->addError($this->integration['ccNumberField'], FormBuilder::t('Card number is invalid'));
            }

            if (!is_null($cardNumber) && !preg_match('/^\d{12,19}$/i', $cardNumber)) {
                $this->entry->addError($this->integration['ccNumberField'], FormBuilder::t('Card number should have 12 to 19 digits'));
            }

            if ($this->card->getExpiryDate('Ym') < gmdate('Ym')) {
                $this->entry->addError($this->integration['ccNumberField'], FormBuilder::t('Card has expired'));
            }

            $this->card->setNumber($this->entry->{$this->integration['ccNumberField']});
            
        } else {
            $this->entry->addError($this->integration['ccNumberField'], FormBuilder::t('Card number is required'));
        }

        if (isset($this->integration['ccCvcField']) && $this->entry->{$this->integration['ccCvcField']} != '') {
            $this->card->setCvv($this->entry->{$this->integration['ccCvcField']});
        }

        if (isset($this->customer['firstName']) && $this->customer['firstName'] != '') {
            $this->card->setFirstName($this->customer['firstName']);
        }

        if (isset($this->customer['lastName']) && $this->customer['lastName'] != '') {
            $this->card->setLastName($this->customer['lastName']);
        }

        if (isset($this->customer['email']) && $this->customer['email'] != '') {
            $this->card->setEmail($this->customer['email']);
        }

        if (isset($this->customer['phone']) && $this->customer['phone'] != '') {
            $this->card->setPhone($this->customer['phone']);
        }

        if ($this->entry->getErrors()) {
            return $this->entry;
        }
    }

    /**
     * Get currency object
     *
     * @return Currency
     */
    private function buildCurrency()
    {
        $currencyCode = $this->settings['currency'] ?: 'USD';
        $currency = new Currency($currencyCode);

        return $currency;
    }

    /**
     * Get money object
     *
     * @param null $money
     * @return \Money\Money|null
     * @throws \Omnipay\Common\Exception\InvalidRequestException
     */
    private function buildAmount($money = null)
    {
        if (!$this->amountValue) {
            $this->entry->addError($this->integration['ccAmountField'], FormBuilder::t('Amount is required'));
        } else {
            $money = ConvergeValidation::instance()->getMoney();
            if (!ConvergeValidation::instance()->negativeAmountAllowed && $money->isNegative()) {
                $this->entry->addError($this->integration['ccAmountField'], FormBuilder::t('A negative amount is not allowed.'));
            }
            if (!ConvergeValidation::instance()->zeroAmountAllowed && $money->isZero()) {
                $this->entry->addError($this->integration['ccAmountField'], FormBuilder::t('A zero amount is not allowed.'));
            }
        }

        return $money;
    }

    /**
     * Initialize Gateway
     *
     * @return \Omnipay\Common\GatewayInterface
     */
    private function buildGateway()
    {
        $gateway = Omnipay::create('Converge')->initialize([
            'merchantId' => $this->settings['merchantId'],
            'userId' => $this->settings['merchantKey'],
            'pin' => $this->settings['merchantPin'],
            'testMode' => ($this->settings['testMode'] && ($this->settings['testMode'] != 'false')) ? 'true' : 'false'
        ]);

        return $gateway;
    }
}