<?php

namespace GraphQLWs\Exception;

use Throwable;

/**
 * Exception signaling that the provided subscription ID is already in use.
 */
class DuplicateSubscriberException extends ConnectionExceptionBase {

  /**
   * {@inheritdoc}
   */
  protected $code = 4409;

  /**
   * Creaete a new DuplicateSubscriberException instance.
   *
   * @param $id
   *   The id of the operation that already exists.
   * @param \Throwable|null $previous
   *   A previous exception if any.
   */
  public function __construct($id, Throwable $previous = NULL) {
    parent::__construct($message = "Subscriber for ${id} already exists.", $previous);
  }

}
