<?php

namespace GraphQLWs\Message;

use GraphQLWs\Exception\InvalidMessageException;

/**
 * ConnectionAck.
 *
 * Direction: Server -> Client.
 *
 * Expected response to the ConnectionInit message from the client acknowledging
 * a successful connection with the server.
 */
class ConnectionAckMessage extends MessageBase implements ServerMessageInterface {

  /**
   * {@inheritdoc}
   */
  public static string $type = "connection_ack";

  /**
   * An optional payload for the message.
   */
  protected ?array $payload;

  /**
   * Create a new ConnectionAckMessage instance.
   *
   * @param array|null $payload
   *   An optional payload for the message.
   */
  public function __construct(?array $payload = NULL) {
    $this->payload = $payload;
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray(array $data): self {
    if ($data['type'] !== static::$type) {
      throw new \RuntimeException("Trying to instantiate message of incorrect type '{$data['type']}'.");
    }
    return new static($data['payload'] ?? NULL);
  }

  /**
   * Get the payload for this message.
   *
   * @return array|null
   *   An optional payload for the message.
   */
  public function getPayload() : ?array {
    return $this->payload;
  }

}
