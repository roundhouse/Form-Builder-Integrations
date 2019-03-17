<?php

namespace roundhouse\formbuilderintegrations\Integrations\Payment\Type;

interface Type {
  public function isValid();
  public function getTransaction();
}
