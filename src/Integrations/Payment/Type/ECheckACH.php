<?php

namespace roundhouse\formbuilderintegrations\Integrations\Payment\Type;

use roundhouse\formbuilder\elements\Entry;
use roundhouse\formbuilder\elements\Form;

use Omnipay\Converge\Gateway;
use Omnipay\Common\CreditCard as OmnipayCreditCard;
use Omnipay\Common\Helper;
use Omnipay\Converge\Message\Response as OmnipayResponse;

use Money\Currency;

class ECheckACH implements Type {
  use CommonMethods;

  private $gateway;
  private $currency;
  private $entry;
  private $integration;

  public function __construct(Gateway $gateway, Currency $currency, Entry $entry, array $integration) {
    $this->gateway     = $gateway;
    $this->currency    = $currency;
    $this->entry       = $entry;
    $this->integration = $integration;
  }

  public function isValid() {
    $is_valid = true;

    $required = [
      ['achAmount', 'Amount'],
      ['achAbaNumber', 'AbaNumber'],
      ['achBankAccountNumber', 'BankAccountNumber'],
      ['achBankAccountType', 'BankAccountType'],
      ['achAgree', 'Agree'],
      ['achCompany', 'Company'],
      ['achFirstName', 'FirstName'],
      ['achLastName', 'LastName'],
    ];

    foreach ($required as $conf) {
      if (!$this->validateRequired($conf[0], $conf[1])) {
        $is_valid = false;
      }
    }

    return $is_valid;
  }

  public function getTransaction() {
    $params = [
      'amount'                  => $this->getValueFromField('achAmount'),
      'currency'                => $this->currency,
      'ssl_aba_number'          => $this->getValueFromField('achAbaNumber'),
      'ssl_bank_account_number' => $this->getValueFromField('achBankAccountNumber'),
      'ssl_bank_account_type'   => $this->getValueFromField('achBankAccountType'),
      'ssl_agree'               => $this->getValueFromField('achAgree'),
      'ssl_first_name'          => $this->getValueFromField('achFirstName'),
      'ssl_last_name'           => $this->getValueFromField('achLastName'),
      'ssl_company'             => $this->getValueFromField('achCompany'),
    ];

    return $this->gateway->purchase($params);
  }

  public function handleSuccess(OmnipayResponse $response, Form $form) {
    return null;
  }
  public function handleFailure(OmnipayResponse $response, Form $form) {
    return null;
  }
}
