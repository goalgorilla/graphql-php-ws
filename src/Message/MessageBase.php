<?php

namespace GraphQLWs\Message;

/**
 * Base class for GraphQL WebSocket message classes.
 */
abstract class MessageBase implements MessageInterface {

  /**
   * The string representation of the type of this message.
   */
  public static string $type;

  /**
   * {inheritdoc}
   */
  public function getType(): string {
    return static::$type;
  }

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): array {
    // Start with an empty array for deterministic order of present properties.
    $return = [];

    // If this message supports an id, include it.
    if (is_callable([$this, 'getId'])) {
      $return['id'] = $this->getId();
    }

    // Type is added here as it's required but should follow `id` in the order.
    $return['type'] = $this->getType();

    // If this message supports a payload, include it if it's non-empty.
    if (is_callable([$this, 'getPayload'])) {
      $payload = $this->getPayload();
      if ($payload !== NULL) {
        $return['payload'] = $payload;
      }
    }

    return $return;
  }

}
