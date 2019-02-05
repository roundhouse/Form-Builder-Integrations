<?php

namespace roundhouse\formbuilderintegrations\Integrations\Payment;

use Craft;
use craft\base\Component;
use craft\helpers\Json;
use craft\helpers\DateTimeHelper;
use craft\web\View;
use yii\base\Exception;
use roundhouse\formbuilder\FormBuilder;
use roundhouse\formbuilderintegrations\events\EntryEvent;
use roundhouse\formbuilderintegrations\Integrations\Payment\Converge\ConvergeValidation;
use roundhouse\formbuilderintegrations\models\Converge as ConvergeModel;
use roundhouse\formbuilderintegrations\records\Converge as ConvergeRecord;

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

    // Constants
    // =========================================================================

    const EVENT_AFTER_SAVE = 'afterSave';

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
        $this->amountValue  = $this->buildAmount($converge);

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
            FormBuilder::info('Integration Converge payment success! ' . $response->getMessage());

            try {
                // Normalize Fields
                $data = $response->getData();
                $this->entry->{$converge['ccNumberField']} = $data['ssl_card_number'];
                $this->entry->{$converge['ccCvcField']} = '***';

                $postFields = $_POST['fields'];
                $title = isset($this->form->settings['database']['titleFormat']) && $this->form->settings['database']['titleFormat'] != '' ? $this->form->settings['database']['titleFormat'] : 'Submission - '.DateTimeHelper::currentTimeStamp();

                foreach ($postFields as $handle => $field) {
                    if ($converge['ccNumberField'] === $handle) {
                        $postFields[$converge['ccNumberField']] = $data['ssl_card_number'];
                    }

                    if ($converge['ccCvcField'] === $handle) {
                        $postFields[$converge['ccCvcField']] = '***';
                    }
                }

                $newTitle = Craft::$app->getView()->renderObjectTemplate($title, $postFields);
                $this->entry->title = $newTitle;
                
                Craft::dd($newTitle);

            } catch(\Throwable $e) {
                FormBuilder::error('Integration Converge normalizing fields and changing title failed! ' . $e);
            }

            // Save transaction record
            $result = $this->saveRecord($response);

            if ($result) {
                $integrationResults = [
                    'integrationId' => $this->integration['integrationId'],
                    'integrationRecordId' => $result
                ];
                $this->entry->settings = Json::encode($integrationResults);
            }

        } else {
            if ($response->getCode() === '4007') {
                $field = Craft::$app->fields->getFieldByHandle($converge['ccCvcField']);
                $this->entry->addError($converge['ccCvcField'], FormBuilder::t($field->name . ' is required'));
            }

            if ($response->getCode() === '4025') {
                $this->entry->addError($converge['ccCvcField'], FormBuilder::t($response->getMessage()));
            }

            FormBuilder::error('Integration Converge payment failed! ' . $response->getMessage());
        }

        return $this->entry;
    }

    // Private Methods
    // =========================================================================

    private function saveRecord($response)
    {
        $data = $response->getData();

        $model = new ConvergeModel();
        $model->integrationId = $this->integration['integrationId'];
        $model->amount = $data['ssl_amount'];
        $model->currency = $this->currency->getCode();
        $model->last4 = substr($data['ssl_card_number'], -4);
        $model->status = $response->getMessage();
        $model->metadata = Json::encode($data);

        $record = new ConvergeRecord();
        $record->integrationId = $model->integrationId;
        $record->amount = $model->amount;
        $record->currency = $model->currency;
        $record->last4 = $model->last4;
        $record->status = $model->status;
        $record->metadata = $model->metadata;

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE)) {
            $this->trigger(self::EVENT_AFTER_SAVE, new EntryEvent([
                'response' => $data,
                'model' => $model,
            ]));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $record->save(false);
            $transaction->commit();

            return $record->id;

        } catch (\Throwable $e) {
            $transaction->rollBack();
            FormBuilder::error('Integration Converge Record failed to save! ' . $e);

            return false;
        }
    }

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
            $field = Craft::$app->fields->getFieldByHandle($this->integration['ccExpirationMonthField']);
            if (get_class($field) === 'craft\\fields\\Dropdown') {
                $value = $this->entry->{$this->integration['ccExpirationMonthField']}->value;
            } else {
                $value = $this->entry->{$this->integration['ccExpirationMonthField']};
            }
            $this->card->setExpiryMonth($value);
        } else {
            $this->entry->addError($this->integration['ccExpirationMonthField'], FormBuilder::t('Expiration month is required'));
        }

        if (isset($this->integration['ccExpirationYearField']) && $this->entry->{$this->integration['ccExpirationYearField']} != '') {
            $field = Craft::$app->fields->getFieldByHandle($this->integration['ccExpirationMonthField']);
            if (get_class($field) === 'craft\\fields\\Dropdown') {
                $value = $this->entry->{$this->integration['ccExpirationYearField']}->value;
            } else {
                $value = $this->entry->{$this->integration['ccExpirationYearField']};
            }
            $this->card->setExpiryYear($value);
        } else {
            $this->entry->addError($this->integration['ccExpirationYearField'], FormBuilder::t('Expiration year is required'));
        }

        if (isset($this->integration['ccNumberField']) && $this->entry->{$this->integration['ccNumberField']} != '') {
            $cardNumber = preg_replace('/\s+/', '', $this->entry->{$this->integration['ccNumberField']});

            if (!is_numeric($cardNumber)) {
                $this->entry->addError($this->integration['ccNumberField'], FormBuilder::t('Card number is invalid'));
            } else {
                if (!Helper::validateLuhn($cardNumber)) {
                    $this->entry->addError($this->integration['ccNumberField'], FormBuilder::t('Card number is invalid'));
                }
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
     */
    private function buildAmount($converge)
    {
        $result = isset($converge['ccAmountField']) && $this->integration['ccAmountField'] != '' ? $this->entry->{$this->integration['ccAmountField']} : null;

        if (!$result) {
            $this->entry->addError($this->integration['ccAmountField'], FormBuilder::t('Amount is required'));
        }

        return $result;
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
