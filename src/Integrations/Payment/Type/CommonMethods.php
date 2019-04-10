<?php

namespace roundhouse\formbuilderintegrations\Integrations\Payment\Type;

use roundhouse\formbuilder\FormBuilder;

trait CommonMethods {
  /**
   * Helper function to test if field contains a value
   */
  private function hasValueInField($field) {
    if (isset($this->integration[$field]) && $this->entry->{$this->integration[$field]} != '') {
      return true;
    }

    return false;
  }

  /**
   * Helper function to retrieve value from complex fields
   */
  private function getValueFromField($field) {
    $value = $this->entry->{$this->integration[$field]};

    if (is_object($value) && property_exists(get_class($value), 'value')) {
      $value = $value->value;
    }

    if ('ccNumberField' === $field) { //XXX: Remove white characters from CC number
      $value = preg_replace('/\s+/', '', $value);
    }

    return $value;
  }

  private function validateRequired($field, $field_label) {
    if (!$this->hasValueInField($field)) {
      $this->entry->addError($this->integration[$field], FormBuilder::t($field_label.' is required'));

      return false;
    }

    return true;
  }
}
