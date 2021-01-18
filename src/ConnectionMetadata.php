<?php

namespace GraphQLWs;

use GraphQLWs\Exception\TooManyInitialisationRequestsException;

/**
 * GraphQL WebSocket Connection Metadata.
 *
 * Provides information about a connection using the graphql-transport-ws
 * protocol.
 */
class ConnectionMetadata {

  /**
   * Whether this connection is authenticated and can perform operations.
   */
  private bool $connectionAccepted = FALSE;

  /**
   * A callback that cancels the connection request timeout.
   *
   * Connections should be initialised with a single ConnectionInit message
   * within the lifetime of this timer. Once a connection is initialised, this
   * value should be set to null.
   */
  private ?\Closure $cancelInitTimeoutCb;

  /**
   * Create a new GraphQL WebSocket Connection Metadata instance.
   *
   * @param null|\Closure $cancel_init_timeout
   *   A function that cancels the timer to timeout this connection request.
   *   This will be called when this connection is marked as initialised. Set
   *   this to NULL if the connection is already initialised.
   */
  public function __construct(?\Closure $cancel_init_timeout = NULL) {
    $this->cancelInitTimeoutCb = $cancel_init_timeout;
  }

  /**
   * Clean up the metadata for this connection when the connection is cleared.
   */
  public function __destruct() {
    // If this connection gets cleaned up we must cancel the timeout if that
    // didn't happen yet because it'll try to use the connection.
    $this->cancelInitTimeout();
  }

  /**
   * Whether this connection has sent its initialisation message.
   *
   * @return bool
   *   Whether this connection has sent its initialisation message.
   */
  public function isInitialized() : bool {
    return $this->cancelInitTimeoutCb === NULL;
  }

  /**
   * Marks a connection as having started initialization.
   *
   * @throws \GraphQLWs\Exception\TooManyInitialisationRequestsException
   *   Thrown when initialization was already attempted.
   */
  public function initialise() : void {
    if ($this->isInitialized()) {
      throw new TooManyInitialisationRequestsException();
    }

    $this->cancelInitTimeout();
  }

  /**
   * Whether this connection is authenticated and can perform operations.
   *
   * @return bool
   *   Whether this connection is authenticated and can perform operations.
   */
  public function isAccepted() : bool {
    return $this->connectionAccepted;
  }

  /**
   * Accept the connection.
   *
   * This will allow it to perform operations.
   */
  public function accept() : void {
    $this->connectionAccepted = TRUE;
  }

  /**
   * Cancel the init timeout.
   */
  private function cancelInitTimeout() : void {
    if ($this->cancelInitTimeoutCb !== NULL) {
      ($this->cancelInitTimeoutCb)();
      $this->cancelInitTimeoutCb = NULL;
    }
  }

}
