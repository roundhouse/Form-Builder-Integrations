<?php

namespace roundhouse\formbuilderintegrations\web\assets;

use Craft;
use craft\web\AssetBundle;

class Tippy extends AssetBundle
{
    public function init()
    {
        Craft::setAlias('@fbilibs', '@vendor/roundhouse/formbuilderintegrations/lib/');

        $this->sourcePath = "@fbilibs";

        $this->css = [
            'tippy/light.css',
        ];

        $this->js = [
            'tippy/tippy.all.min.js',
        ];

        parent::init();
    }
}
