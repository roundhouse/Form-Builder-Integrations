<?php

namespace roundhouse\formbuilderintegrations\web\assets;

use Craft;
use craft\web\AssetBundle;

class Framework extends AssetBundle
{
    public function init()
    {
        Craft::setAlias('@fbilibs', '@vendor/roundhouse/formbuilderintegrations/lib/');

        $this->sourcePath = "@fbilibs";

        $this->css = [
            'codyhouse-framework/framework.css',
        ];

        $this->js = [
            'codyhouse-framework/framework.js',
        ];

        parent::init();
    }
}
