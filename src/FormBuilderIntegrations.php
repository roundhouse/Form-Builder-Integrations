<?php
/**
 * Form Builder Integrations plugin for Craft CMS 3.x
 *
 * Collection of integrations for Form Builder
 *
 * @link      https://roundhouseagency.com
 * @copyright Copyright (c) 2018 Vadim Goncharov
 */

namespace roundhouse\formbuilderintegrations;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\twig\variables\CraftVariable;

use roundhouse\formbuilder\controllers\EntriesController;
use roundhouse\formbuilderintegrations\plugin\Services;
use roundhouse\formbuilderintegrations\Integrations\Payment\Converge;

use yii\base\Event;

/**
 * Class FormBuilderIntegrations
 *
 * @author    Vadim Goncharov
 * @package   FormBuilderIntegrations
 * @since     1.0.0
 *
 */
class FormBuilderIntegrations extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var FormBuilderIntegrations
     */
    public static $plugin;

    // Public Properties
    // =========================================================================
    public $entry;
    public $integrationRecordId;

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';
    public $hasCpSettings = false;
    public $hasCpSection = false;
    public $changelogUrl = 'https://raw.githubusercontent.com/roundhouse/formbuilderintegrations/master/CHANGELOG.md';
    public $downloadUrl = 'https://formbuilder.tools';
    public $pluginUrl = 'https://formbuilder.tools';
    public $docsUrl = 'https://docs.formbuilder.tools/integrations';

    // Traits
    // =========================================================================

    use Services;
//    use Routes;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->_setPluginComponents();
//        $this->_registerCpRoutes();
        $this->_registerVariables();

        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $e) {
            /** @var CraftVariable $variable */
            $variable = $e->sender;
            $variable->set('fbi', $this::$plugin);
        });

        // Before submit entry
        Event::on(EntriesController::class, EntriesController::EVENT_BEFORE_SUBMIT_ENTRY, function(Event $e) {
            $form = $e->form;
            $entry = $e->entry;
            $integrations = $form->getIntegrations();

            // Save entry variable
            $this->entry = $entry;

            if ($integrations) {
                foreach ($integrations as $type => $integration) {
                    switch ($type) {
                        case 'converge':
                            // Converge Integration
                            $converge = Converge::instance()->prepare($form, $entry, $integration);

                            if ($converge->hasErrors()) {
                                $e->isValid = false;
                            }
                            break;
                    }
                }
            }
        });

        Craft::$app->view->hook('formbuilder-integrations-types', function(array &$context) {
            // Make dynamic
            $integrations['integrations'] = [
                ['category' => 'payment', 'frontend' => true, 'type' => 'converge', 'name' => 'Converge', 'icon' => 'converge']
            ];
            return Craft::$app->view->renderTemplate('form-builder-integrations/types/_items', $integrations);
        });

    }

    /**
     * @param $message
     * @param array $params
     * @return string
     */
    public static function t($message, array $params = [])
    {
        return Craft::t('fbi', $message, $params);
    }

    /**
     * @param $message
     * @param string $type
     */
    public static function log($message, $type = 'info')
    {
        Craft::$type(self::t($message), __METHOD__);
    }
    /**
     * @param $message
     */
    public static function info($message)
    {
        Craft::info(self::t($message), __METHOD__);
    }
    /**
     * @param $message
     */
    public static function error($message)
    {
        Craft::error(self::t($message), __METHOD__);
    }

    // Protected Methods
    // =========================================================================

     /**
     * Register variables
     */
    private function _registerVariables()
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('fbi', FBI::class);
                $variable->set('fbivariables', Variables::class);
            }
        );
    }

}
