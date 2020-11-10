<?php

namespace GraphQLWs\Message;

use GraphQLWs\Exception\InvalidMessageException;

/**
 * Complete.
 *
 * Direction: Server -> Client.
 *
 * Indicates that the requested operation execution has completed. If the server
 * dispatched the Error message relative to the original Subscribe message, no
 * Complete message will be emitted.
 *
 * Direction: Client -> Server.
 *
 * Indicates that the client has stopped listening and wants to complete the
 * source stream. No further events, relevant to the original subscription,
 * should be sent through.
 */
class CompleteMessage extends MessageBase implements ClientMessageInterface, ServerMessageInterface {

  /**
   * {@inheritdoc}
   */
  public static string $type = "complete";

  /**
   * The operation id.
   */
  protected string $id;

  /**
   * Create a new CompleteMessage instance.
   *
   * @param string $id
   *   The id of the operation.
   */
  public function __construct(string $id) {
    $this->id = $id;
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

    return new static($data['id']);
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

}
