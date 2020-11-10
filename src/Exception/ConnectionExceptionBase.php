<?php

namespace GraphQLWs\Exception;

use Throwable;

/**
 * Base class for exceptions in GraphQL WebSocket Connections.
 */
abstract class ConnectionExceptionBase extends \Exception implements ConnectionExceptionInterface {

  /**
   * {@inheritdoc}
   *
   * By default 4400 signifies an unknown error.
   */
  protected $code = 4400;

  /**
   * Create a new GraphQL WS Connection Exception.
   *
   * @param string $message
   *   An optional message to provide context for the error that occurred.
   * @param \Throwable|null $previous
   *   A previous error that caused this one.
   */
  public function __construct($message = "", Throwable $previous = NULL) {
    parent::__construct($message, $this->code, $previous);
  }

}
