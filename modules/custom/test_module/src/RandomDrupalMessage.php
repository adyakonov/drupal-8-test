<?php

namespace Drupal\test_module;

/**
 * Class RandomDrupalMessage.
 *
 * @package Drupal\test_module
 */
class RandomDrupalMessage {

  // Тут мы будем хранить все возможные типы вывода сообщений для
  // drupal_set_message() из параметров сервиса.
  private $message_types;
  // В данном свойстве мы будем хранить экземпляр объекта
  // RandomMessageGenerator.
  private $random_message_generator;

  /**
   * При создании экземпляра данного объекта, сервисы автоматически передадут
   * сюда все указанные аргументы. Если в качестве аргументы был указан другой
   * сервис, то будет передан уже готовый экземпляр данного сервиса.
   *
   * @param \Drupal\test_module\RandomMessageGenerator $random_message_generator
   * @param array $message_types
   */
  public function __construct(\Drupal\test_module\RandomMessageGenerator $random_message_generator, array $message_types) {
    $this->random_message_generator = $random_message_generator;
    $this->message_types = $message_types;
  }

  /**
   * Этот метот как раз будет выводить сообщение ипользуя объект из первого
   * сервиса и параметры из services.yml
   */
  public function setRandomMessage() {
    $random_message = $this->random_message_generator->getRandomMessage();
    $random_message_type = rand(0, count($this->message_types));
    drupal_set_message($random_message, $this->message_types[$random_message_type]);
  }

}
