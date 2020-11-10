<?php

namespace GraphQLWs\Message;

use GraphQLWs\Exception\InvalidMessageException;

/**
 * ConnectionAck.
 *
 * Direction: Client -> Server.
 *
 * Requests an operation specified in the message payload. This message provides
 * a unique ID field to connect published messages to the operation requested by
 * this message.
 */
class SubscribeMessage extends MessageBase implements ClientMessageInterface {

  /**
   * {@inheritdoc}
   */
  public static string $type = "subscribe";

  /**
   * The unique operation id.
   */
  protected string $id;

  /**
   * The query being subscribed to.
   */
  protected string $query;

  /**
   * An optional operation name.
   */
  protected ?string $operationName;

  /**
   * The variables in the query.
   */
  protected ?array $variables;

  /**
   * Create a new ConnectionAckMessage instance.
   *
   * @param string $id
   *   A unique operation id.
   * @param string $query
   *   The query that is being subscribed to.
   * @param string|null $operation_name
   *   An operation name (optional).
   * @param array|null $variables
   *   Variables to be used in the query (optional).
   */
  public function __construct(string $id, string $query, ?string $operation_name = NULL, ?array $variables = NULL) {
    $this->id = $id;
    $this->query = $query;
    $this->operationName = $operation_name;
    $this->variables = $variables;
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
    if (empty($data['payload']) || empty($data['payload']['query'])) {
      throw new InvalidMessageException("Missing payload.query");
    }

    return new static($data['id'], $data['payload']['query'], $data['payload']['operationName'] ?? NULL, $data['payload']['variables'] ?? NULL);
  }

  /**
   * Get the unique operation id.
   *
   * @return string
   *   The unique operation id.
   */
  public function getId() : string {
    return $this->id;
  }

  /**
   * The query that this message wants to subscribe to.
   *
   * @return string
   *   The query.
   */
  public function getQuery() : string {
    return $this->query;
  }

  /**
   * The name of the operation this message wants to subscribe to.
   *
   * @return null|string
   *   The operation name if specified.
   */
  public function getOperationName() : ?string {
    return $this->operationName;
  }

  /**
   * The variables for this subscription if any.
   *
   * @return array|null
   *   The variables if specified.
   */
  public function getVariables() : ?array {
    return $this->variables;
  }

  /**
   * Get the payload for this message.
   *
   * @return array
   *   The payload for the message.
   */
  public function getPayload() : array {
    return [
      "operationName" => $this->operationName,
      "query" => $this->query,
      "variables" => $this->variables,
    ];
  }

}
