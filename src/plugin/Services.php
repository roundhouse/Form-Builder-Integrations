<?php

namespace roundhouse\formbuilderintegrations\plugin;

use roundhouse\formbuilderintegrations\services\Integrations;

trait Services
{
	// Public Methods
    // =========================================================================

    /**
     * Get Integrations
     *
     * @return Integrations
     */
    public function getIntegrations(): Integrations
    {
        return $this->get('integrations');
    }

    // Private Methods
    // =========================================================================

    /**
     * Set components
     */
    private function _setPluginComponents()
    {
        $this->setComponents([
            'integrations' => Integrations::class,
        ]);
    }
}