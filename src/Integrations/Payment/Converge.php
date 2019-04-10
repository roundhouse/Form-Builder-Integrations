<?php

namespace roundhouse\formbuilderintegrations\Integrations\Payment;

use Craft;
use craft\base\Component;
use craft\web\View;
use craft\helpers\Json;
use yii\base\Exception;
use roundhouse\formbuilder\FormBuilder;
use roundhouse\formbuilderintegrations\events\EntryEvent;
use roundhouse\formbuilderintegrations\Integrations\Payment\Converge\ConvergeValidation;
use roundhouse\formbuilder\elements\Entry;

use Omnipay\Omnipay;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Helper;
use Omnipay\Converge\Message\Response as OmnipayResponse;
use Money\Currency;

use roundhouse\formbuilderintegrations\Integrations\Payment\Type\Type;
use roundhouse\formbuilderintegrations\models\Converge as ConvergeModel;
use roundhouse\formbuilderintegrations\records\Converge as ConvergeRecord;

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

    const EVENT_AFTER_SAVE = 'afterSave';

    public function prepare($form, Entry $entry, $converge)
    {
        $this->settings = [
          'merchantId'    => $converge['integration']->settings['merchantId'],
          'merchantKey'   => $converge['integration']->settings['merchantKey'],
          'merchantPin'   => $converge['integration']->settings['merchantPin'],
          'currency'      => $converge['integration']->settings['currency'],
          'type'          => $converge['integration']->settings['type'],
          'testMode'      => $converge['integration']->settings['testMode'],
        ];

        $this->entry            = $entry;
        $this->form             = $form;
        $this->integration      = $converge;
        $this->integrationModel = $converge['integration'];

        $gateway        = $this->buildGateway();
        $this->currency = $this->buildCurrency();

        $processorClass = 'roundhouse\formbuilderintegrations\Integrations\Payment\Type\\'.$this->settings['type'];
        $processor      = new $processorClass($gateway, $this->currency, $this->entry, $converge);
        if (!($processor instanceof Type)) {
          throw new \Exception('Processor class needs to implement Type interface.');
        }
        if (!$processor->isValid()) {
          return $this->entry;
        }

        $transaction = $processor->getTransaction();
        if (null === $transaction) {
          throw new \Exception('Processor returned emtpy transaction.');
        }

        $response = $transaction->send();
        if ($response->isSuccessful()) {
          $handled = $processor->handleSuccess($response, $this->form);
          if (null !== $handled) {
            $result = $this->saveRecord($handled[0], $handled[1], $response);
            if ($result) {
              $integrationResults = [
                'integrationId'       => $this->integration['integrationId'],
                'integrationRecordId' => $result
              ];
              $this->entry->settings = Json::encode($integrationResults);
            }
          }
        } else {
          $processor->handleFailure($response, $this->form);
        }

        return $this->entry;
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
     * Initialize Gateway
     *
     * @return \Omnipay\Common\GatewayInterface
     */
    private function buildGateway()
    {
      $gateway = Omnipay::create('Converge')->initialize([
        'merchantId' => $this->settings['merchantId'],
        'userId'     => $this->settings['merchantKey'],
        'pin'        => $this->settings['merchantPin'],
        'testMode'   => ($this->settings['testMode'] && ($this->settings['testMode'] != 'false')) ? 'true' : 'false',
        'type'       => $this->settings['type'],
      ]);

      return $gateway;
    }

    private function saveRecord(ConvergeModel $model, ConvergeRecord $record, OmnipayResponse $response)
    {
      if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE)) {
        $this->trigger(self::EVENT_AFTER_SAVE, new EntryEvent([
          'response' => $response->getData(),
          'model'    => $model,
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
}
