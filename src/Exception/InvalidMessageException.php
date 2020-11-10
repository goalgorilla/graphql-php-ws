<?php

namespace GraphQLWs\Exception;

use Throwable;

/**
 * Exception caused by an invalid message from the client.
 */
class InvalidMessageException extends ConnectionExceptionBase {

  /**
   * {@inheritdoc}
   */
  public function __construct($message, Throwable $previous = NULL) {
    parent::__construct($message, $previous);
  }

}
