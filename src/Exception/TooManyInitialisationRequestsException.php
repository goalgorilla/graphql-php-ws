<?php

namespace GraphQLWs\Exception;

use Throwable;

/**
 * Exception signaling that more than one ConnectionInit message was received.
 */
class TooManyInitialisationRequestsException extends ConnectionExceptionBase {

  /**
   * {@inheritdoc}
   */
  protected $code = 4429;

  /**
   * {@inheritdoc}
   */
  public function __construct($message = "Too many initialisation requests", Throwable $previous = NULL) {
    parent::__construct($message, $previous);
  }

}
