<?php

namespace GraphQLWs\Message;

/**
 * A GraphQL WebSocket message.
 *
 * https://github.com/enisdenjo/graphql-ws/blob/master/PROTOCOL.md#message-types
 */
interface MessageInterface {

  /**
   * Create a message from a data array.
   *
   * @param array $data
   *   An array containing the data that's required by the spec.
   *
   * @return self
   *   A new instance of a message.
   */
  public static function fromArray(array $data) : self;

  /**
   * Turn the message into a spec-compliant json serializable array.
   *
   * @return array
   *   An array that can be serialized and sent over the wire.
   */
  public function jsonSerialize() : array;

  /**
   * The message type as string representation.
   *
   * @return string
   *   The message type.
   */
  public function getType() : string;
}
