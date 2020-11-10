<?php

namespace GraphQLWs\Exception;

use Throwable;

/**
 * Exception signaling that the client took too long to send ConnectionInit.
 */
class ConnectionInitialisationTimeoutException extends ConnectionExceptionBase {

  /**
   * {@inheritdoc}
   */
  protected $code = 4408;

  /**
   * {@inheritdoc}
   */
  public function __construct($message = "Connection initialisation timeout", Throwable $previous = NULL) {
    parent::__construct($message, $previous);
  }

}
