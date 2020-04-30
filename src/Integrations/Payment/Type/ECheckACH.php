<?php

namespace roundhouse\formbuilderintegrations\Integrations\Payment\Type;

use craft\helpers\Json;
use Money\Currency;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Message\AbstractResponse as OmnipayResponse;
use roundhouse\formbuilder\elements\Entry;
use roundhouse\formbuilder\elements\Form;
use roundhouse\formbuilder\FormBuilder;
use roundhouse\formbuilderintegrations\models\Taluspay as TaluspayModel;
use roundhouse\formbuilderintegrations\records\Taluspay as TaluspayRecord;

class ECheckACH implements Type
{
  use CommonMethods;

  private $gateway;
  private $currency;
  private $entry;
  private $integration;

  public function __construct(AbstractGateway $gateway, Currency $currency, Entry $entry, array $integration)
  {
    $this->gateway = $gateway;
    $this->currency = $currency;
    $this->entry = $entry;
    $this->integration = $integration;
  }

  public function isValid()
  {
    $is_valid = true;

    $required = [
      ['achAmount', 'Amount'],
      ['achAbaNumber', 'AbaNumber'],
      ['achFirstName', 'FirstName'],
      ['achLastName', 'LastName'],
    ];
    if ($this->integration['type'] === 'converge') {
      $required = array_merge(
        $required,
        [
          ['achBankAccountNumber', 'BankAccountNumber'],
          ['achBankAccountType', 'BankAccountType'],
          ['achAgree', 'Agree'],
          ['achCompany', 'Company'],
        ]
      );
    }

    foreach ($required as $conf) {
      if (!$this->validateRequired($conf[0], $conf[1])) {
        $is_valid = false;
      }
    }

    return $is_valid;
  }

  public function getTransaction()
  {
    switch ($this->integration['type']) {
      case 'converge':
        $params = [
          'amount' => $this->getValueFromField('achAmount'),
          'currency' => $this->currency,
          'ssl_aba_number' => $this->getValueFromField('achAbaNumber'),
          'ssl_bank_account_number' => $this->getValueFromField('achBankAccountNumber'),
          'ssl_bank_account_type' => $this->getValueFromField('achBankAccountType'),
          'ssl_agree' => $this->getValueFromField('achAgree'),
          'ssl_first_name' => $this->getValueFromField('achFirstName'),
          'ssl_last_name' => $this->getValueFromField('achLastName'),
          'ssl_company' => $this->getValueFromField('achCompany'),
        ];
        return $this->gateway->purchase($params);
      case 'taluspay':
        $params = [
          'amount' => $this->getValueFromField('achAmount'),
          'currency' => $this->currency,
          'check' => [
            'routingNumber' => $this->getValueFromField('achAbaNumber'),
            'bankAccount' => $this->getValueFromField('achBankAccountNumber'),
            'billingFirstName' => $this->getValueFromField('achFirstName'),
            'billingLastName' => $this->getValueFromField('achLastName'),
          ],
        ];
        return $this->gateway->authorize($params);
    };


  }

  public function handleSuccess(OmnipayResponse $response, Form $form)
  {
    $type = $this->integration['type'];
    FormBuilder::info("Integration {$type} payment success! " . $response->getMessage());

    switch ($type) {
      case 'taluspay':
        return $this->createTaluspayRecords($response->getData(), $response);
      case 'converge':
        return null;
    }

    return null;
  }

  private function createTaluspayRecords($data, OmnipayResponse $response)
  {
    /** @var  AuthorizeResponse $response */
    $model = new TaluspayModel();
    $model->integrationId = $this->integration['integrationId'];
    $model->amount = $response->getRequest()->getData()['amount'];
    $model->currency = $this->currency->getCode();
    $model->status = $response->getMessage();
    $model->metadata = Json::encode($data);

    $record = new TaluspayRecord();
    $record->integrationId = $model->integrationId;
    $record->amount = $model->amount;
    $record->currency = $model->currency;
    $record->status = $model->status;
    $record->metadata = $model->metadata;

    return [$model, $record];
  }

  public function handleFailure(OmnipayResponse $response, Form $form)
  {
    $type = $this->integration['type'];
    FormBuilder::error("Integration ${$type} payment failed! " . $response->getMessage());

    return null;
  }
}
