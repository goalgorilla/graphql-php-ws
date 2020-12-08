<?php

namespace GraphQLWs;

use Ratchet\WebSocket\WsConnection;
use React\Promise\PromiseInterface;

/**
 * An interface for GraphQLWs authentication handlers.
 */
interface GraphQLWsInitHandlerInterface extends GraphQLWsEventHandlerInterface {

  /**
   * Event handler for a connection init request.
   *
   * @param \Ratchet\WebSocket\WsConnection $client
   *   The client that is trying to make the connection. This event handler
   *   should not send any data to the client. The connection is passed to the
   *   event handler because it may contain information related to
   *   authentication such as cookies.
   * @param array|null $payload
   *   The payload sent in the connection init request.
   *
   * @return \React\Promise\PromiseInterface<bool>
   *   A promise that resolves to a boolean to indicate whether the connection
   *   should be accepted or rejected.
   *
   * @throws \GraphQLWs\Exception\ConnectionDeniedException
   *   Thrown when an event handler wants to deny the connection.
   */
  public function onConnectionInit(WsConnection $client, ?array $payload) : PromiseInterface;

}
