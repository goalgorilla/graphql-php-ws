<?php

namespace GraphQLWs;

use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\WebSocket\WsConnection;
use Ratchet\WebSocket\WsServerInterface;
use React\EventLoop\LoopInterface;
use GraphQLWs\Exception\ConnectionInitialisationTimeoutException;
use GraphQLWs\Exception\ConnectionExceptionInterface;
use GraphQLWs\Exception\InvalidMessageException;
use GraphQLWs\Exception\UnauthorizedException;
use GraphQLWs\Message\CompleteMessage;
use GraphQLWs\Message\ConnectionAckMessage;
use GraphQLWs\Message\ConnectionInitMessage;
use GraphQLWs\Message\ClientMessageInterface;
use GraphQLWs\Message\SubscribeMessage;

/**
 * GraphQL Subscription Server.
 *
 * This class implements a Ratchet WebSocket server delegate that can handle the
 * graphql-transport-ws protocol to serve GraphQL subscriptions over WebSockets.
 *
 * See https://github.com/enisdenjo/graphql-ws/blob/master/PROTOCOL.md
 */
class GraphQLWsSubscriptionServer implements MessageComponentInterface, WsServerInterface {

  /**
   * A logging implementation.
   */
  protected LoggerInterface $logger;

  /**
   * The ReactPHP Event Loop.
   */
  protected LoopInterface $loop;

  /**
   * The connected clients.
   */
  protected \SplObjectStorage $clients;

  /**
   * The number of seconds a client has to init an opened connection.
   */
  protected int $connectionInitWaitTimeout = 3;

  /**
   * Create a new GraphQLSubscriptionServer instance.
   */
  public function __construct(LoggerInterface $logger, LoopInterface $loop) {
    $this->logger = $logger;
    $this->loop = $loop;
    $this->clients = new \SplObjectStorage();
  }

  /**
   * {@inheritdoc}
   */
  public function onOpen(ConnectionInterface $conn) {
    $conn = $this->assertWsConnection($conn);

    // A connection has only a limited time to send the ConnectInit message so
    // create a timer that automatically closes the connection if that doesn't
    // happen. A cancel function is stored that will be called when a
    // ConnectInit function is received.
    $timeout_timer = $this->loop->addTimer($this->connectionInitWaitTimeout, fn () => $conn->close((new ConnectionInitialisationTimeoutException())->getCode()));
    $cancel_timeout = fn () => $this->loop->cancelTimer($timeout_timer);

    // Create the metadata that keeps track of the connection's state.
    $metadata = new ConnectionMetadata($cancel_timeout);
    $this->clients->attach($conn, $metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function onMessage(ConnectionInterface $from, $msg_raw) {
    $from = $this->assertWsConnection($from);

    try {
      $msg = $this->parseClientMessage($msg_raw);

      $this->logger->debug("< {type}", ['type' => $msg->getType()]);

      /** @var \GraphQLWs\ConnectionMetadata $metadata */
      $metadata = $this->clients[$from];

      switch ($msg->getType()) {
        case ConnectionInitMessage::$type:
          // TODO: Validate authentication.
          $metadata->markInitialised();

          $ack = new ConnectionAckMessage();
          $this->logger->debug("> {type}", ['type' => $ack->getType()]);
          $from->send(json_encode($ack->jsonSerialize()));
          break;

        case SubscribeMessage::$type:
          // A connection must be initialized before it can subscribe.
          if (!$metadata->isInitialized()) {
            throw new UnauthorizedException();
          }
          print_r($msg->jsonSerialize());
          // TODO: Implement delegate onSubscription.
          break;

        case CompleteMessage::$type:
          // No need to clean up as onClose will be called when the connection
          // closes and cleanup should be done there.
          $from->close();

          // TODO: Implement delegate unsubscribe.
          break;

        default:
          throw new \RuntimeException("Missing implementation for '{$msg->getType()}' message type.");
      }

    }
    catch (ConnectionExceptionInterface $e) {
      // TODO: Log error for our own info.
      $from->close($e->getCode());
    }

  }

  /**
   * {@inheritdoc}
   */
  public function onClose(ConnectionInterface $conn) {
    $conn = $this->assertWsConnection($conn);

    $this->clients->detach($conn);
  }

  /**
   * {@inheritdoc}
   */
  public function onError(ConnectionInterface $conn, \Exception $e) {
    $conn = $this->assertWsConnection($conn);
    $this->logger->error($e->getMessage());
    $conn->close();
  }

  /**
   * {@inheritdoc}
   */
  public function getSubProtocols() {
    return ['graphql-transport-ws'];
  }

  /**
   * Parse a client message.
   *
   * @param string $message
   *   The raw JSON string.
   *
   * @return \GraphQLWs\Message\ClientMessageInterface
   *   A valid client message.
   *
   * @throws \GraphQLWs\Exception\InvalidMessageException
   *   The provided JSON string was not a valid Client message.
   */
  protected function parseClientMessage(string $message): ClientMessageInterface {
    try {
      $data = json_decode($message, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new InvalidMessageException("Invalid JSON");
    }

    if (empty($data['type'])) {
      throw new InvalidMessageException("Missing type");
    }

    switch ($data['type']) {
      case ConnectionInitMessage::$type:
        return ConnectionInitMessage::fromArray($data);

      case SubscribeMessage::$type:
        return SubscribeMessage::fromArray($data);

      case CompleteMessage::$type:
        return CompleteMessage::fromArray($data);

      default:
        throw new InvalidMessageException("Unsupported type '{$data['type']}'");
    }
  }

  /**
   * Verify that a received connection is a websocket connection.
   *
   * This is needed to be able to close connections with the proper status
   * message while still conforming to the overall message interface and making
   * PHP happy.
   *
   * @param \Ratchet\ConnectionInterface $connection
   *   The connection to check.
   *
   * @return \Ratchet\WebSocket\WsConnection
   *   The connection that was checked (to improve type-hints).
   */
  private function assertWsConnection(ConnectionInterface $connection) : WsConnection {
    if (!$connection instanceof WsConnection) {
      $class = __CLASS__;
      throw new \RuntimeException("${class} was used to handle a non-WebSocket connection. This points to an implementation error of the subscription server.");
    }

    return $connection;
  }

}
