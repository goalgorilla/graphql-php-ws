<?php

namespace GraphQLWs;

use GraphQL\Error\Error as GraphQLError;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Utils\AST;
use GraphQLWs\Exception\DuplicateSubscriberException;
use GraphQLWs\Exception\OperationNotFoundException;
use GraphQLWs\Exception\UnsupportedOperationException;
use GraphQLWs\Message\ErrorMessage;
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
use React\Promise\PromiseInterface;
use function React\Promise\all as promise_all;

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
   * The event handlers for GraphQL subscriptions.
   */
  protected \SplObjectStorage $eventHandlers;

  /**
   * The connected clients.
   */
  protected \SplObjectStorage $clients;

  /**
   * The instantiated GraphQL subscriptions.
   *
   * Used to make sure new subscriptions are passed with a unique id.
   */
  protected array $subscriptions = [];

  /**
   * The number of seconds a client has to init an opened connection.
   */
  protected int $connectionInitWaitTimeout = 3;

  /**
   * Create a new GraphQLSubscriptionServer instance.
   *
   * TODO: Determine whether a single subscriber can be passed or multiple can
   *   be attached.
   */
  public function __construct(LoggerInterface $logger, LoopInterface $loop) {
    $this->logger = $logger;
    $this->loop = $loop;
    $this->eventHandlers = new \SplObjectStorage();
    $this->clients = new \SplObjectStorage();
  }

  /**
   * Add an event handler for subscription related events.
   *
   * @param \GraphQLWs\GraphQLWsEventHandlerInterface $handler
   *   A GraphQL Subscription Event Handler instance.
   */
  public function addEventHandler(GraphQLWsEventHandlerInterface $handler): void {
    $this->eventHandlers->attach($handler);
  }

  /**
   * Remove an event handler for subscription related events.
   *
   * @param \GraphQLWs\GraphQLWsEventHandlerInterface $handler
   *   A GraphQL Subscription Event Handler instance.
   */
  public function removeEventHandler(GraphQLWsEventHandlerInterface $handler): void {
    $this->eventHandlers->detach($handler);
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
          /** @var \GraphQLWs\Message\ConnectionInitMessage $msg */

          $metadata->initialise();
          $this->delegateOnConnectionInit($from, $msg->getPayload())
            ->done(
              function ($isAccepted) use ($metadata, $from) {
                // If one of the authentication handlers did not accept the
                // connection then we close it.
                if (!$isAccepted) {
                  $from->close();
                  return;
                }

                // Otherwise we mark the connection as accepted and send a
                // connection acknowledgement.
                $metadata->accept();
                $from->send(json_encode((new ConnectionAckMessage())->jsonSerialize()));
              }
            );

          break;

        case SubscribeMessage::$type:
          /** @var \GraphQLWs\Message\SubscribeMessage $msg */
          // A connection must be initialized before it can subscribe.
          if (!$metadata->isAccepted()) {
            throw new UnauthorizedException();
          }

          // Verify this connection ID is not already in use.
          if (isset($this->subscriptions[$msg->getId()])) {
            throw new DuplicateSubscriberException($msg->getId());
          }

          // Associate the subscription ID with this client.
          $this->subscriptions[$msg->getId()] = $from;

          try {
            $document = Parser::parse($msg->getQuery());

            // If no operation was found we can't continue.
            $operation = AST::getOperationAST($document, $msg->getOperationName());
            if ($operation === NULL) {
              throw new OperationNotFoundException('Could not identify operation.');
            }

            // We don't currently support anything besides subscriptions.
            // TODO: Implement query and mutation support in this library.
            if ($operation->operation !== 'subscription') {
              throw new UnsupportedOperationException("This server only supports 'subscription' operations.");
            }

            $this->delegateOnSubscribe($msg->getId(), $from, $document, $msg->getOperationName(), $msg->getVariables());
          }
          catch (GraphQLError $e) {
            $error = new ErrorMessage($msg->getId(), [$e]);

            $this->logger->error(json_encode($error->jsonSerialize(), JSON_PRETTY_PRINT));
            $from->send(json_encode($error->jsonSerialize()));
          }

          break;

        case CompleteMessage::$type:
          /** @var \GraphQLWs\Message\CompleteMessage $msg */
          // Verify that the closed subscription belongs to this connection.
          if (!isset($this->subscriptions[$msg->getId()]) || $this->subscriptions[$msg->getId()] !== $from) {
            throw new InvalidMessageException("The provided subscription is not established or does not belong to this client.");
          }

          unset($this->subscriptions[$msg->getId()]);
          $this->delegateOnComplete($msg->getId());
          break;

        default:
          throw new \RuntimeException("Missing implementation for '{$msg->getType()}' message type.");
      }

    }
    catch (ConnectionExceptionInterface $e) {
      // TODO: Log error for our own info, what level should client errors be?
      $from->close($e->getCode());
    }

  }

  /**
   * {@inheritdoc}
   */
  public function onClose(ConnectionInterface $conn) {
    $conn = $this->assertWsConnection($conn);

    // Unregister any subscriptions for this client.
    $subscriptions = array_filter($this->subscriptions, static fn ($c) => $c === $conn);
    foreach (array_keys($subscriptions) as $subscription_id) {
      unset($this->subscriptions[$subscription_id]);
      $this->delegateOnComplete($subscription_id);
    }

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
   * Calls the onConnectionInit method for all registered event handlers.
   *
   * @See GraphQLWsInitHandlerInterface::onConnectionInit().
   */
  protected function delegateOnConnectionInit(WsConnection $client, ?array $payload) : PromiseInterface {
    // By default we indicate that access is allowed.
    $promises = [];

    foreach ($this->eventHandlers as $handler) {
      if ($handler instanceof GraphQLWsInitHandlerInterface) {
        $promises[] = $handler->onConnectionInit($client, $payload);
      }
    }

    return promise_all($promises)
      ->then(
        // A connection is accepted if none of the handlers indicate that it
        // shouldn't be accepted (they all resolve to TRUE). The default is TRUE
        // so that connections with no init event handlers are accepted.
        fn (array $results) => array_reduce(
          $results,
          static fn ($acc, $result) => $acc && $result,
          TRUE
        ),
        // If we encounter any unhandled rejections we can't authenticate so we
        // also deny authentication.
        function ($e) {
          // Provide developers with information. The correct way to
          // reject authentication is to return FALSE from the handler.
          // Any errors should be handled in the handler itself.
          if ($e instanceof \Throwable) {
            $this->logger->error(
              "Unhandled exception in authentication handler: {message}\n{file}:{line}",
              [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
              ]
            );
          }
          else {
            $this->logger->error(
              "Unhandled error in authentication handler: {rejection}",
              ['rejection' => json_encode($e)]
            );
          }

          return FALSE;
        }
      );
  }

  /**
   * Calls the onSubscribe method for all registered event handlers.
   *
   * @See GraphQLWsSubscriberInterface::onSubscribe().
   */
  protected function delegateOnSubscribe(string $subscription_id, WsConnection $client, DocumentNode $document, ?string $operationName = NULL, ?array $variables = NULL) : void {
    foreach ($this->eventHandlers as $handler) {
      if ($handler instanceof GraphQLWsSubscriberInterface) {
        $handler->onSubscribe($subscription_id, $client, $document, $operationName, $variables);
      }
    }
  }

  /**
   * Calls the onComplete method for all registered event handlers.
   *
   * @See GraphQLWsSubscriberInterface::onComplete().
   */
  protected function delegateOnComplete(string $subcription_id) {
    foreach ($this->eventHandlers as $handler) {
      if ($handler instanceof GraphQLWsSubscriberInterface) {
        $handler->onComplete($subcription_id);
      }
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
