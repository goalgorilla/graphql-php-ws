<?php

namespace GraphQLWs\Message;

/**
 * ConnectionInit.
 *
 * Direction: Client -> Server.
 *
 * Indicates that the client wants to establish a connection within the existing
 * socket. This connection is not the actual WebSocket communication channel,
 * but is rather a frame within it asking the server to allow future operation
 * requests.
 */
class ConnectionInitMessage extends MessageBase implements ClientMessageInterface {

  /**
   * {@inheritdoc}
   */
  public static string $type = "connection_init";

  /**
   * An optional payload provided in the connection init message.
   */
  protected ?array $payload;

  /**
   * Create a new ConnectionInitMessage instance.
   *
   * @param array|null $payload
   *   An optional payload for the ConnectionInit message.
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
   * Get the payload for this ConnectionInit message.
   *
   * @return array|null
   *   An optional payload for the ConnectionInit message.
   */
  public function getPayload() : ?array {
    return $this->payload;
  }

}
