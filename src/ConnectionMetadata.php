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
   * Whether this connection has been successfully initialised.
   *
   * @return bool
   *   Whether this connection has been successfully initialised.
   */
  public function isInitialized() : bool {
    return $this->cancelInitTimeoutCb === NULL;
  }

  /**
   * Marks a connection as initialised.
   *
   * @throws \GraphQLWs\Exception\TooManyInitialisationRequestsException
   *   Thrown when a connection was previously initialised.
   */
  public function markInitialised() : void {
    if ($this->isInitialized()) {
      throw new TooManyInitialisationRequestsException();
    }

    $this->cancelInitTimeout();
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
