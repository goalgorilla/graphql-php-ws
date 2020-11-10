<?php

namespace GraphQLWs\Message;

use GraphQL\Error\Error;
use GraphQLWs\Exception\InvalidMessageException;

/**
 * Error.
 *
 * Direction: Server -> Client.
 *
 * Operation execution error(s) triggered by the Next message happening before
 * the actual execution, usually due to validation errors.
 */
class ErrorMessage extends MessageBase implements ServerMessageInterface {

  /**
   * {@inheritdoc}
   */
  public static string $type = "error";

  /**
   * The operation id.
   */
  protected string $id;

  /**
   * The execution result.
   */
  protected array $payload;

  /**
   * Create a new ErrorMessage instance.
   *
   * @param string $id
   *   The id of the operation this result is for.
   * @param \GraphQL\Error\Error[] $payload
   *   The execution errors.
   */
  public function __construct(string $id, array $payload) {
    $this->id = $id;
    $this->payload = $payload;
  }

  /**
   * {@inheritdoc}
   */
  public static function fromArray(array $data): self {
    if ($data['type'] !== static::$type) {
      throw new \RuntimeException("Trying to instantiate message of incorrect type '{$data['type']}'.");
    }
    if (empty($data['id'])) {
      throw new InvalidMessageException("Missing id");
    }
    if (empty($data['payload'])) {
      throw new InvalidMessageException("Missing payload");
    }

    return new static($data['id'], $data['payload']);
  }

  /**
   * Get the operation id.
   *
   * @return string
   *   The operation id.
   */
  public function getId() : string {
    return $this->id;
  }

  /**
   * Get the payload for this message.
   *
   * @return array
   *   The execution errors as a spec compliant array.
   */
  public function getPayload() : array {
    return array_map(static fn (Error $e) => $e->jsonSerialize(), $this->payload);
  }

}
