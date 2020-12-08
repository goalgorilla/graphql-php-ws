<?php

namespace GraphQLWs\Exception;

use Throwable;

/**
 * Exception signaling that the client's 'ConnectionInit was denied.
 */
class ConnectionDeniedException extends ConnectionExceptionBase {

  /**
   * {@inheritdoc}
   */
  public function __construct($message = "Access Denied", Throwable $previous = NULL) {
    parent::__construct($message, $previous);
  }

}
