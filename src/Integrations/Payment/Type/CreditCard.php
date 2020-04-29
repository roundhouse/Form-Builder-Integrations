<?php

namespace roundhouse\formbuilderintegrations\Integrations\Payment\Type;

use Craft;

use Omnipay\TalusPay\Message\CreditCard\AuthorizeResponse;
use roundhouse\formbuilder\FormBuilder;
use roundhouse\formbuilder\elements\Entry;
use roundhouse\formbuilder\elements\Form;

use Omnipay\Common\AbstractGateway;
use Omnipay\Common\CreditCard as OmnipayCreditCard;
use Omnipay\Common\Helper;
use Omnipay\Common\Message\AbstractResponse as OmnipayResponse;

use Money\Currency;
use Pachico\Magoo\Magoo;
use craft\helpers\Json;
use craft\helpers\DateTimeHelper;

use roundhouse\formbuilderintegrations\models\Converge as ConvergeModel;
use roundhouse\formbuilderintegrations\models\Taluspay as TaluspayModel;
use roundhouse\formbuilderintegrations\records\Converge as ConvergeRecord;
use roundhouse\formbuilderintegrations\records\Taluspay as TaluspayRecord;

class CreditCard implements Type {
  use CommonMethods;

  private $gateway;
  private $currency;
  private $entry;
  private $integration;

  public function __construct(AbstractGateway $gateway, Currency $currency, Entry $entry, array $integration) {
    $this->gateway     = $gateway;
    $this->currency    = $currency;
    $this->entry       = $entry;
    $this->integration = $integration;
  }

  public function isValid() {
    $is_valid = true;

    if (!$this->validateRequired('ccExpirationMonthField', 'Expiration month')) {
      $is_valid = false;
    }

    if (!$this->validateRequired('ccExpirationYearField', 'Expiration year')) {
      $is_valid = false;
    }

    if (!$this->validateRequired('ccNumberField', 'Card number')) {
      $is_valid = false;
    }

    if (!$this->validateRequired('ccAmountField', 'Amount')) {
      $is_valid = false;
    }

    if (!$this->validateCreditCardNumber()) {
      $is_valid = false;
    }

    if (!$this->validateExpirationDate()) {
      $is_valid = false;
    }

    return $is_valid;
  }

  private function validateCreditCardNumber() {
    $cardNumber = $this->getValueFromField('ccNumberField');

    if (
      is_null($cardNumber) ||
      !is_numeric($cardNumber) ||
      !Helper::validateLuhn($cardNumber)
    ) {
        $this->entry->addError($this->integration['ccNumberField'], FormBuilder::t('Card number is invalid'));

        return false;
    }

    if (!preg_match('/^\d{12,19}$/i', $cardNumber)) {
        $this->entry->addError($this->integration['ccNumberField'], FormBuilder::t('Card number should have 12 to 19 digits'));

        return false;
    }

    return true;
  }

  private function validateExpirationDate() {
    $expiration = $this->getValueFromField('ccExpirationYearField').$this->getValueFromField('ccExpirationMonthField');

    if ($expiration < gmdate('Ym')) {
      $this->entry->addError($this->integration['ccNumberField'], FormBuilder::t('Card has expired'));

      return false;
    }

    return true;
  }

  private function buildCreditCard() {
    if ($this->isValid()) {
      $cc = new OmnipayCreditCard();
      $cc->setExpiryMonth($this->getValueFromField('ccExpirationMonthField'));
      $cc->setExpiryYear($this->getValueFromField('ccExpirationYearField'));
      $cc->setNumber($this->getValueFromField('ccNumberField'));
      if ($this->hasValueInField('ccCvcField')) {
        $cc->setCvv($this->getValueFromField('ccCvcField'));
      }
      if ($this->hasValueInField('firstNameField')) {
        $cc->setFirstName($this->getValueFromField('firstNameField'));
      }
      if ($this->hasValueInField('lastNameField')) {
        $cc->setLastName($this->getValueFromField('lastNameField'));
      }
      if ($this->hasValueInField('emailField')) {
        $cc->setEmail($this->getValueFromField('emailField'));
      }
      if ($this->hasValueInField('phoneField')) {
        $cc->setPhone($this->getValueFromField('phoneField'));
      }

      return $cc;
    }

    return null;
  }

  public function getTransaction() {
    $cc  = $this->buildCreditCard();
    if (null === $cc) {
      throw new \Exception('Could not create transaction due to some errors, please run "isValid" for details.');
    }

    $params = [
        'amount'    => $this->getValueFromField('ccAmountField'),
        'currency'  => $this->currency,
        'card'      => $cc
    ];

    switch ($this->integration['transactionType']) {
      case 'ccsale':
        return $this->gateway->purchase($params);
        break;
      case 'ccauthonly':
        return $this->gateway->purchase($params);
        break;
      default:
        return null;
        break;
    }

    return null;
  }

  public function handleSuccess(OmnipayResponse $response, Form $form) {
      $type = $this->integration['type'];
    FormBuilder::info("Integration {$type} payment success! " . $response->getMessage());

    try {
        $this->maskCredentials($form);
    } catch(\Throwable $e) {
        FormBuilder::error("Integration {$type} normalizing fields and changing title failed! " . $e);
    }

    $data = $response->getData();

    switch ($type) {
        case 'converge':
            return $this->createConvergeRecords($data, $response);
        case 'taluspay':
            return $this->createTaluspayRecords($data, $response);
    }
  }

  public function handleFailure(OmnipayResponse $response, Form $form) {
    $this->maskCredentials($form);
    if ($response->getCode() === '4007') {
      $this->entry->addError($this->integration['ccCvcField'], FormBuilder::t($field->name . ' is required'));
      return;
    }

    if ($response->getCode() === '4025') {
      $this->entry->addError($this->integration['ccCvcField'], FormBuilder::t($response->getMessage()));
      return;
    }

    FormBuilder::error("Integration ${$type} payment failed! " . $response->getMessage());
  }

  private function maskCredentials(Form $form)
  {
      $magoo = new Magoo();
      $magoo->maskCreditCards();

      $this->entry->{$this->integration['ccNumberField']} = $magoo->getMasked($this->entry->{$this->integration['ccNumberField']});
      $this->entry->{$this->integration['ccCvcField']} = '***';

      $postFields = $_POST['fields'];
      foreach ($postFields as $handle => $field) {
        if ($this->integration['ccNumberField'] === $handle) {
          $postFields[$this->integration['ccNumberField']] = $magoo->getMasked($this->entry->{$this->integration['ccNumberField']});
        }

        if ($this->integration['ccCvcField'] === $handle) {
          $postFields[$this->integration['ccCvcField']] = '***';
        }
      }
      $title = isset($form->settings['database']['titleFormat']) && $form->settings['database']['titleFormat'] != '' ? $form->settings['database']['titleFormat'] : 'Submission - '.DateTimeHelper::currentTimeStamp();

      $this->entry->title = Craft::$app->getView()->renderObjectTemplate($title, $postFields);
  }

    /**
     * @param $data
     * @param OmnipayResponse $response
     * @return array
     */
    private function createConvergeRecords($data, OmnipayResponse $response)
    {
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

        return [$model, $record];
    }


    private function createTaluspayRecords($data, OmnipayResponse $response)
    {
        /** @var  AuthorizeResponse $response */
        $model = new TaluspayModel();
        $model->integrationId = $this->integration['integrationId'];
        $model->amount = $response->getRequest()->getData()['amount'];
        $model->currency = $this->currency->getCode();
        $model->last4 = substr($data['masked_card_number'], -4);
        $model->status = $response->getMessage();
        $model->metadata = Json::encode($data);

        $record = new TaluspayRecord();
        $record->integrationId = $model->integrationId;
        $record->amount = $model->amount;
        $record->currency = $model->currency;
        $record->last4 = $model->last4;
        $record->status = $model->status;
        $record->metadata = $model->metadata;

        return [$model, $record];
    }
}
