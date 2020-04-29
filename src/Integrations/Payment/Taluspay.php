<?php

namespace roundhouse\formbuilderintegrations\Integrations\Payment;

use Craft;
use craft\base\Component;
use craft\web\View;
use craft\helpers\Json;
use Omnipay\Common\Http\Exception\RequestException;
use Omnipay\TalusPay\CheckGateway;
use Omnipay\TalusPay\CreditCardGateway;
use Http\Adapter\Guzzle6\Client;
use yii\base\Exception;
use roundhouse\formbuilder\FormBuilder;
use roundhouse\formbuilderintegrations\events\EntryEvent;
use roundhouse\formbuilder\elements\Entry;

use Omnipay\Omnipay;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Helper;
use Omnipay\Common\Http\Client as OmnipayClient;
use Omnipay\TalusPay\Message\AbstractResponse as OmnipayResponse;
use Money\Currency;

use roundhouse\formbuilderintegrations\Integrations\Payment\Type\Type;
use roundhouse\formbuilderintegrations\models\Taluspay as TaluspayModel;
use roundhouse\formbuilderintegrations\records\Taluspay as TaluspayRecord;


class Taluspay extends Component
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
    
    const INTEGRATOR_ID = '92400UL8ObAn';

    public function prepare($form, Entry $entry, $integration)
    {
        $this->settings = [
          'merchantId'    => $integration['integration']->settings['merchantId'],
          'merchantKey'   => $integration['integration']->settings['merchantKey'],
          'currency'      => $integration['integration']->settings['currency'],
          'type'          => $integration['integration']->settings['type'],
        ];

        $this->entry            = $entry;
        $this->form             = $form;
        $this->integration      = $integration;
        $this->integrationModel = $integration['integration'];

        $gateway        = $this->buildGateway();
        $this->currency = $this->buildCurrency();

        $processorClass = 'roundhouse\formbuilderintegrations\Integrations\Payment\Type\\'.$this->settings['type'];
        $processor      = new $processorClass($gateway, $this->currency, $this->entry, $integration);
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

        try {
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
        } catch (RequestException $exception) {
            $a = '';
//            $processor->handleFailure($exception, $this->form);
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
        $type = $this->settings['type'] === 'ECheckACH' ? 'Check' : $this->settings['type'];
        $gatewayClassName = $this->settings['type'] === 'ECheckACH' ? 'TalusPay\\Check' : 'TalusPay\\CreditCard' ;


        $craftClient = Craft::createGuzzleClient();
        $adapter = new Client($craftClient);
        $httpClient = new OmnipayClient($adapter);

        $g = new CheckGateway($httpClient);
        $gateway = Omnipay::create($gatewayClassName, $httpClient)->initialize(
            [
                'username' => $this->settings['merchantId'],
                'password' => $this->settings['merchantKey'],
                'integratorId' => self::INTEGRATOR_ID,
            ]
        );

        return $gateway;
    }

    private function saveRecord(TaluspayModel $model, TaluspayRecord $record, OmnipayResponse $response)
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
