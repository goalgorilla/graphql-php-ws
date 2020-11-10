<?php

namespace GraphQLWs\Message;

use GraphQL\Executor\ExecutionResult;
use GraphQLWs\Exception\InvalidMessageException;

/**
 * Next.
 *
 * Direction: Server -> Client.
 *
 * Operation execution result(s) from the source stream created by the binding
 * Subscribe message. After all results have been emitted, the Complete message
 * will follow indicating stream completion.
 */
class NextMessage extends MessageBase implements ServerMessageInterface {

  /**
   * {@inheritdoc}
   */
  public static string $type = "next";

  /**
   * The unique operation id.
   */
  protected string $id;

  /**
   * The execution result.
   */
  protected ExecutionResult $payload;

  /**
   * Create a new NextMessage instance.
   *
   * @param string $id
   *   The id of the operation this result is for.
   * @param \GraphQL\Executor\ExecutionResult $payload
   *   The execution result.
   */
  public function __construct(string $id, ExecutionResult $payload) {
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
   *   The execution result as a spec compliant array.
   */
  public function getPayload() : array {
    return $this->payload->jsonSerialize();
  }

}
